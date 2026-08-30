<?php

declare(strict_types=1);

return [
    'part_size_bytes' => (int) env('MEDIA_UPLOAD_PART_SIZE_BYTES', 1_048_576),
    'upload_expiry_hours' => 24,
    'clean_review_expiry_days' => 7,
    'quarantine_cleanup_target_minutes' => 15,
    'quarantine_cleanup_hard_minutes' => 60,
    'operational_history_days' => 365,

    'quarantine_disk' => env('MEDIA_QUARANTINE_DISK', 'media_quarantine'),
    'accepted_disks' => [
        'model_audio' => env(
            'LEARNING_MODEL_AUDIO_DISK',
            env('FILESYSTEM_DISK', 'local'),
        ),
        'lesson_audio' => env(
            'LEARNING_LESSON_AUDIO_DISK',
            env('LEARNING_MODEL_AUDIO_DISK', env('FILESYSTEM_DISK', 'local')),
        ),
        'lesson_video' => env(
            'LEARNING_LESSON_VIDEO_DISK',
            env('LEARNING_MODEL_AUDIO_DISK', env('FILESYSTEM_DISK', 'local')),
        ),
    ],

    'kinds' => [
        'model_audio' => [
            'maximum_bytes' => 10_000_000,
            'mime_types' => [
                'audio/mpeg',
                'audio/mp4',
                'audio/ogg',
                'audio/wav',
                'audio/webm',
            ],
            'minimum_duration_milliseconds' => 100,
            'maximum_duration_milliseconds' => 300_000,
        ],
        'lesson_audio' => [
            'maximum_bytes' => 20_000_000,
            'mime_types' => [
                'audio/mpeg',
                'audio/mp4',
                'audio/ogg',
                'audio/wav',
                'audio/webm',
            ],
            'minimum_duration_milliseconds' => 100,
            'maximum_duration_milliseconds' => 900_000,
        ],
        'lesson_video' => [
            'maximum_bytes' => 20_000_000,
            'mime_types' => ['video/mp4'],
            'minimum_duration_milliseconds' => 100,
            'maximum_duration_milliseconds' => 7_200_000,
        ],
    ],

    /*
     * Testing binds deterministic project fakes regardless of these values.
     * Every operational environment fails closed unless reviewed adapters are
     * selected and reachable.
     */
    'inspector_driver' => env('MEDIA_INSPECTOR_DRIVER', 'unavailable'),
    'ffprobe_binary' => env('MEDIA_FFPROBE_BINARY', '/usr/bin/ffprobe'),
    'inspector_policy_version' => 'admin-media-inspection-v1',
    'scanner_driver' => env('MEDIA_SCANNER_DRIVER', 'unavailable'),
    'scanner_policy_version' => 'admin-media-malware-v1',
    'clamav' => [
        'host' => env('MEDIA_CLAMAV_HOST', '127.0.0.1'),
        'port' => (int) env('MEDIA_CLAMAV_PORT', 3310),
        'connect_timeout_seconds' => (int) env('MEDIA_CLAMAV_CONNECT_TIMEOUT_SECONDS', 2),
        'read_timeout_seconds' => (int) env('MEDIA_CLAMAV_READ_TIMEOUT_SECONDS', 120),
    ],

    'rights_attestation' => [
        'approval_reference' => 'APP-0044',
        'evidence_sha256' => '51e8835828cd5d0d31bfcb074db356c76f73e7e28d387f0a7658fdf5a8b668ca',
    ],
];
