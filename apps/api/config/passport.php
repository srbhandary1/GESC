<?php

declare(strict_types=1);

return [
    'guard' => 'web',

    // Passport package routes are disabled and registered explicitly in
    // routes/auth.php so the browser authorization journey is stateful while
    // the mobile token endpoint remains stateless.
    'middleware' => [],

    'private_key' => env('PASSPORT_PRIVATE_KEY'),
    'public_key' => env('PASSPORT_PUBLIC_KEY'),
    'connection' => env('PASSPORT_CONNECTION'),
];
