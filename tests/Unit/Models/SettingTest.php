<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can get and set values', function () {
    Setting::set('hostname', 'pipower');

    expect(Setting::get('hostname'))->toBe('pipower');
});

it('returns default when key not found', function () {
    expect(Setting::get('missing', 'default'))->toBe('default');
});

it('updates existing values', function () {
    Setting::set('hostname', 'old');
    Setting::set('hostname', 'new');

    expect(Setting::get('hostname'))->toBe('new')
        ->and(Setting::where('key', 'hostname')->count())->toBe(1);
});

it('handles array values', function () {
    Setting::set('smtp', ['host' => 'smtp.example.com', 'port' => 587]);

    $value = Setting::get('smtp');
    expect($value)->toBeArray()
        ->and($value['host'])->toBe('smtp.example.com');
});
