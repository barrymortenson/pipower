<?php

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create an event', function () {
    $event = Event::create([
        'type' => 'power_lost',
        'message' => 'Power lost, running on battery',
        'data' => ['battery_level' => 85],
    ]);

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->type)->toBe('power_lost')
        ->and($event->data)->toBe(['battery_level' => 85]);
});

it('casts data to array', function () {
    $event = Event::create([
        'type' => 'power_restored',
        'message' => 'Power restored',
        'data' => ['downtime_seconds' => 120],
    ]);

    $fresh = Event::find($event->id);
    expect($fresh->data)->toBeArray()
        ->and($fresh->data['downtime_seconds'])->toBe(120);
});
