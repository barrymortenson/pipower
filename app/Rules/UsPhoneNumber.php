<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A US phone number stored as +1 followed by exactly 10 digits, with no
 * spaces or punctuation, e.g. +18055551234. The area code cannot start
 * with 0 or 1.
 */
class UsPhoneNumber implements ValidationRule
{
    private const PATTERN = '/^\+1[2-9]\d{9}\z/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail('Enter a US phone number as +1 followed by 10 digits, e.g. +18055551234.');
        }
    }

    /**
     * Turn a loosely formatted number such as "(805) 555-1234" or
     * "+1 805 555 1234" into +18055551234. Returns null when it is not a
     * valid US number, including numbers with another country code such
     * as +44.
     */
    public static function normalize(string $value): ?string
    {
        $value = trim($value);

        if (str_starts_with($value, '+') && ! str_starts_with($value, '+1')) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        $number = "+1{$digits}";

        return preg_match(self::PATTERN, $number) ? $number : null;
    }
}
