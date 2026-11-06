<?php

use App\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create an email-only recipient', function () {
    $recipient = Recipient::create(['email' => 'test@example.com', 'enabled' => true]);

    expect($recipient->email)->toBe('test@example.com')
        ->and($recipient->phone)->toBeNull()
        ->and($recipient->enabled)->toBeTrue();
});

it('can create a phone-only recipient', function () {
    $recipient = Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    expect($recipient->phone)->toBe('+15551234567')
        ->and($recipient->email)->toBeNull();
});

it('can create a recipient with both email and phone', function () {
    $recipient = Recipient::create([
        'name' => 'Barry',
        'email' => 'barry@example.com',
        'phone' => '+15551234567',
        'enabled' => true,
    ]);

    expect($recipient->email)->toBe('barry@example.com')
        ->and($recipient->phone)->toBe('+15551234567');
});

it('scopes to enabled recipients', function () {
    Recipient::create(['email' => 'a@test.com', 'enabled' => true]);
    Recipient::create(['email' => 'b@test.com', 'enabled' => false]);
    Recipient::create(['phone' => '+1555', 'enabled' => true]);

    expect(Recipient::enabled()->count())->toBe(2);
});

it('includes a recipient with both channels in the email and phone scopes', function () {
    Recipient::create(['email' => 'both@test.com', 'phone' => '+15551234567', 'enabled' => true]);
    Recipient::create(['email' => 'email@test.com', 'enabled' => true]);
    Recipient::create(['phone' => '+15559876543', 'enabled' => true]);

    expect(Recipient::emails()->pluck('email')->all())->toBe(['both@test.com', 'email@test.com'])
        ->and(Recipient::phones()->pluck('phone')->all())->toBe(['+15551234567', '+15559876543']);
});
