<?php

use App\Services\NutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 200 when on AC power and ups connected', function () {
    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'connected' => true,
            'on_battery' => false,
            'battery_low' => false,
        ]);
        $mock->shouldReceive('healthCheck')->andReturn([
            'healthy' => true,
            'error' => null,
        ]);
    });

    $response = $this->get('/api/health');

    $response->assertStatus(200);
});

it('returns 503 when on battery', function () {
    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('getStatus')->andReturn([
            'connected' => true,
            'on_battery' => true,
            'battery_low' => false,
        ]);
        $mock->shouldReceive('healthCheck')->andReturn([
            'healthy' => true,
            'error' => null,
        ]);
    });

    $response = $this->get('/api/health');

    $response->assertStatus(503);
});

it('returns 503 when ups disconnected', function () {
    $this->mock(NutService::class, function ($mock) {
        $mock->shouldReceive('getStatus')->andReturn(null);
        $mock->shouldReceive('healthCheck')->andReturn([
            'healthy' => false,
            'error' => 'UPS not found',
        ]);
    });

    $response = $this->get('/api/health');

    $response->assertStatus(503);
});
