<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Support\ConversationUiLocales;
use App\Conversation\Support\GuidanceTtsPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class GuidanceTtsPolicyTest extends TestCase
{
    public function test_model_en_always_uses_en_in_neural_voice(): void
    {
        foreach (ConversationUiLocales::all() as $locale) {
            $this->assertSame(
                'en-IN-NeerjaNeural',
                GuidanceTtsPolicy::voiceFor(
                    GuidanceTtsPolicy::PURPOSE_MODEL_EN,
                    $locale,
                ),
            );
        }
    }

    #[DataProvider('guidanceLocales')]
    public function test_guidance_l1_maps_phase1_locales(
        string $locale,
        string $voice,
    ): void {
        $this->assertSame(
            $voice,
            GuidanceTtsPolicy::voiceFor(
                GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1,
                $locale,
            ),
        );
        $this->assertTrue(GuidanceTtsPolicy::isAllowlistedVoice($voice));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function guidanceLocales(): array
    {
        return [
            ['en-IN', 'en-IN-NeerjaNeural'],
            ['hi-IN', 'hi-IN-SwaraNeural'],
            ['kn-IN', 'kn-IN-SapnaNeural'],
            ['ml-IN', 'ml-IN-SobhanaNeural'],
            ['ta-IN', 'ta-IN-PallaviNeural'],
            ['te-IN', 'te-IN-ShrutiNeural'],
        ];
    }

    public function test_rejects_unknown_purpose_and_locale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GuidanceTtsPolicy::voiceFor('unknown', 'kn-IN');
    }

    public function test_rejects_unsupported_guidance_locale(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GuidanceTtsPolicy::voiceFor(
            GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1,
            'fr-FR',
        );
    }

    public function test_confidence_conversation_forbids_human_lesson_recordings(): void
    {
        $this->assertFalse(GuidanceTtsPolicy::allowsHumanLessonRecording());
        $this->assertTrue(GuidanceTtsPolicy::textFirstFallbackRequired());
    }
}
