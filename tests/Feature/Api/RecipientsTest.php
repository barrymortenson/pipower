<?php

use App\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists all recipients', function () {
    Recipient::create(['type' => 'email', 'value' => 'test@example.com', 'enabled' => true]);
    Recipient::create(['type' => 'phone', 'value' => '+15551234567', 'enabled' => false]);

    $response = $this->getJson('/api/recipients');

    $response->assertStatus(200)
        ->assertJsonCount(2);
});

it('creates a recipient', function () {
    $response = $this->postJson('/api/recipients', [
        'type' => 'email',
        'value' => 'new@example.com',
        'enabled' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('value', 'new@example.com');

    expect(Recipient::count())->toBe(1);
});

it('updates a recipient', function () {
    $recipient = Recipient::create(['type' => 'email', 'value' => 'old@example.com', 'enabled' => true]);

    $response = $this->putJson("/api/recipients/{$recipient->id}", [
        'enabled' => false,
    ]);

    $response->assertStatus(200);
    expect($recipient->fresh()->enabled)->toBeFalse();
});

it('deletes a recipient', function () {
    $recipient = Recipient::create(['type' => 'email', 'value' => 'delete@example.com', 'enabled' => true]);

    $response = $this->deleteJson("/api/recipients/{$recipient->id}");

    $response->assertStatus(204);
    expect(Recipient::count())->toBe(0);
});
