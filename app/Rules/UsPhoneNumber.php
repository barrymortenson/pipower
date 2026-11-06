<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A US phone number stored as +1 followed by exactly 10 digits, with no
 * spaces or punctuation, e.g. +18055551234.
 */
class UsPhoneNumber implements ValidationRule
{
    private const PATTERN = '/^\+1\d{10}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail('Enter a 10-digit phone number with no spaces or symbols.');
        }
    }

    /**
     * Turn a loosely formatted number such as "(805) 555-1234" or
     * "+1 805 555 1234" into +18055551234. Returns null when it is not a
     * 10-digit US number.
     */
    public static function normalize(string $value): ?string
    {
        $digits = preg_replace('/\D/', '', $value);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? "+1{$digits}" : null;
    }
}
