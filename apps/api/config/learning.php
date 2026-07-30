<?php

declare(strict_types=1);

return [
    'goal_keys' => [
        'everyday-conversation',
        'workplace-confidence',
        'travel-confidence',
    ],

    /*
     * PR-006 stores only a client-side capture receipt. Raw audio is neither
     * uploaded nor retained, and the receipt never awards spoken mastery.
     */
    'capture' => [
        'mime_types' => [
            'audio/webm',
            'audio/webm;codecs=opus',
            'audio/ogg',
            'audio/ogg;codecs=opus',
            'audio/mp4',
        ],
        'minimum_bytes' => 128,
        'maximum_bytes' => 10_000_000,
        'minimum_duration_ms' => 250,
        'maximum_duration_ms' => 120_000,
    ],

    /*
     * Private model-audio delivery is bounded before any storage object is
     * opened. PR-006 missions are short daily exercises, so a 10 MB ceiling
     * fails closed on unexpectedly large or corrupted metadata.
     */
    'model_audio_maximum_bytes' => 10_000_000,
    'model_audio_disk' => env(
        'LEARNING_MODEL_AUDIO_DISK',
        env('FILESYSTEM_DISK', 'local'),
    ),
    'model_audio_mime_types' => [
        'audio/mpeg',
        'audio/mp4',
        'audio/ogg',
        'audio/wav',
        'audio/webm',
    ],

    'default_rule_version' => 'daily-speaking-v1',
];
