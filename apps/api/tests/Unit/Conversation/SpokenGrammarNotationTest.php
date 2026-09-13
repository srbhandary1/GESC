<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Support\SpokenGrammarNotation;
use PHPUnit\Framework\TestCase;

final class SpokenGrammarNotationTest extends TestCase
{
    public function test_drill_formulas_say_and_and_or(): void
    {
        $this->assertSame(
            'Let us practise: I can and base verb. I cannot and base verb. Can I and base verb? Yes, you can. Or No, you cannot. Say one short sentence.',
            SpokenGrammarNotation::forSpeech(
                'Let us practise: I can + base verb. I cannot + base verb. Can I + base verb? Yes, you can. / No, you cannot. Say one short sentence.',
                true,
            ),
        );
    }

    public function test_free_speech_keeps_plus_and_slash(): void
    {
        $this->assertSame(
            'Two plus two is 2 + 2, not 4 / 0.',
            SpokenGrammarNotation::forSpeech('Two plus two is 2 + 2, not 4 / 0.'),
        );
    }

    public function test_leaves_ordinary_speech_unchanged(): void
    {
        $this->assertSame(
            'I can speak English.',
            SpokenGrammarNotation::forSpeech('I can speak English.', true),
        );
    }
}
