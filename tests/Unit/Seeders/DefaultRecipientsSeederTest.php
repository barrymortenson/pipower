<?php

use App\Models\Recipient;
use Database\Seeders\DefaultRecipientsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds email and phone recipients from config', function () {
    config(['pipower.defaults.recipients' => 'Barry <barry@example.com>, IT Team <+15551234567>']);

    (new DefaultRecipientsSeeder)->run();

    expect(Recipient::count())->toBe(2);

    $email = Recipient::where('type', 'email')->first();
    expect($email->name)->toBe('Barry');
    expect($email->value)->toBe('barry@example.com');
    expect($email->enabled)->toBeTrue();

    $phone = Recipient::where('type', 'phone')->first();
    expect($phone->name)->toBe('IT Team');
    expect($phone->value)->toBe('+15551234567');
    expect($phone->enabled)->toBeTrue();
});

it('seeds multiple contacts of the same type', function () {
    config(['pipower.defaults.recipients' => 'Barry <barry@example.com>, Admin <admin@example.com>, On Call <+15559876543>']);

    (new DefaultRecipientsSeeder)->run();

    expect(Recipient::count())->toBe(3);
    expect(Recipient::where('type', 'email')->count())->toBe(2);
    expect(Recipient::where('type', 'phone')->count())->toBe(1);
});

it('skips seeding when config is empty', function () {
    config(['pipower.defaults.recipients' => '']);

    (new DefaultRecipientsSeeder)->run();

    expect(Recipient::count())->toBe(0);
});

it('does not duplicate on re-run', function () {
    config(['pipower.defaults.recipients' => 'Barry <barry@example.com>']);

    (new DefaultRecipientsSeeder)->run();
    (new DefaultRecipientsSeeder)->run();

    expect(Recipient::count())->toBe(1);
});

it('handles bare values without names', function () {
    config(['pipower.defaults.recipients' => 'barry@example.com, +15551234567']);

    (new DefaultRecipientsSeeder)->run();

    $email = Recipient::where('type', 'email')->first();
    expect($email->name)->toBeNull();
    expect($email->value)->toBe('barry@example.com');

    $phone = Recipient::where('type', 'phone')->first();
    expect($phone->name)->toBeNull();
    expect($phone->value)->toBe('+15551234567');
});
