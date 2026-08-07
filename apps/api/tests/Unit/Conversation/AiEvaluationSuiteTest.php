<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Evaluation\AiEvaluationSuite;
use App\Conversation\Support\ConversationUiLocales;
use Tests\TestCase;

final class AiEvaluationSuiteTest extends TestCase
{
    public function test_locked_suite_hash_is_stable_and_covers_all_ui_locales(): void
    {
        $suite = new AiEvaluationSuite;
        $first = $suite->load();
        $second = $suite->load();

        $this->assertSame('friend.l1.locale.v1', $first['suite_key']);
        $this->assertSame('1', $first['suite_version']);
        $this->assertSame(
            '5bf9a508d1aeaf3a329212e60cf957cc28404fabcf0d34f4f65ebb6e99223270',
            $first['suite_hash'],
        );
        $this->assertSame($first['suite_hash'], $second['suite_hash']);
        $this->assertSame(ConversationUiLocales::all(), $first['ui_locales']);
        $this->assertCount(15, $first['cases']);

        $localesSeen = [];
        $shameLocales = [];

        foreach ($first['cases'] as $case) {
            $localesSeen[] = $case['ui_locale'];
            if ($case['expects_shame_rejection']) {
                $shameLocales[] = $case['ui_locale'];
            }
        }

        $this->assertSame(
            ConversationUiLocales::all(),
            array_values(array_unique($localesSeen)),
        );
        $this->assertSame(ConversationUiLocales::all(), $shameLocales);
    }
}
