<?php

use App\Models\Setting;
use App\Services\NotificationService;
use App\Services\NutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('runs check and shows connected status', function () {
    Setting::set('last_power_state', 'on_mains');
    Setting::set('last_ups_connected', true);
    Setting::set('last_battery_low', false);

    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('healthCheck')->andReturn(['healthy' => true, 'error' => null]);
        $mock->shouldReceive('getStatus')->andReturn([
            'connected' => true,
            'model' => 'CyberPower CP1500AVRLCD',
            'battery_level' => 100,
            'battery_low' => false,
            'battery_runtime' => 3600,
            'charging' => false,
            'load_percent' => 15,
            'on_battery' => false,
            'raw_status' => 'OL',
        ]);
    });

    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldNotReceive('notify');
    });

    $this->artisan('power:check')
        ->expectsOutputToContain('Connected')
        ->assertExitCode(0);
});

it('shows not connected message', function () {
    Setting::set('last_ups_connected', false);

    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('healthCheck')->andReturn(['healthy' => false, 'error' => 'NUT daemon not running']);
        $mock->shouldReceive('getStatus')->andReturn(null);
    });

    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldNotReceive('notify');
    });

    $this->artisan('power:check')
        ->expectsOutputToContain('Not connected')
        ->assertExitCode(0);
});

it('shows on battery status', function () {
    Setting::set('last_power_state', 'on_battery');
    Setting::set('last_ups_connected', true);
    Setting::set('last_battery_low', false);

    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('healthCheck')->andReturn(['healthy' => true, 'error' => null]);
        $mock->shouldReceive('getStatus')->andReturn([
            'connected' => true,
            'model' => 'CyberPower CP1500AVRLCD',
            'battery_level' => 85,
            'battery_low' => false,
            'battery_runtime' => 1800,
            'charging' => false,
            'load_percent' => 20,
            'on_battery' => true,
            'raw_status' => 'OB',
        ]);
    });

    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldNotReceive('notify');
    });

    $this->artisan('power:check')
        ->expectsOutputToContain('On Battery')
        ->assertExitCode(0);
});
