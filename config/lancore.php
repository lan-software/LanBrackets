<?php

return [
    'app_slug' => env('LANCORE_APP_SLUG', 'lanbrackets'),

    /*
    |--------------------------------------------------------------------------
    | Legacy Signed-Payload Auth Secret
    |--------------------------------------------------------------------------
    |
    | HMAC-SHA256 shared secret used by AuthController::handleSignedCallback to
    | verify the legacy ?payload=&signature= redirect form. This is independent
    | of the modern OAuth-style flow handled via lancore-client's exchangeCode().
    | Empty / unset disables the legacy path.
    |
    */
    'legacy_auth_secret' => env('LANCORE_AUTH_SECRET'),
];
