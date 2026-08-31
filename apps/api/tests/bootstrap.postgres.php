<?php

declare(strict_types=1);

/**
 * PHPUnit applies &lt;env force="true"&gt; to putenv/$_ENV only. PHP also copies
 * the process environment into $_SERVER, and Laravel's Env repository can read
 * $_SERVER first. If the developer shell exported live DB_* values, integration
 * tests would otherwise migrate the live database.
 */
$forced = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '5432',
    'DB_DATABASE' => 'global_english_test',
    'DB_USERNAME' => 'global_english_test',
    'DB_PASSWORD' => 'global_english_test',
    'DB_URL' => '',
    'DATABASE_URL' => '',
    'DB_SSLMODE' => 'disable',
    'CONVERSATION_PROVIDER_DRIVER' => 'fake',
    'CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED' => 'false',
    'CONVERSATION_GUIDANCE_TTS_LIVE_REQUESTS_ENABLED' => 'false',
    'VOICE_UPLOAD_ENABLED' => 'true',
    'VOICE_PROVIDER_DRIVER' => 'deterministic-test',
    'VOICE_ALLOWED_ORGANIZATION_IDS' => '',
    'SANCTUM_STATEFUL_DOMAINS' => 'localhost,localhost:3000,localhost:5174,127.0.0.1,127.0.0.1:5174,127.0.0.1:8000,::1',
];

foreach ($forced as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
