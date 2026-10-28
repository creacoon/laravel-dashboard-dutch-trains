<?php

namespace Creacoon\LiveDepartureBoardTile\Commands;

use Carbon\CarbonImmutable;
use Creacoon\LiveDepartureBoardTile\LiveDepartureBoardStore;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * @phpstan-import-type Departure from LiveDepartureBoardStore
 * @phpstan-import-type Disruption from LiveDepartureBoardStore
 */
class FetchLiveDeparturesCommand extends Command
{
    protected $signature = 'dashboard:fetch-live-departure-board {--station=}';

    protected $description = 'Fetch departures and disruptions for the live departure board';

    public function handle(): int
    {
        $station = (string) ($this->option('station') ?: LiveDepartureBoardStore::defaultStation());
        $store = LiveDepartureBoardStore::make($station);
        $apiKey = (string) config('dashboard.tiles.live_departure_board.api_key');

        if (blank($apiKey)) {
            $store->refresh(null, null);
            $this->error('Set dashboard.tiles.live_departure_board.api_key (for example via NS_API_KEY in config/dashboard.php) before fetching live departures.');

            return self::FAILURE;
        }

        $encodedStation = rawurlencode($station);
        $responses = Http::pool(fn (Pool $pool) => [
            $this->nsRequest($pool->as('departures'), $apiKey)->get('v2/departures', [
                'station' => $station,
            ]),
            $this->nsRequest($pool->as('disruptions'), $apiKey)->get("v3/disruptions/station/{$encodedStation}", [
                'isActive' => 'true',
            ]),
        ]);

        $departures = $this->parseDepartures($responses['departures']);
        $disruptions = $this->parseDisruptions($responses['disruptions']);

        $store->refresh(
            is_array($departures) ? $departures : null,
            is_array($disruptions) ? $disruptions : null,
        );

        $errors = array_filter([$departures, $disruptions], is_string(...));

        foreach ($errors as $error) {
            $this->error($error);
        }

        if (! is_array($departures)) {
            return self::FAILURE;
        }

        if (! is_array($disruptions)) {
            return self::FAILURE;
        }

        $departuresCount = count($departures);
        $disruptionsCount = count($disruptions);

        $this->info("Stored {$departuresCount} departures and {$disruptionsCount} active disruptions.");

        return self::SUCCESS;
    }

    private function nsRequest(PendingRequest $request, string $apiKey): PendingRequest
    {
        return $request
            ->baseUrl('https://gateway.apiportal.ns.nl/reisinformatie-api/api')
            ->acceptJson()
            ->withHeaders([
                'Cache-Control' => 'no-cache',
                'Ocp-Apim-Subscription-Key' => $apiKey,
            ])
            ->connectTimeout(3)
            ->timeout(10);
    }

    /** @return list<Departure>|string */
    private function parseDepartures(Response|Throwable $response): array|string
    {
        if (! $response instanceof Response) {
            return 'Could not connect to the NS departures API.';
        }

        if ($response->failed()) {
            return "The NS departures API returned HTTP {$response->status()}.";
        }

        $apiDepartures = $response->json('payload.departures');

        if (! is_array($apiDepartures)) {
            return 'The NS departures API response did not contain a departures list.';
        }

        $departures = [];

        foreach ($apiDepartures as $apiDeparture) {
            if (! is_array($apiDeparture)) {
                continue;
            }

            if (($apiDeparture['departureStatus'] ?? null) === 'DEPARTED') {
                continue;
            }

            $departure = $this->mapDeparture($apiDeparture);

            if ($departure === null) {
                continue;
            }

            $departures[] = $departure;
        }

        return $departures;
    }

    /** @return list<Disruption>|string */
    private function parseDisruptions(Response|Throwable $response): array|string
    {
        if (! $response instanceof Response) {
            return 'Could not connect to the NS disruptions API.';
        }

        if ($response->failed()) {
            return "The NS disruptions API returned HTTP {$response->status()}.";
        }

        $apiDisruptions = $response->json('payload');

        if (! is_array($apiDisruptions)) {
            $apiDisruptions = $response->json();
        }

        if (is_array($apiDisruptions['disruptions'] ?? null)) {
            $apiDisruptions = $apiDisruptions['disruptions'];
        }

        if (! is_array($apiDisruptions)) {
            return 'The NS disruptions API response did not contain a disruptions list.';
        }

        $disruptions = [];

        foreach ($apiDisruptions as $apiDisruption) {
            if (! is_array($apiDisruption)) {
                continue;
            }

            if (($apiDisruption['isActive'] ?? true) !== true) {
                continue;
            }

            $disruption = $this->mapDisruption($apiDisruption);

            if ($disruption === null) {
                continue;
            }

            $disruptions[] = $disruption;
        }

        return $disruptions;
    }

    /**
     * @param  array<array-key, mixed>  $departure
     * @return ?Departure
     */
    private function mapDeparture(array $departure): ?array
    {
        $plannedDateTime = $departure['plannedDateTime'] ?? null;
        $actualDateTime = $departure['actualDateTime'] ?? null;
        $destination = $departure['direction'] ?? null;

        if (! is_string($plannedDateTime)) {
            return null;
        }

        if (! is_string($destination)) {
            return null;
        }

        try {
            $planned = CarbonImmutable::parse($plannedDateTime);
            $actual = is_string($actualDateTime) && filled($actualDateTime)
                ? CarbonImmutable::parse($actualDateTime)
                : null;
        } catch (Throwable) {
            return null;
        }

        $delayMinutes = $actual === null
            ? 0
            : max(0, intdiv($actual->getTimestamp() - $planned->getTimestamp(), 60));
        $isCancelled = (bool) ($departure['cancelled'] ?? false);

        [$status, $statusCode, $detail] = match (true) {
            $isCancelled => ['Cancelled', 'cancelled', 'Service cancelled'],
            $delayMinutes === 0 => ['On time', 'on_time', 'On schedule'],
            default => ['Delayed', 'delayed', "+ {$delayMinutes} min"],
        };

        return [
            'time' => $planned->setTimezone(config('dashboard.tiles.live_departure_board.timezone', 'Europe/Amsterdam'))->format('H:i'),
            'destination' => $destination,
            'service' => (string) data_get($departure, 'product.longCategoryName', $departure['name'] ?? $departure['trainCategory'] ?? 'Train'),
            'status' => $status,
            'status_code' => $statusCode,
            'detail' => $detail,
            'track' => (string) ($departure['actualTrack'] ?? $departure['plannedTrack'] ?? '—'),
            'planned_at' => $planned->toIso8601String(),
            'actual_at' => $actual?->toIso8601String(),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $disruption
     * @return ?Disruption
     */
    private function mapDisruption(array $disruption): ?array
    {
        $title = $disruption['title'] ?? null;

        if (! is_string($title)) {
            return null;
        }

        if (blank($title)) {
            return null;
        }

        $detail = collect([
            data_get($disruption, 'timespans.0.situation.label'),
            data_get($disruption, 'timespans.0.situation'),
            $disruption['description'] ?? null,
            $disruption['topic'] ?? null,
            $disruption['summary'] ?? null,
            $disruption['cause'] ?? null,
        ])->first(fn (mixed $candidate): bool => is_string($candidate) && filled($candidate), '');

        return [
            'title' => trim($title),
            'detail' => trim($detail),
        ];
    }
}
