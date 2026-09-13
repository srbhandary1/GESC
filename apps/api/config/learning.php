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

    /*
     * Lesson-video delivery (ADR-0013) is separate from model audio. Legacy
     * level MP4 feedstock can exceed the 10 MB audio ceiling; bound and verify
     * before any private object is streamed to a learner.
     *
     * Staging/prod-like: set LEARNING_LESSON_VIDEO_DISK=lesson_videos (private
     * S3 prefix). See docs/LESSON_VIDEO_STAGING_HARDENING.md. This is not a
     * production content-approval or Play authorization.
     */
    'lesson_video_maximum_bytes' => 20_000_000,
    'lesson_video_disk' => env(
        'LEARNING_LESSON_VIDEO_DISK',
        env(
            'LEARNING_MODEL_AUDIO_DISK',
            env('FILESYSTEM_DISK', 'local'),
        ),
    ),
    'lesson_video_mime_types' => [
        'video/mp4',
    ],
    'lesson_video_usage_key' => 'lesson_video',

    'default_rule_version' => 'daily-speaking-v1',
];
