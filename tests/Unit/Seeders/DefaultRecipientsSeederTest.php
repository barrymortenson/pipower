<?php

use App\Models\Recipient;
use Database\Seeders\DefaultRecipientsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds email and phone recipients from config', function () {
    config(['pipower.defaults.recipients' => 'Barry <barry@example.com>, IT Team <+15551234567>']);

    (new DefaultRecipientsSeeder)->run();

    expect(Recipient::count())->toBe(2);

    $email = Recipient::where('email', 'barry@example.com')->first();
    expect($email->name)->toBe('Barry');
    expect($email->phone)->toBeNull();
    expect($email->enabled)->toBeTrue();

    $phone = Recipient::where('phone', '+15551234567')->first();
    expect($phone->name)->toBe('IT Team');
    expect($phone->email)->toBeNull();
    expect($phone->enabled)->toBeTrue();
});

it('merges an email and a phone with the same name into one recipient', function () {
    config(['pipower.defaults.recipients' => 'Barry <barry@example.com>, Barry <+15551234567>']);

    (new DefaultRecipientsSeeder)->run();

    expect(Recipient::count())->toBe(1);

    $recipient = Recipient::first();
    expect($recipient->name)->toBe('Barry');
    expect($recipient->email)->toBe('barry@example.com');
    expect($recipient->phone)->toBe('+15551234567');
});

it('keeps two emails with the same name as separate recipients', function () {
    config(['pipower.defaults.recipients' => 'Barry <work@example.com>, Barry <home@example.com>']);

    (new DefaultRecipientsSeeder)->run();

    expect(Recipient::count())->toBe(2);
});

it('seeds multiple contacts of the same type', function () {
    config(['pipower.defaults.recipients' => 'Barry <barry@example.com>, Admin <admin@example.com>, On Call <+15559876543>']);

    (new DefaultRecipientsSeeder)->run();

    expect(Recipient::count())->toBe(3);
    expect(Recipient::emails()->count())->toBe(2);
    expect(Recipient::phones()->count())->toBe(1);
});

it('skips seeding when config is empty', function () {
    config(['pipower.defaults.recipients' => '']);

    (new DefaultRecipientsSeeder)->run();

    expect(Recipient::count())->toBe(0);
});

it('does not duplicate on re-run', function () {
    config(['pipower.defaults.recipients' => 'Barry <barry@example.com>, Barry <+15551234567>, Admin <admin@example.com>']);

    (new DefaultRecipientsSeeder)->run();
    (new DefaultRecipientsSeeder)->run();

    expect(Recipient::count())->toBe(2);
});

it('handles bare values without names', function () {
    config(['pipower.defaults.recipients' => 'barry@example.com, +15551234567']);

    (new DefaultRecipientsSeeder)->run();

    expect(Recipient::count())->toBe(2);

    $email = Recipient::where('email', 'barry@example.com')->first();
    expect($email->name)->toBeNull();
    expect($email->phone)->toBeNull();

    $phone = Recipient::where('phone', '+15551234567')->first();
    expect($phone->name)->toBeNull();
    expect($phone->email)->toBeNull();
});
