<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function splitRecipientMigration(): object
{
    return require database_path('migrations/2026_11_06_000000_split_recipient_value_into_email_and_phone.php');
}

it('moves existing type and value rows into email and phone columns', function () {
    $migration = splitRecipientMigration();
    $migration->down();

    DB::table('recipients')->insert([
        ['name' => 'Work', 'type' => 'email', 'value' => 'barry@example.com', 'enabled' => true],
        ['name' => 'Cell', 'type' => 'phone', 'value' => '+15551234567', 'enabled' => false],
    ]);

    $migration->up();

    $rows = DB::table('recipients')->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->email)->toBe('barry@example.com')
        ->and($rows[0]->phone)->toBeNull()
        ->and($rows[1]->email)->toBeNull()
        ->and($rows[1]->phone)->toBe('+15551234567')
        ->and((bool) $rows[1]->enabled)->toBeFalse();
});

it('splits a recipient with both channels into two rows on rollback', function () {
    DB::table('recipients')->insert([
        'name' => 'Barry', 'email' => 'barry@example.com', 'phone' => '+15551234567', 'enabled' => true,
    ]);

    splitRecipientMigration()->down();

    $rows = DB::table('recipients')->orderBy('id')->get(['name', 'type', 'value']);

    expect($rows->map(fn ($row) => (array) $row)->all())->toBe([
        ['name' => 'Barry', 'type' => 'email', 'value' => 'barry@example.com'],
        ['name' => 'Barry', 'type' => 'phone', 'value' => '+15551234567'],
    ]);
});
