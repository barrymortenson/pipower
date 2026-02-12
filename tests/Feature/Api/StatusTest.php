<?php

use App\Models\Event;
use App\Services\NutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns full status json', function () {
    Event::create([
        'type' => 'power_restored',
        'message' => 'Power restored',
        'data' => [],
    ]);

    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'connected' => true,
            'model' => 'CyberPower CP1500AVRLCD',
            'battery_level' => 100,
            'battery_low' => false,
            'load_percent' => 15,
            'on_battery' => false,
        ]);
        $mock->shouldReceive('healthCheck')->andReturn([
            'healthy' => true,
            'error' => null,
        ]);
    });

    $response = $this->getJson('/api/status');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'power' => ['status', 'on_battery_since'],
            'ups' => ['connected', 'model', 'battery_level', 'battery_low', 'load_percent'],
            'device' => ['hostname', 'ip', 'uptime_seconds', 'version'],
            'last_event' => ['type', 'timestamp'],
        ])
        ->assertJsonPath('power.status', 'on_mains')
        ->assertJsonPath('ups.model', 'CyberPower CP1500AVRLCD');
});

it('returns on_battery status when on battery', function () {
    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'connected' => true,
            'model' => 'CyberPower CP1500AVRLCD',
            'battery_level' => 85,
            'battery_low' => false,
            'load_percent' => 20,
            'on_battery' => true,
        ]);
        $mock->shouldReceive('healthCheck')->andReturn([
            'healthy' => true,
            'error' => null,
        ]);
    });

    $response = $this->getJson('/api/status');

    $response->assertStatus(200)
        ->assertJsonPath('power.status', 'on_battery');
});
