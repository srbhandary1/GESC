<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Support\LessonDrillStrictTurnResponder;
use PHPUnit\Framework\TestCase;

final class LessonDrillStrictTurnResponderTest extends TestCase
{
    public function test_off_topic_try_retries_the_current_pattern(): void
    {
        $responder = new LessonDrillStrictTurnResponder;
        $lessonDrill = [
            'part_id' => 'introduction',
            'accepted_pattern_count' => 0,
            'target_patterns' => [
                'I can + base verb.',
                'I cannot + base verb.',
            ],
            'fallback_turns' => [],
        ];

        $this->assertTrue($responder->applies($lessonDrill));

        $result = $responder->respond(
            transcript: 'Tell me about your weekend.',
            lessonDrill: $lessonDrill,
            uiLocale: 'kn-IN',
        );

        $this->assertSame(
            LessonDrillStrictTurnResponder::INTENT_PATTERN_RETRY,
            $result->understoodIntent,
        );
        $this->assertStringContainsString(
            'I can drink water',
            $result->assistantReply,
        );
        $this->assertTrue($result->priorityCorrection?->retryRecommended);
    }

    public function test_matching_try_advances_to_the_next_pattern(): void
    {
        $responder = new LessonDrillStrictTurnResponder;
        $lessonDrill = [
            'part_id' => 'introduction',
            'accepted_pattern_count' => 0,
            'target_patterns' => [
                'I can + base verb.',
                'I cannot + base verb.',
            ],
            'fallback_turns' => [],
        ];

        $result = $responder->respond(
            transcript: 'I can drink water.',
            lessonDrill: $lessonDrill,
            uiLocale: 'kn-IN',
        );

        $this->assertSame(
            LessonDrillStrictTurnResponder::INTENT_PATTERN_ACCEPTED,
            $result->understoodIntent,
        );
        $this->assertStringContainsString(
            'I cannot drink water',
            $result->assistantReply,
        );
        $this->assertFalse($result->priorityCorrection?->needed);
    }
}
