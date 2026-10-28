<?php

namespace Creacoon\LiveDepartureBoardTile\Tests;

use Creacoon\LiveDepartureBoardTile\LiveDepartureBoardStore;
use Creacoon\LiveDepartureBoardTile\LiveDepartureBoardTileServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Dashboard\DashboardServiceProvider;

/** @phpstan-import-type Departure from LiveDepartureBoardStore */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            DashboardServiceProvider::class,
            LiveDepartureBoardTileServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('dashboard.tiles.live_departure_board.api_key', 'test-api-key');
        $app['config']->set('dashboard.tiles.live_departure_board.station', 'Asd');
        $app['config']->set('dashboard.tiles.live_departure_board.timezone', 'Europe/Amsterdam');
        $app['config']->set('dashboard.tiles.live_departure_board.visible_departures', 5);
    }

    protected function setUp(): void
    {
        parent::setUp();

        (include __DIR__.'/../vendor/spatie/laravel-dashboard/database/migrations/create_dashboard_tiles_table.php.stub')->up();
    }

    /** @return Departure */
    protected function storedDeparture(string $destination, ?string $plannedAt = null): array
    {
        return [
            'time' => '12:00',
            'destination' => $destination,
            'service' => 'Intercity',
            'status' => 'On time',
            'status_code' => 'on_time',
            'detail' => 'On schedule',
            'track' => '1',
            'planned_at' => $plannedAt ?? now()->addHour()->toIso8601String(),
            'actual_at' => null,
        ];
    }
}
