<?php

namespace Creacoon\LiveDepartureBoardTile;

use Carbon\CarbonImmutable;
use Spatie\Dashboard\Models\Tile;

/**
 * @phpstan-type Departure array{
 *     time: string,
 *     destination: string,
 *     service: string,
 *     status: string,
 *     status_code: 'on_time'|'delayed'|'cancelled',
 *     detail: string,
 *     track: string,
 *     planned_at: string,
 *     actual_at: ?string,
 * }
 * @phpstan-type Disruption array{
 *     title: string,
 *     detail: string,
 * }
 */
final class LiveDepartureBoardStore
{
    private Tile $tile;

    public static function make(?string $station = null): self
    {
        return new self($station);
    }

    public static function defaultStation(): string
    {
        return (string) config('dashboard.tiles.live_departure_board.station', 'Rm');
    }

    public function __construct(?string $station = null)
    {
        $station = strtolower(trim($station ?? self::defaultStation()));

        $this->tile = Tile::firstOrCreateForName("live-departure-board-{$station}");
    }

    /**
     * Passing null for a feed keeps its last known data and marks it stale.
     *
     * @param  ?list<Departure>  $departures
     * @param  ?list<Disruption>  $disruptions
     */
    public function refresh(?array $departures, ?array $disruptions): self
    {
        $data = $this->tile->data ?? [];

        $data['departures_stale'] = $departures === null;
        $data['disruptions_stale'] = $disruptions === null;

        if ($departures !== null) {
            $data['departures'] = $departures;
        }

        if ($disruptions !== null) {
            $data['disruptions'] = $disruptions;
        }

        $this->tile->update(['data' => $data]);

        return $this;
    }

    /** @return list<Departure> */
    public function departures(): array
    {
        return $this->tile->getData('departures') ?? [];
    }

    /** @return list<Departure> */
    public function upcomingDepartures(int $limit): array
    {
        $upcomingDepartures = [];

        foreach ($this->departures() as $departure) {
            if (count($upcomingDepartures) === $limit) {
                break;
            }

            if (! CarbonImmutable::parse($departure['actual_at'] ?? $departure['planned_at'])->isFuture()) {
                continue;
            }

            $upcomingDepartures[] = $departure;
        }

        return $upcomingDepartures;
    }

    public function departuresAreStale(): bool
    {
        return (bool) $this->tile->getData('departures_stale');
    }

    /** @return list<Disruption> */
    public function disruptions(): array
    {
        return $this->tile->getData('disruptions') ?? [];
    }

    public function disruptionsAreStale(): bool
    {
        return (bool) $this->tile->getData('disruptions_stale');
    }
}
