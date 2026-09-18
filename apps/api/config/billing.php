<?php

declare(strict_types=1);

return [
    'driver' => env('PAYMENT_DRIVER', 'dummy'),
    'razorpay' => [
        'api_base_url' => env('RAZORPAY_API_BASE_URL', 'https://api.razorpay.com/v1'),
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'mode' => env('RAZORPAY_MODE', 'test'),
        'timeout_seconds' => (int) env('RAZORPAY_TIMEOUT_SECONDS', 10),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],
    'live_ai_turn_seconds' => (int) env('LIVE_AI_TURN_SECONDS', 10),
    'live_ai_speech_seconds' => (int) env('LIVE_AI_SPEECH_SECONDS', 5),
];
