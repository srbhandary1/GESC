<?php

declare(strict_types=1);

return [
    'session_part_ids' => [
        'introduction',
        'practice',
        'conversation',
        'interaction',
    ],
    'level_count' => 17,
    /**
     * Concept keys bound to level numbers once commissioned.
     * Unlisted levels remain commissioning_pending shells.
     */
    'level_concepts' => [
        1 => 'can',
        2 => 'should',
        3 => 'do_does',
        4 => 'is_are_ing',
        5 => 'did',
        6 => 'was_were_ing',
        7 => 'have_has',
        8 => 'have_been_has_been',
        9 => 'had',
        10 => 'will',
        11 => 'gonna_wanna',
        12 => 'would',
        13 => 'may',
        14 => 'if',
        15 => 'must',
        16 => 'should_have',
        17 => 'used_to',
    ],
    'conversation_bindings' => [
        'introduction' => [
            'prototype' => 'skill_focus',
            'coaching_mode' => 'tutor',
        ],
        'practice' => [
            'prototype' => 'skill_focus',
            'coaching_mode' => 'tutor',
        ],
        'conversation' => [
            'prototype' => 'skill_focus',
            'coaching_mode' => 'gentle',
        ],
        'interaction' => [
            'prototype' => 'friend',
            'coaching_mode' => 'gentle',
        ],
    ],
];
