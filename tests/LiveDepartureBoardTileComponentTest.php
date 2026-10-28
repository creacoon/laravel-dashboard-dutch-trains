<?php

namespace Creacoon\LiveDepartureBoardTile\Tests;

use Creacoon\LiveDepartureBoardTile\LiveDepartureBoardStore;
use Creacoon\LiveDepartureBoardTile\LiveDepartureBoardTileComponent;
use Livewire\Livewire;

class LiveDepartureBoardTileComponentTest extends TestCase
{
    public function test_shows_the_delay_of_a_delayed_departure_and_the_status_of_the_others(): void
    {
        LiveDepartureBoardStore::make()->refresh([
            $this->storedDeparture('Utrecht'),
            [
                ...$this->storedDeparture('Maastricht'),
                'status' => 'Delayed',
                'status_code' => 'delayed',
                'detail' => '+ 4 min',
            ],
            [
                ...$this->storedDeparture('Nijmegen'),
                'status' => 'Cancelled',
                'status_code' => 'cancelled',
                'detail' => 'Service cancelled',
            ],
        ], []);

        Livewire::withoutLazyLoading()
            ->test(LiveDepartureBoardTileComponent::class, ['position' => 'a1', 'stationName' => 'Amsterdam Centraal'])
            ->assertSee('Amsterdam Centraal departures')
            ->assertSeeInOrder(['Utrecht', 'On time', 'Maastricht', '+ 4 min', 'Nijmegen', 'Cancelled'])
            ->assertDontSee('Service cancelled');
    }

    public function test_shows_active_disruptions_above_the_departures(): void
    {
        LiveDepartureBoardStore::make()->refresh([$this->storedDeparture('Utrecht')], [[
            'title' => 'Disruption near Amsterdam',
            'detail' => 'Trains are running less frequently.',
        ]]);

        Livewire::withoutLazyLoading()
            ->test(LiveDepartureBoardTileComponent::class, ['position' => 'a1'])
            ->assertSeeInOrder(['Disruption near Amsterdam', 'Trains are running less frequently.', 'Utrecht']);
    }

    public function test_refreshes_at_the_configured_interval(): void
    {
        config()->set('dashboard.tiles.live_departure_board.refresh_interval_in_seconds', 30);

        Livewire::withoutLazyLoading()
            ->test(LiveDepartureBoardTileComponent::class, ['position' => 'a1'])
            ->assertSeeHtml('wire:poll.30s')
            ->assertSee('No departures');
    }
}
