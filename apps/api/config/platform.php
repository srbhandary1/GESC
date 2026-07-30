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
];
