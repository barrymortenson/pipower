<?php

use App\Services\NetworkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns network status json', function () {
    $this->mock(NetworkService::class, function ($mock) {
        $mock->shouldReceive('getNetworkStatus')->andReturn([
            'ethernet' => [
                'connected' => true,
                'ip' => '192.168.1.100',
                'gateway' => '192.168.1.1',
                'dns_primary' => '8.8.8.8',
                'dns_secondary' => '8.8.4.4',
            ],
            'hostname' => 'pipower-01',
        ]);
    });

    $response = $this->getJson('/api/network/status');

    $response->assertStatus(200)
        ->assertJsonPath('ethernet.connected', true)
        ->assertJsonPath('ethernet.ip', '192.168.1.100')
        ->assertJsonPath('hostname', 'pipower-01');
});

it('returns disconnected network status', function () {
    $this->mock(NetworkService::class, function ($mock) {
        $mock->shouldReceive('getNetworkStatus')->andReturn([
            'ethernet' => [
                'connected' => false,
                'ip' => null,
                'gateway' => null,
                'dns_primary' => null,
                'dns_secondary' => null,
            ],
            'hostname' => 'pipower-01',
        ]);
    });

    $response = $this->getJson('/api/network/status');

    $response->assertStatus(200)
        ->assertJsonPath('ethernet.connected', false)
        ->assertJsonPath('ethernet.ip', null);
});
