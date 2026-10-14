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
    'sequential_unlock' => [
        'enabled' => true,
        'rule_key' => 'level_unlock_initial_two_v2',
        'initial_open_levels' => [1, 2],
    ],
    'level_checkpoint' => [
        'enabled' => true,
        'rule_key' => 'level_checkpoint_v1',
        'max_attempts' => 3,
        'pass_threshold' => 80,
    ],
    'review_due_blocking' => [
        'enabled' => true,
        'rule_key' => 'review_due_blocking_v1',
    ],
    'speaking_evidence' => [
        'enabled' => true,
        'rule_key' => 'level_speaking_evidence_v1',
    ],
    'conversation_bindings' => [
        'introduction' => [
            'prototype' => 'skill_focus',
            'coaching_mode' => 'tutor',
            'lesson_drill' => true,
        ],
        'practice' => [
            'prototype' => 'skill_focus',
            'coaching_mode' => 'tutor',
            'lesson_drill' => true,
        ],
        'conversation' => [
            'prototype' => 'friend',
            'coaching_mode' => 'gentle',
        ],
        'interaction' => [
            'prototype' => 'friend',
            'coaching_mode' => 'gentle',
        ],
    ],
];
