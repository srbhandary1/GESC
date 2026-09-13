<?php

declare(strict_types=1);

return [
    /*
     | Direct soft-launch institute contact for the 1:1 destination. Empty
     | values hide the corresponding link. Do not invent a number in git.
     */
    'institute_contact' => [
        'phone' => env('TUITION_CONTACT_PHONE', ''),
        'whatsapp' => env('TUITION_CONTACT_WHATSAPP', ''),
        'email' => env('TUITION_CONTACT_EMAIL', ''),
    ],
];
