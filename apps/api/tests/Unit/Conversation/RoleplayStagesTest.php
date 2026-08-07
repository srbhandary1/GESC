<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Support\RoleplayStages;
use Tests\TestCase;

final class RoleplayStagesTest extends TestCase
{
    public function test_ordered_stages_and_linear_next(): void
    {
        $this->assertSame(
            ['observe', 'rehearse', 'perform', 'reflect'],
            RoleplayStages::all(),
        );
        $this->assertTrue(RoleplayStages::isValid('observe'));
        $this->assertFalse(RoleplayStages::isValid('warmup'));
        $this->assertSame('rehearse', RoleplayStages::nextStage('observe'));
        $this->assertSame('perform', RoleplayStages::nextStage('rehearse'));
        $this->assertSame('reflect', RoleplayStages::nextStage('perform'));
        $this->assertNull(RoleplayStages::nextStage('reflect'));
        $this->assertNull(RoleplayStages::nextStage('unknown'));
    }
}
