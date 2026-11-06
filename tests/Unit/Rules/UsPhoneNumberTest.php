<?php

use App\Rules\UsPhoneNumber;
use Illuminate\Support\Facades\Validator;

function passesUsPhone(mixed $value): bool
{
    return Validator::make(['phone' => $value], ['phone' => [new UsPhoneNumber]])->passes();
}

it('accepts +1 followed by 10 digits', function () {
    expect(passesUsPhone('+18052053000'))->toBeTrue();
});

it('rejects numbers that are not +1 and 10 digits', function (string $value) {
    expect(passesUsPhone($value))->toBeFalse();
})->with([
    'no country code' => '8052053000',
    'nine digits' => '+1805205300',
    'eleven digits' => '+180520530001',
    'spaces' => '+1 805 205 3000',
    'dashes' => '+1805-205-3000',
    'parentheses' => '+1(805)2053000',
    'other country code' => '+448052053000',
    'letters' => 'abc',
]);

it('normalizes loosely formatted US numbers', function (string $value) {
    expect(UsPhoneNumber::normalize($value))->toBe('+18052053000');
})->with([
    '8052053000',
    '(805) 205-3000',
    '+1 805 205 3000',
    '1-805-205-3000',
    '+18052053000',
]);

it('returns null for numbers that are not 10-digit US numbers', function (string $value) {
    expect(UsPhoneNumber::normalize($value))->toBeNull();
})->with(['805205300', '+448052053000', '28052053000', 'abc']);
