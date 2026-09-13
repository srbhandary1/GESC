<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Support\LessonDrillStepper;
use PHPUnit\Framework\TestCase;

final class LessonDrillStepperTest extends TestCase
{
    public function test_can_formulas_become_one_model_sentence(): void
    {
        $this->assertSame(
            'I can drink water.',
            LessonDrillStepper::modelUtterance('I can + base verb.'),
        );
        $this->assertSame(
            'Can I drink water?',
            LessonDrillStepper::modelUtterance('Can I + base verb?'),
        );
        $this->assertSame(
            'Yes, you can.',
            LessonDrillStepper::modelUtterance('Yes, you can. / No, you cannot.'),
        );
        $this->assertSame(
            'I am waiting.',
            LessonDrillStepper::modelUtterance('I am + -ing.'),
        );
        $this->assertSame(
            'I cooked yesterday.',
            LessonDrillStepper::modelUtterance('I + past verb yesterday.'),
        );
    }

    public function test_introduction_opens_on_the_first_model_only(): void
    {
        $this->assertSame(
            'Listen. Say: I can drink water. Now you try.',
            LessonDrillStepper::opening('introduction', [
                'I can + base verb.',
                'I cannot + base verb.',
                'Can I + base verb?',
                'Yes, you can. / No, you cannot.',
            ]),
        );
    }

    public function test_introduction_advances_one_pattern_per_turn(): void
    {
        $patterns = [
            'I can + base verb.',
            'I cannot + base verb.',
            'Can I + base verb?',
            'Yes, you can. / No, you cannot.',
        ];

        $this->assertSame(
            'Good. Now listen. Say: I cannot drink water. Your turn.',
            LessonDrillStepper::reply('introduction', $patterns, 0),
        );
        $this->assertSame(
            'Good. Now listen. Say: Can I drink water? Your turn.',
            LessonDrillStepper::reply('introduction', $patterns, 1),
        );
        $this->assertSame(
            'Good. You practised each pattern. You can mark Introduction complete.',
            LessonDrillStepper::reply('introduction', $patterns, 3),
        );
        $this->assertStringNotContainsString(
            'Do not start a free conversation',
            LessonDrillStepper::reply('introduction', $patterns, 0),
        );
    }

    public function test_fallback_turns_replace_template_patterns(): void
    {
        $patterns = [
            'If + present, will + base verb.',
            'If I + present, I will + base verb.',
        ];
        $fallbacks = [
            'If it rains, we will stay inside.',
            'If I finish early, I will call you.',
        ];

        $this->assertSame(
            'Listen. Say: If it rains, we will stay inside. Now you try.',
            LessonDrillStepper::opening('introduction', $patterns, $fallbacks),
        );
    }

    public function test_strict_gate_matches_normalized_model_sentences(): void
    {
        $this->assertTrue(
            LessonDrillStepper::utteranceMatchesModel(
                'I can drink water.',
                'I can drink water.',
            ),
        );
        $this->assertTrue(
            LessonDrillStepper::utteranceMatchesModel(
                '  i can drink water  ',
                'I can drink water.',
            ),
        );
        $this->assertFalse(
            LessonDrillStepper::utteranceMatchesModel(
                'We cannot drink water.',
                'I cannot drink water.',
            ),
        );
    }

    public function test_retry_reply_keeps_the_current_pattern(): void
    {
        $this->assertSame(
            'Good try. Listen again. Say: I cannot drink water. Your turn.',
            LessonDrillStepper::retryReply(
                'introduction',
                'I cannot drink water.',
            ),
        );
    }

    public function test_practice_asks_for_the_next_form_then_the_check(): void
    {
        $patterns = [
            'I can + base verb.',
            'I cannot + base verb.',
        ];

        $this->assertSame(
            'Practice time. I can drink water. Now say: I cannot drink water.',
            LessonDrillStepper::opening('practice', $patterns),
        );
        $this->assertSame(
            'Good. Finish the Check items on this page.',
            LessonDrillStepper::reply('practice', $patterns, 1),
        );
    }
}
