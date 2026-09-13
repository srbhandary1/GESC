<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Curriculum\LessonPractice\AuthoredFoundationLessons;
use App\Curriculum\LessonPractice\LessonPracticeBlueprintValidator;
use PHPUnit\Framework\TestCase;

final class LessonPracticeBlueprintValidatorTest extends TestCase
{
    public function test_authored_level_one_blueprint_passes(): void
    {
        $blueprint = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/docs/evidence/lpl-01/level1-authored-blueprint-draft.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([], (new LessonPracticeBlueprintValidator)->validate($blueprint));
    }

    public function test_authored_levels_two_to_seventeen_pass_and_are_not_templates(): void
    {
        $validator = new LessonPracticeBlueprintValidator;
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
            $blueprint = AuthoredFoundationLessons::blueprint(
                $level,
                $concept,
                $assets,
            );
            self::assertSame([], $validator->validate($blueprint), $concept);
            self::assertArrayHasKey('level_checkpoint', $blueprint);
            self::assertCount(3, $blueprint['level_checkpoint']['items']);
            self::assertSame(
                $concept.'-cp-01',
                $blueprint['level_checkpoint']['items'][0]['id'],
            );
            $encoded = json_encode($blueprint, JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('Provisional APP-0063', $encoded);
            self::assertStringNotContainsString('provisional_template_content', $encoded);
            self::assertSame('APP-0066', $blueprint['english_review_reference']);
            self::assertSame('APP-0067', $blueprint['kannada_review_reference']);
            self::assertSame(
                'APP-0066_human_english_academic_review',
                $blueprint['review']['english_academic']['authority'],
            );
            self::assertSame(
                'APP-0067_human_kannada_review',
                $blueprint['review']['kannada']['authority'],
            );
            self::assertNotContains(
                'independent_english_academic_review',
                $blueprint['publication']['blockers'],
            );
            self::assertNotContains(
                'independent_kannada_review',
                $blueprint['publication']['blockers'],
            );
            self::assertSame([], $blueprint['publication']['blockers']);
            self::assertTrue($blueprint['publication']['eligible']);
            self::assertSame('published_direct_entitled_v1', $blueprint['state']);
            self::assertTrue($blueprint['kannada_guidance']['independent_review']);
        }
    }

    public function test_invalid_weight_and_script_are_rejected(): void
    {
        $blueprint = [
            'schema' => 'global-english.lesson-practice-loop.v1',
            'lesson' => ['objective' => 'x'],
            'ordered_parts' => array_map(fn (string $id, int $order): array => ['part_id' => $id, 'order' => $order], ['introduction', 'practice', 'conversation', 'interaction'], [1, 2, 3, 4]),
            'check' => ['items' => [['weight' => 99, 'options' => ['<script>'], 'correct_option' => 0]]],
            'apply' => ['evidence_state' => 'not_recorded'],
        ];

        self::assertSame(['check_weights', 'executable_markup'], (new LessonPracticeBlueprintValidator)->validate($blueprint));
    }
}
