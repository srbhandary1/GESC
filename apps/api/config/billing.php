<?php

declare(strict_types=1);

return [
    'driver' => env('PAYMENT_DRIVER', 'dummy'),
    'live_ai_turn_seconds' => (int) env('LIVE_AI_TURN_SECONDS', 10),
    'live_ai_speech_seconds' => (int) env('LIVE_AI_SPEECH_SECONDS', 5),
];
