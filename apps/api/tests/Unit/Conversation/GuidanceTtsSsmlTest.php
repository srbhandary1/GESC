<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Support\GuidanceTtsSsml;
use Tests\TestCase;

final class GuidanceTtsSsmlTest extends TestCase
{
    public function test_wraps_escaped_text_in_a_slightly_slower_neural_prosody(): void
    {
        $ssml = GuidanceTtsSsml::document(
            'en-IN',
            'en-IN-NeerjaNeural',
            'I went to the market <today>.',
        );

        $this->assertStringContainsString('xml:lang="en-IN"', $ssml);
        $this->assertStringContainsString('name="en-IN-NeerjaNeural"', $ssml);
        $this->assertStringContainsString('<prosody rate="0.92">', $ssml);
        $this->assertStringContainsString(
            'I went to the market &lt;today&gt;.',
            $ssml,
        );
        $this->assertStringNotContainsString('<today>', $ssml);
        $this->assertSame(1, substr_count($ssml, '<voice '));
    }

    public function test_routes_embedded_latin_runs_through_the_english_voice(): void
    {
        $ssml = GuidanceTtsSsml::document(
            'kn-IN',
            'kn-IN-SapnaNeural',
            "ಈ ವಾಕ್ಯದಲ್ಲಿ 'I goes daily' ತಪ್ಪಾಗಿದೆ. 'I go daily' ಎಂದು ಹೇಳಿ.",
            'en-IN-NeerjaNeural',
        );

        $this->assertStringContainsString(
            '<voice name="kn-IN-SapnaNeural"><prosody rate="0.92">ಈ ವಾಕ್ಯದಲ್ಲಿ &apos;</prosody></voice>',
            $ssml,
        );
        $this->assertStringContainsString(
            '<voice name="en-IN-NeerjaNeural"><prosody rate="0.92">I goes daily&apos; </prosody></voice>',
            $ssml,
        );
        $this->assertStringContainsString(
            '<voice name="en-IN-NeerjaNeural"><prosody rate="0.92">I go daily&apos; </prosody></voice>',
            $ssml,
        );
        $this->assertStringContainsString(
            '<voice name="kn-IN-SapnaNeural"><prosody rate="0.92">ಎಂದು ಹೇಳಿ.</prosody></voice>',
            $ssml,
        );
        $this->assertSame(5, substr_count($ssml, '<voice '));
    }

    public function test_keeps_pure_kannada_in_one_voice(): void
    {
        $ssml = GuidanceTtsSsml::document(
            'kn-IN',
            'kn-IN-SapnaNeural',
            'ಈ ವಾಕ್ಯವನ್ನು ಮತ್ತೊಮ್ಮೆ ಹೇಳಿ.',
            'en-IN-NeerjaNeural',
        );

        $this->assertSame(1, substr_count($ssml, '<voice '));
        $this->assertStringNotContainsString('en-IN-NeerjaNeural', $ssml);
    }
}
