<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $value = $setting->value;

        // If value is an array with single 'value' key, unwrap it
        if (is_array($value) && count($value) === 1 && array_key_exists('value', $value)) {
            return $value['value'];
        }

        return $value;
    }

    public static function apiToken(): string
    {
        $token = static::get('api_token');

        if (! $token) {
            $token = Str::random(64);
            static::set('api_token', $token);
        }

        return $token;
    }

    public static function set(string $key, mixed $value): void
    {
        // Wrap non-array values for JSON storage
        $storedValue = is_array($value) ? $value : ['value' => $value];

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue]
        );
    }
}
