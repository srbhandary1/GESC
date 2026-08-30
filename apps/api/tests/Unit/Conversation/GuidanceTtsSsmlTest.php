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
    }
}
