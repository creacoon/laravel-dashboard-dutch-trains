<?php

namespace Creacoon\LiveDepartureBoardTile;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Spatie\Dashboard\Components\BaseTileComponent;

class LiveDepartureBoardTileComponent extends BaseTileComponent
{
    #[Locked]
    public string $station;

    #[Locked]
    public ?string $stationName = null;

    public function mount(string $position, ?string $station = null, ?string $stationName = null): void
    {
        $this->position = $position;
        $this->station = $station ?? LiveDepartureBoardStore::defaultStation();

        $configuredStationName = config('dashboard.tiles.live_departure_board.station_name');
        $this->stationName = $stationName ?? ($station === null && is_string($configuredStationName)
            ? $configuredStationName
            : null);
    }

    public function render(): View
    {
        $store = LiveDepartureBoardStore::make($this->station);
        $allDisruptions = $store->disruptions();
        $visibleDisruptions = array_slice(
            $allDisruptions,
            0,
            max(1, (int) config('dashboard.tiles.live_departure_board.visible_disruptions', 2)),
        );

        $departures = $store->upcomingDepartures(max(1, (int) config('dashboard.tiles.live_departure_board.visible_departures', 3)));

        return view()->make('dashboard-live-departure-board-tile::tile', [
            'departures' => $departures,
            'departures_are_stale' => $store->departuresAreStale(),
            'departures_stale_message' => $departures === []
                ? 'Departure information unavailable; the latest refresh failed.'
                : 'Departure update failed; showing last known departures.',
            'disruptions' => $visibleDisruptions,
            'additional_disruptions_count' => count($allDisruptions) - count($visibleDisruptions),
            'disruptions_are_stale' => $store->disruptionsAreStale(),
            'disruptions_stale_message' => $allDisruptions === []
                ? 'Disruption information unavailable; the latest refresh failed.'
                : 'Disruption update failed; showing last known information.',
            'station_name' => $this->stationName ?: $this->station,
        ]);
    }
}
