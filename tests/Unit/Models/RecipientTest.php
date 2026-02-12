<?php

use App\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create an email recipient', function () {
    $recipient = Recipient::create([
        'type' => 'email',
        'value' => 'test@example.com',
        'enabled' => true,
    ]);

    expect($recipient->type)->toBe('email')
        ->and($recipient->value)->toBe('test@example.com')
        ->and($recipient->enabled)->toBeTrue();
});

it('can create a phone recipient', function () {
    $recipient = Recipient::create([
        'type' => 'phone',
        'value' => '+15551234567',
        'enabled' => true,
    ]);

    expect($recipient->type)->toBe('phone')
        ->and($recipient->value)->toBe('+15551234567');
});

it('scopes to enabled recipients', function () {
    Recipient::create(['type' => 'email', 'value' => 'a@test.com', 'enabled' => true]);
    Recipient::create(['type' => 'email', 'value' => 'b@test.com', 'enabled' => false]);
    Recipient::create(['type' => 'phone', 'value' => '+1555', 'enabled' => true]);

    expect(Recipient::enabled()->count())->toBe(2);
});
