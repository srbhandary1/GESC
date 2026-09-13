<?php

declare(strict_types=1);

return [
    'content_emergency' => [
        /*
         * Platform emergency authority is deny-by-default. Production
         * operations must name stable active user identifiers explicitly;
         * organization membership never supplies this capability.
         */
        'actor_user_ids' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env(
                'PLATFORM_CONTENT_EMERGENCY_ACTOR_USER_IDS',
                '',
            )),
        ))),
    ],
    'speech_economics' => [
        /*
         * Commercial rate cards and invoices are platform-only and
         * deny-by-default. Organization membership never supplies this
         * capability.
         */
        'commercial_actor_user_ids' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env(
                'PLATFORM_SPEECH_ECONOMICS_COMMERCIAL_ACTOR_USER_IDS',
                '',
            )),
        ))),
    ],
    'account_recovery' => [
        'manager_user_ids' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env(
                'PLATFORM_ACCOUNT_RECOVERY_MANAGER_USER_IDS',
                '',
            )),
        ))),
    ],
];
