<?php

declare(strict_types=1);

return [
    'bundle_schema' => 'global-english.offline-mission-bundle.v1',
    'level_bundle_schema' => 'global-english.offline-level-bundle.v1',
    'completion_schema' => 'global-english.offline-completion.v1',
    'level_checkpoint_completion_schema' => 'global-english.offline-level-checkpoint-completion.v1',
    'lease_minutes' => (int) env('OFFLINE_LEASE_MINUTES', 1440),
    'bundle_retention_days' => (int) env(
        'OFFLINE_BUNDLE_RETENTION_DAYS',
        30,
    ),
    'pending_command_active_days' => (int) env(
        'OFFLINE_PENDING_COMMAND_ACTIVE_DAYS',
        30,
    ),
    'signing_key' => env('OFFLINE_BUNDLE_SIGNING_KEY'),
];
