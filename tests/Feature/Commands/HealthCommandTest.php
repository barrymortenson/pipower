<?php

use App\Services\NutService;

it('shows success when healthy', function () {
    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('healthCheck')->andReturn(['healthy' => true, 'error' => null]);
        $mock->shouldReceive('getStatus')->andReturn([
            'connected' => true,
            'model' => 'CyberPower CP1500AVRLCD',
            'battery_level' => 100,
            'battery_low' => false,
            'load_percent' => 15,
            'on_battery' => false,
        ]);
    });

    $this->artisan('power:health')
        ->expectsOutputToContain('NUT daemon is running')
        ->expectsOutputToContain('UPS is detected')
        ->assertExitCode(0);
});

it('shows failure when nut not running', function () {
    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('healthCheck')->andReturn([
            'healthy' => false,
            'error' => 'NUT daemon not running',
        ]);
        $mock->shouldNotReceive('getStatus');
    });

    $this->artisan('power:health')
        ->expectsOutputToContain('NUT daemon not running')
        ->assertExitCode(1);
});

it('shows failure when ups not found', function () {
    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('healthCheck')->andReturn([
            'healthy' => false,
            'error' => 'UPS not found - check USB connection or driver compatibility',
        ]);
        $mock->shouldNotReceive('getStatus');
    });

    $this->artisan('power:health')
        ->expectsOutputToContain('UPS not found')
        ->assertExitCode(1);
});
