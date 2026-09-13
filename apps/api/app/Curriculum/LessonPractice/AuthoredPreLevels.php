<?php

declare(strict_types=1);

namespace App\Curriculum\LessonPractice;

/**
 * Newly authored Pre-Level 1 (Be) and Pre-Level 2 (This / That).
 *
 * These sessions are not in the `codex/confidence-conversation` LessonPractice
 * archive. They follow the same Phase 1 foundation pattern as Levels 1–17
 * (identity / pointing, not greetings or coffee-shop fillers) so entitled
 * learners can listen, talk on-chapter, and receive L1 correction.
 */
final class AuthoredPreLevels
{
    public const string APPROVAL = 'PR6-PRELEVEL-AUTHORED';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function lessons(): array
    {
        return [
            'be' => self::be(),
            'this_that' => self::thisThat(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function be(): array
    {
        return [
            'pre_level_number' => 1,
            'concept_key' => 'be',
            'title' => 'Be',
            'objective' => 'By the end of this lesson, the learner can use I am, You are and This is to name a person or thing in a short classroom or workplace exchange.',
            'communication_function' => 'identity_and_naming',
            'target_patterns' => [
                'I am + noun / adjective.',
                'You are + noun / adjective.',
                'This is + noun.',
                'Is this + noun?',
            ],
            'misconception_codes' => ['BE_MISSING', 'THIS_IS_ARTICLE'],
            'talk' => [
                'scenario' => 'In a classroom or office, name yourself and one object on the table.',
                'elicitation_goals' => [
                    'Say one I am… sentence.',
                    'Say one This is… sentence.',
                    'Ask Is this…? and answer Yes, it is or No, it is not.',
                ],
                'fallback_turns' => [
                    'I am a student. This is my book.',
                    'Is this your pen?',
                    'Yes, it is.',
                ],
            ],
            'check' => [
                'pass_threshold' => 80,
                'items' => [
                    [
                        'id' => 'be-01',
                        'weight' => 35,
                        'prompt' => 'Choose the correct identity sentence.',
                        'options' => ['I am a teacher.', 'I are a teacher.', 'I be a teacher.'],
                        'correct_option' => 0,
                        'rationale' => 'I am is the present form of be for the speaker.',
                    ],
                    [
                        'id' => 'be-02',
                        'weight' => 30,
                        'prompt' => 'Choose the correct naming sentence.',
                        'options' => ['This is my desk.', 'This are my desk.', 'This my desk.'],
                        'correct_option' => 0,
                        'rationale' => 'This is + noun names one thing near the speaker.',
                    ],
                    [
                        'id' => 'be-03',
                        'weight' => 35,
                        'prompt' => 'Choose the question for a nearby object.',
                        'options' => ['Is this your bag?', 'This is your bag?', 'Are this your bag?'],
                        'correct_option' => 0,
                        'rationale' => 'Yes/no questions invert Is this…?',
                    ],
                ],
            ],
            'apply' => [
                'task' => 'Record a 20–30 second exchange: say who you are, name one object with This is, and ask one Is this…? question.',
                'rubric' => [
                    'Uses one accurate I am sentence',
                    'Names one object with This is',
                    'Asks one Is this…? question',
                    'Speech is understandable',
                ],
                'evidence_state' => 'not_recorded',
            ],
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ I am, You are ಮತ್ತು This is ಬಳಸಿ ವ್ಯಕ್ತಿ ಅಥವಾ ವಸ್ತುವನ್ನು ಹೆಸರಿಸಿ.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function thisThat(): array
    {
        return [
            'pre_level_number' => 2,
            'concept_key' => 'this_that',
            'title' => 'This / That',
            'objective' => 'By the end of this lesson, the learner can use this and that to point to a near or far object at work or in class and ask which one it is.',
            'communication_function' => 'pointing_and_distinguishing',
            'target_patterns' => [
                'This is + noun.',
                'That is + noun.',
                'Is this / that + noun?',
                'These are / Those are + noun.',
            ],
            'misconception_codes' => ['THIS_THAT_DISTANCE', 'THESE_THOSE_PLURAL'],
            'talk' => [
                'scenario' => 'At a shared desk, point to a near object and a far object and check which one a colleague needs.',
                'elicitation_goals' => [
                    'Name one near object with This is.',
                    'Name one far object with That is.',
                    'Ask Is that…? and answer Yes, it is or No, it is not.',
                ],
                'fallback_turns' => [
                    'This is my desk. That is your chair.',
                    'Is that the form?',
                    'No, that is the handbook.',
                ],
            ],
            'check' => [
                'pass_threshold' => 80,
                'items' => [
                    [
                        'id' => 'this_that-01',
                        'weight' => 35,
                        'prompt' => 'Choose the sentence for a nearby object.',
                        'options' => ['This is my notebook.', 'That is my notebook here.', 'These is my notebook.'],
                        'correct_option' => 0,
                        'rationale' => 'This names one thing near the speaker.',
                    ],
                    [
                        'id' => 'this_that-02',
                        'weight' => 30,
                        'prompt' => 'Choose the sentence for a farther object.',
                        'options' => ['That is the door.', 'This is the door over there.', 'Those is the door.'],
                        'correct_option' => 0,
                        'rationale' => 'That names one thing farther away.',
                    ],
                    [
                        'id' => 'this_that-03',
                        'weight' => 35,
                        'prompt' => 'Choose the question about a farther object.',
                        'options' => ['Is that your file?', 'Are that your file?', 'This that your file?'],
                        'correct_option' => 0,
                        'rationale' => 'Questions invert Is that…?',
                    ],
                ],
            ],
            'apply' => [
                'task' => 'Record a 20–30 second exchange: name one near object with this, one far object with that, and ask one Is that…? question.',
                'rubric' => [
                    'Uses this for a near object',
                    'Uses that for a farther object',
                    'Asks one Is that…? question',
                    'Speech is understandable',
                ],
                'evidence_state' => 'not_recorded',
            ],
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ this (ಹತ್ತಿರ) ಮತ್ತು that (ದೂರ) ಬಳಸಿ ವಸ್ತುಗಳನ್ನು ತೋರಿಸಿ.',
        ];
    }
}
