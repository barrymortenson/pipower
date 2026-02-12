<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns empty array when no events', function () {
    $response = $this->getJson('/api/events');

    $response->assertStatus(200)
        ->assertJson([]);
});

it('returns events in descending order by created_at', function () {
    $old = Event::create(['type' => 'power_lost', 'message' => 'First event', 'data' => []]);
    $old->forceFill(['created_at' => now()->subMinutes(3)])->save();

    $mid = Event::create(['type' => 'power_restored', 'message' => 'Second event', 'data' => []]);
    $mid->forceFill(['created_at' => now()->subMinutes(2)])->save();

    $new = Event::create(['type' => 'battery_low', 'message' => 'Third event', 'data' => []]);
    $new->forceFill(['created_at' => now()->subMinute()])->save();

    $response = $this->getJson('/api/events');

    $response->assertStatus(200);

    $events = $response->json();
    expect($events)->toHaveCount(3)
        ->and($events[0]['type'])->toBe('battery_low')
        ->and($events[1]['type'])->toBe('power_restored')
        ->and($events[2]['type'])->toBe('power_lost');
});

it('respects limit query parameter', function () {
    Event::create(['type' => 'power_lost', 'message' => 'First', 'data' => []]);
    Event::create(['type' => 'power_restored', 'message' => 'Second', 'data' => []]);
    Event::create(['type' => 'battery_low', 'message' => 'Third', 'data' => []]);

    $response = $this->getJson('/api/events?limit=2');

    $response->assertStatus(200);
    expect($response->json())->toHaveCount(2);
});

it('includes all event fields in response', function () {
    Event::create([
        'type' => 'power_lost',
        'message' => 'Power outage detected',
        'data' => ['timestamp' => '2025-01-01T00:00:00+00:00'],
    ]);

    $response = $this->getJson('/api/events');

    $response->assertStatus(200)
        ->assertJsonStructure([
            '*' => ['id', 'type', 'message', 'data', 'created_at', 'updated_at'],
        ]);
});
