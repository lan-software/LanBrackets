<?php

return [
    'enabled' => env('LANCORE_ENABLED', false),
    'base_url' => env('LANCORE_BASE_URL', 'http://lancore.lan'),
    'internal_url' => env('LANCORE_INTERNAL_URL') ?? env('LANCORE_BASE_URL', 'http://lancore.lan'),
    'token' => env('LANCORE_TOKEN'),
    'app_slug' => env('LANCORE_APP_SLUG', 'lanbrackets'),
    'callback_url' => env('LANCORE_CALLBACK_URL', env('APP_URL').'/auth/callback'),
    'roles_webhook_secret' => env('LANCORE_ROLES_WEBHOOK_SECRET'),
    'timeout' => (int) env('LANCORE_TIMEOUT', 5),
    'retries' => (int) env('LANCORE_RETRIES', 2),
    'retry_delay' => (int) env('LANCORE_RETRY_DELAY', 100),
];
