<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master Toggle
    |--------------------------------------------------------------------------
    */
    'enabled' => env('ANTICHEAT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Layer Toggles
    |--------------------------------------------------------------------------
    */
    'layers' => [
        'heuristics' => env('ANTICHEAT_HEURISTICS_ENABLED', true),
        'velocity' => env('ANTICHEAT_VELOCITY_ENABLED', false),
        'app_attest' => env('ANTICHEAT_APP_ATTEST_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'max_daily_steps' => 100_000,
        'large_jump' => 30_000,
        'max_hourly_steps' => 15_000,
        'round_number_minimum' => 5_000,
        'round_number_divisor' => 1_000,
        'suspicious_night_steps' => 5_000,
        'night_hours' => [0, 1, 2, 3],
        'hourly_total_tolerance' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | App Attest
    |--------------------------------------------------------------------------
    */
    'app_attest' => [
        'team_id' => env('APPLE_TEAM_ID'),
        'bundle_id' => env('APPLE_BUNDLE_ID'),
        'production' => env('APPLE_ATTEST_PRODUCTION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'max_attempts' => 30,
        'decay_minutes' => 1,
    ],

];
