<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    */
    'version' => env('PIPOWER_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | NUT Configuration
    |--------------------------------------------------------------------------
    */
    'nut' => [
        'host' => env('NUT_HOST', 'localhost'),
        'port' => env('NUT_PORT', 3493),
        'ups_name' => env('NUT_UPS_NAME', 'ups'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'debounce_seconds' => env('NOTIFICATION_DEBOUNCE', 30),
        'battery_low_threshold' => env('BATTERY_LOW_THRESHOLD', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMTP Defaults (baked into image)
    |--------------------------------------------------------------------------
    */
    'smtp' => [
        'host' => env('MAIL_HOST', ''),
        'port' => env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'from_address' => env('MAIL_FROM_ADDRESS', 'pipower@example.com'),
        'from_name' => env('MAIL_FROM_NAME', 'UPS Monitor'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plivo Defaults (baked into image)
    |--------------------------------------------------------------------------
    */
    'plivo' => [
        'auth_id' => env('PLIVO_AUTH_ID', ''),
        'auth_token' => env('PLIVO_AUTH_TOKEN', ''),
        'from_number' => env('PLIVO_FROM_NUMBER', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Recipients (seeded on fresh install)
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'recipients' => env('DEFAULT_RECIPIENTS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Polling Interval
    |--------------------------------------------------------------------------
    */
    'poll_interval_seconds' => env('POLL_INTERVAL', 5),
];
