<?php

declare(strict_types=1);

return [
    'local_lpl_pilot_authority_bootstrap_enabled' => env(
        'LPL01_PILOT_AUTHORITY_BOOTSTRAP_ENABLED',
        false,
    ),

    /*
     * Repository approval evidence alone cannot identify a runtime account.
     * Production must bind APP-0004 to S R Bhandary's stable application user
     * ULID. An empty value keeps every self-appointment denied.
     */
    'consolidated_self_appointments' => [
        'APP-0004' => [
            'actor_user_id' => env(
                'CONTENT_GOVERNANCE_APP_0004_ACTOR_USER_ID',
                '',
            ),
            'authority_kinds' => [
                'english_academic_reviewer',
                'kannada_reviewer',
                'rights_confirmer',
            ],
            'qualification_bases' => [
                'english_academic_reviewer' => 'MA',
                'kannada_reviewer' => 'MA',
            ],
        ],
    ],
];
