<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\LessonDrillContext;
use App\Conversation\Orchestration\ConversationContextAllowlist;
use App\Curriculum\LessonPractice\AuthoredFoundationLessons;
use Tests\TestCase;

final class LessonDrillContextTest extends TestCase
{
    public function test_from_canonical_keeps_can_patterns_for_ask_ai_tutor_parts(): void
    {
        $canonical = [
            'lesson' => [
                'concept_key' => 'can',
                'communication_function' => 'ability_and_permission',
                'target_patterns' => [
                    'I can + base verb.',
                    'I cannot + base verb.',
                    'Can I + base verb?',
                    'Yes, you can. / No, you cannot.',
                ],
            ],
            'talk' => [
                'scenario' => 'Ask for permission at work.',
                'elicitation_goals' => ['Ask one Can I…? question.'],
            ],
        ];

        $introduction = LessonDrillContext::fromCanonical(1, 'introduction', 'can', $canonical);
        $practice = LessonDrillContext::fromCanonical(1, 'practice', 'can', $canonical);
        $conversation = LessonDrillContext::fromCanonical(1, 'conversation', 'can', $canonical);

        $this->assertNotNull($introduction);
        $this->assertNotNull($practice);
        $this->assertNull($conversation);
        $this->assertSame('can', $introduction->conceptKey);
        $this->assertSame(
            [
                'I can + base verb.',
                'I cannot + base verb.',
                'Can I + base verb?',
                'Yes, you can. / No, you cannot.',
            ],
            $introduction->targetPatterns,
        );
        $this->assertSame('Ask for permission at work.', $introduction->talkScenario);
    }

    public function test_conversation_binding_is_free_speech_not_a_tutor_drill(): void
    {
        $introduction = config('level_sessions.conversation_bindings.introduction');
        $conversation = config('level_sessions.conversation_bindings.conversation');
        $interaction = config('level_sessions.conversation_bindings.interaction');

        $this->assertIsArray($introduction);
        $this->assertIsArray($conversation);
        $this->assertIsArray($interaction);
        $this->assertTrue(($introduction['lesson_drill'] ?? false) === true);
        $this->assertSame('skill_focus', $introduction['prototype']);
        $this->assertSame('friend', $conversation['prototype']);
        $this->assertArrayNotHasKey('lesson_drill', $conversation);
        $this->assertSame('gentle', $conversation['coaching_mode']);
        $this->assertSame('friend', $interaction['prototype']);
        $this->assertArrayNotHasKey('lesson_drill', $interaction);
    }

    public function test_from_canonical_uses_should_patterns_for_level_two(): void
    {
        $drill = LessonDrillContext::fromCanonical(2, 'practice', 'should', [
            'lesson' => [
                'concept_key' => 'should',
                'communication_function' => 'advice_and_suggestion',
                'target_patterns' => [
                    'I should + base verb.',
                    'You should not + base verb.',
                    'Should I + base verb?',
                    'Yes, you should. / No, you should not.',
                ],
            ],
            'talk' => [
                'scenario' => 'Give one suggestion.',
                'elicitation_goals' => ['Give one You should… sentence.'],
            ],
        ]);

        $this->assertNotNull($drill);
        $this->assertSame('should', $drill->conceptKey);
        $this->assertSame('I should + base verb.', $drill->targetPatterns[0]);
    }

    public function test_allowlist_keeps_published_patterns_and_drops_markup(): void
    {
        $allowlist = new ConversationContextAllowlist;
        $context = $allowlist->build(
            transcript: 'I can speaking English.',
            personaStyleCode: 'patient-friend-v1',
            scenarioResponseCode: 'friend.skill-focus.past-simple.v1',
            coachingMode: 'tutor',
            uiLocale: 'kn-IN',
            promptKey: 'friend.turn.v1',
            promptVersion: 1,
            promptBodySha256: str_repeat('a', 64),
            lessonDrill: [
                'concept_key' => 'can',
                'communication_function' => 'ability_and_permission',
                'talk_scenario' => 'Ask for permission.',
                'target_patterns' => [
                    'I can + base verb.',
                    '<script>bad</script>',
                ],
                'elicitation_goals' => ['Ask one Can I…? question.'],
            ],
        );

        $this->assertSame('can', $context['lesson_drill']['concept_key']);
        $this->assertSame(['I can + base verb.'], $context['lesson_drill']['target_patterns']);
        $this->assertSame(
            ['Ask one Can I…? question.'],
            $context['lesson_drill']['elicitation_goals'],
        );
    }

    public function test_every_commissioned_level_supplies_ask_ai_tutor_patterns(): void
    {
        $levelOne = json_decode(
            (string) file_get_contents(dirname(__DIR__, 5).'/docs/evidence/lpl-01/level1-authored-blueprint-draft.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->assertIsArray($levelOne);
        $can = LessonDrillContext::fromCanonical(1, 'introduction', 'can', $levelOne);
        $this->assertNotNull($can);
        $this->assertSame(
            [
                'I can + base verb.',
                'I cannot + base verb.',
                'Can I + base verb?',
                'Yes, you can. / No, you cannot.',
            ],
            $can->targetPatterns,
        );
        $this->assertNull(LessonDrillContext::fromCanonical(1, 'interaction', 'can', $levelOne));

        $assets = [];
        foreach (['introduction', 'practice', 'conversation', 'interaction'] as $partId) {
            $assets[$partId] = [
                'media_asset_id' => '01KYS97G7MX374P303XJHDXT72',
                'metadata_revision_id' => '01KYS97G7MX374P303XJHDXT73',
                'metadata_content_hash' => str_repeat('a', 64),
            ];
        }

        $concepts = [
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
        ];

        foreach ($concepts as $level => $concept) {
            $canonical = AuthoredFoundationLessons::blueprint($level, $concept, $assets);
            $introduction = LessonDrillContext::fromCanonical(
                $level,
                'introduction',
                $concept,
                $canonical,
            );
            $practice = LessonDrillContext::fromCanonical($level, 'practice', $concept, $canonical);
            $conversation = LessonDrillContext::fromCanonical(
                $level,
                'conversation',
                $concept,
                $canonical,
            );

            $this->assertNotNull($introduction, $concept);
            $this->assertNotNull($practice, $concept);
            $this->assertNull($conversation, $concept);
            $this->assertSame($concept, $introduction->conceptKey);
            $this->assertNotSame([], $introduction->targetPatterns, $concept);
        }
    }
}
