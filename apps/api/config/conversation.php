<?php

declare(strict_types=1);

return [
    'provider_driver' => env(
        'CONVERSATION_PROVIDER_DRIVER',
        'disabled',
    ),
    'maximum_provider_attempts' => 2,
    'maximum_utterance_characters' => 500,
    'prototype' => [
        'persona_key' => 'patient-friend-prototype',
        'scenario_key' => 'everyday-friend-prototype',
        'coaching_mode' => 'gentle',
    ],
];
