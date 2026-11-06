<?php

use App\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists all recipients', function () {
    Recipient::create(['email' => 'test@example.com', 'enabled' => true]);
    Recipient::create(['phone' => '+15551234567', 'enabled' => false]);

    $response = $this->getJson('/api/recipients');

    $response->assertStatus(200)
        ->assertJsonCount(2);
});

it('creates an email-only recipient', function () {
    $response = $this->postJson('/api/recipients', [
        'email' => 'new@example.com',
        'enabled' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('email', 'new@example.com')
        ->assertJsonPath('phone', null);

    expect(Recipient::count())->toBe(1);
});

it('creates a phone-only recipient', function () {
    $response = $this->postJson('/api/recipients', [
        'phone' => '+15551234567',
        'email' => '',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('phone', '+15551234567')
        ->assertJsonPath('email', null);
});

it('creates a recipient with both email and phone', function () {
    $response = $this->postJson('/api/recipients', [
        'name' => 'Barry',
        'email' => 'barry@example.com',
        'phone' => '+15551234567',
        'enabled' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('name', 'Barry')
        ->assertJsonPath('email', 'barry@example.com')
        ->assertJsonPath('phone', '+15551234567');

    expect(Recipient::count())->toBe(1);
});

it('rejects a recipient with neither email nor phone', function () {
    $this->postJson('/api/recipients', ['name' => 'Nobody', 'email' => '', 'phone' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    expect(Recipient::count())->toBe(0);
});

it('rejects an invalid email address', function () {
    $this->postJson('/api/recipients', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('rejects a phone number that is not +1 and 10 digits', function (string $phone) {
    $this->postJson('/api/recipients', ['phone' => $phone])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');

    expect(Recipient::count())->toBe(0);
})->with(['8052053000', '+1805205300', '+1 805 205 3000', '+1805-205-3000', '+448052053000']);

it('rejects an update to an invalid phone number', function () {
    $recipient = Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    $this->putJson("/api/recipients/{$recipient->id}", ['phone' => '555-123-4567'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone');

    expect($recipient->fresh()->phone)->toBe('+15551234567');
});

it('updates a recipient', function () {
    $recipient = Recipient::create(['email' => 'old@example.com', 'enabled' => true]);

    $response = $this->putJson("/api/recipients/{$recipient->id}", [
        'enabled' => false,
    ]);

    $response->assertStatus(200);
    expect($recipient->fresh()->enabled)->toBeFalse();
});

it('adds a phone number to an email recipient', function () {
    $recipient = Recipient::create(['email' => 'barry@example.com', 'enabled' => true]);

    $this->putJson("/api/recipients/{$recipient->id}", ['phone' => '+15551234567'])
        ->assertStatus(200)
        ->assertJsonPath('email', 'barry@example.com')
        ->assertJsonPath('phone', '+15551234567');
});

it('removes one channel from a recipient that has both', function () {
    $recipient = Recipient::create(['email' => 'barry@example.com', 'phone' => '+15551234567', 'enabled' => true]);

    $this->putJson("/api/recipients/{$recipient->id}", ['email' => ''])
        ->assertStatus(200);

    expect($recipient->fresh()->email)->toBeNull()
        ->and($recipient->fresh()->phone)->toBe('+15551234567');
});

it('refuses an update that leaves a recipient with no email or phone', function () {
    $recipient = Recipient::create(['email' => 'barry@example.com', 'enabled' => true]);

    $this->putJson("/api/recipients/{$recipient->id}", ['email' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    expect($recipient->fresh()->email)->toBe('barry@example.com');
});

it('deletes a recipient', function () {
    $recipient = Recipient::create(['email' => 'delete@example.com', 'enabled' => true]);

    $response = $this->deleteJson("/api/recipients/{$recipient->id}");

    $response->assertStatus(204);
    expect(Recipient::count())->toBe(0);
});
