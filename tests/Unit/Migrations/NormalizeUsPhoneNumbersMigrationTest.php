<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function normalizeUsPhoneMigration(): object
{
    return require database_path('migrations/2026_11_06_100000_normalize_us_phone_numbers.php');
}

it('rewrites recipient phones in older US formats as +1 and 10 digits', function () {
    DB::table('recipients')->insert([
        ['name' => 'A', 'phone' => '(805) 205-3000', 'enabled' => true],
        ['name' => 'B', 'phone' => '1-805-555-1234', 'enabled' => true],
        ['name' => 'C', 'phone' => '+18055559876', 'enabled' => true],
        ['name' => 'D', 'phone' => null, 'enabled' => true],
    ]);

    normalizeUsPhoneMigration()->up();

    expect(DB::table('recipients')->orderBy('id')->pluck('phone')->all())
        ->toBe(['+18052053000', '+18055551234', '+18055559876', null]);
});

it('leaves phones that are not valid US numbers unchanged and logs them', function () {
    Log::spy();

    DB::table('recipients')->insert(['name' => 'Mom', 'phone' => '+44 7911 123456', 'enabled' => true]);

    normalizeUsPhoneMigration()->up();

    expect(DB::table('recipients')->value('phone'))->toBe('+44 7911 123456');
    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message) => str_contains($message, '+44 7911 123456'));
});

it('rewrites a saved plivo from number in an older format', function () {
    Setting::set('plivo_from_number', '18052053000');

    normalizeUsPhoneMigration()->up();

    expect(Setting::get('plivo_from_number'))->toBe('+18052053000');
});

it('leaves a saved plivo from number that is not a US number unchanged', function () {
    Setting::set('plivo_from_number', '+447911123456');

    normalizeUsPhoneMigration()->up();

    expect(Setting::get('plivo_from_number'))->toBe('+447911123456');
});
