<?php

declare(strict_types=1);

return [
    'provider_driver' => env(
        'CONVERSATION_PROVIDER_DRIVER',
        'disabled',
    ),
    'maximum_provider_attempts' => 2,
    'maximum_utterance_characters' => 500,
    'coaching_modes' => ['gentle', 'tutor', 'fluency'],
    'ui_locales' => [
        'kn-IN',
        'ml-IN',
        'ta-IN',
        'te-IN',
        'hi-IN',
        'en-IN',
    ],
    'prototype' => [
        'persona_key' => 'patient-friend-prototype',
        'scenario_key' => 'everyday-friend-prototype',
        'coaching_mode' => 'gentle',
        'ui_locale' => 'kn-IN',
    ],
];
