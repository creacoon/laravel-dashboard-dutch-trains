<?php

namespace Creacoon\LiveDepartureBoardTile\Tests;

use Creacoon\LiveDepartureBoardTile\LiveDepartureBoardStore;
use Illuminate\Support\Facades\DB;
use Spatie\Dashboard\Models\Tile;

class LiveDepartureBoardStoreTest extends TestCase
{
    public function test_isolates_data_by_station(): void
    {
        $departures = [$this->storedDeparture('Utrecht')];

        LiveDepartureBoardStore::make('Asd')->refresh($departures, []);

        self::assertSame($departures, LiveDepartureBoardStore::make('Asd')->departures());
        self::assertSame([], LiveDepartureBoardStore::make('Rm')->departures());
    }

    public function test_refreshes_departures_disruptions_and_freshness_in_one_database_write(): void
    {
        $store = LiveDepartureBoardStore::make()->refresh(null, null);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $departures = [$this->storedDeparture('Utrecht')];
        $disruptions = [[
            'title' => 'Disruption near Roermond',
            'detail' => 'Trains are running less frequently.',
        ]];

        $store->refresh($departures, $disruptions);

        self::assertCount(1, DB::getQueryLog());
        self::assertSame($departures, $store->departures());
        self::assertSame($disruptions, $store->disruptions());
        self::assertFalse($store->departuresAreStale());
        self::assertFalse($store->disruptionsAreStale());
    }

    public function test_keeps_last_known_data_and_marks_it_stale_when_a_feed_is_missing(): void
    {
        $departures = [$this->storedDeparture('Utrecht')];
        $disruptions = [[
            'title' => 'Disruption near Roermond',
            'detail' => 'Trains are running less frequently.',
        ]];

        $store = LiveDepartureBoardStore::make()
            ->refresh($departures, $disruptions)
            ->refresh(null, null);

        self::assertSame($departures, $store->departures());
        self::assertSame($disruptions, $store->disruptions());
        self::assertTrue($store->departuresAreStale());
        self::assertTrue($store->disruptionsAreStale());
    }

    public function test_returns_only_upcoming_departures_up_to_the_limit(): void
    {
        LiveDepartureBoardStore::make()->refresh([
            $this->storedDeparture('Departed', now()->subMinute()->toIso8601String()),
            $this->storedDeparture('Utrecht'),
            $this->storedDeparture('Eindhoven'),
        ], []);

        $departures = LiveDepartureBoardStore::make()->upcomingDepartures(1);

        self::assertSame(['Utrecht'], array_column($departures, 'destination'));
    }

    public function test_treats_saved_data_without_stale_flags_as_fresh(): void
    {
        Tile::firstOrCreateForName('live-departure-board-asd')->update([
            'data' => [
                'departures' => [$this->storedDeparture('Utrecht')],
                'disruptions' => [[
                    'title' => 'Disruption near Roermond',
                    'detail' => 'Trains are running less frequently.',
                ]],
            ],
        ]);

        $store = LiveDepartureBoardStore::make();

        self::assertFalse($store->departuresAreStale());
        self::assertFalse($store->disruptionsAreStale());
    }
}
