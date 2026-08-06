<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Evaluation\AiEvaluationSuite;
use Tests\TestCase;

final class AiEvaluationSuiteTest extends TestCase
{
    public function test_locked_suite_hash_is_stable(): void
    {
        $suite = new AiEvaluationSuite;
        $first = $suite->load();
        $second = $suite->load();

        $this->assertSame('friend.prompt.comparison.v1', $first['suite_key']);
        $this->assertSame($first['suite_hash'], $second['suite_hash']);
        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{64}\z/',
            $first['suite_hash'],
        );
        $this->assertNotEmpty($first['cases']);
    }
}
