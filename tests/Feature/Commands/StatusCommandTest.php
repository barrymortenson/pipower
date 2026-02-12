<?php

use App\Services\NutService;

it('shows status when ups connected', function () {
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

    $this->artisan('power:status')
        ->expectsOutputToContain('On AC Power')
        ->assertExitCode(0);
});

it('shows error when ups disconnected', function () {
    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('healthCheck')->andReturn(['healthy' => false, 'error' => 'NUT daemon not running']);
        $mock->shouldReceive('getStatus')->andReturn(null);
    });

    $this->artisan('power:status')
        ->expectsOutputToContain('UPS Not Connected')
        ->assertExitCode(1);
});

it('shows on battery label when on battery', function () {
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

    $this->artisan('power:status')
        ->expectsOutputToContain('On Battery')
        ->assertExitCode(0);
});
