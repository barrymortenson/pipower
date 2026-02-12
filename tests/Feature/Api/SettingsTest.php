<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gets settings', function () {
    Setting::set('hostname', 'mydevice');

    $response = $this->getJson('/api/settings');

    $response->assertStatus(200)
        ->assertJsonPath('hostname', 'mydevice');
});

it('updates settings', function () {
    $response = $this->putJson('/api/settings', [
        'hostname' => 'newname',
    ]);

    $response->assertStatus(200);
    expect(Setting::get('hostname'))->toBe('newname');
});
