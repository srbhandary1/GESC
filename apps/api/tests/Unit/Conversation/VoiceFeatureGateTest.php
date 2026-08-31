<?php

declare(strict_types=1);

namespace Tests\Unit\Conversation;

use App\Conversation\Voice\VoiceFeatureGate;
use App\Conversation\Voice\VoiceOperationRejected;
use Tests\TestCase;

final class VoiceFeatureGateTest extends TestCase
{
    public function test_soft_launch_allows_allowlisted_organization(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'soft-launch');
        config()->set('voice.upload_enabled', true);
        config()->set('voice.allowed_organization_ids', [
            '01KYS97G7MX374P303XJHDXT72',
        ]);

        $this->app->make(VoiceFeatureGate::class)
            ->requireEnabled('01KYS97G7MX374P303XJHDXT72');

        $this->addToAssertionCount(1);
    }

    public function test_soft_launch_rejects_unknown_organization(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'soft-launch');
        config()->set('voice.upload_enabled', true);
        config()->set('voice.allowed_organization_ids', [
            '01KYS97G7MX374P303XJHDXT72',
        ]);

        $this->expectException(VoiceOperationRejected::class);
        $this->app->make(VoiceFeatureGate::class)
            ->requireEnabled('01KYS97G7MX374P303XJHDXT99');
    }

    public function test_production_allows_allowlisted_organization_when_upload_is_enabled(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set('voice.upload_enabled', true);
        config()->set('voice.allowed_organization_ids', [
            '01KYS97G7MX374P303XJHDXT72',
        ]);

        $this->app->make(VoiceFeatureGate::class)
            ->requireEnabled('01KYS97G7MX374P303XJHDXT72');

        $this->addToAssertionCount(1);
    }

    public function test_production_rejects_unknown_organization(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set('voice.upload_enabled', true);
        config()->set('voice.allowed_organization_ids', [
            '01KYS97G7MX374P303XJHDXT72',
        ]);

        $this->expectException(VoiceOperationRejected::class);
        $this->app->make(VoiceFeatureGate::class)
            ->requireEnabled('01KYS97G7MX374P303XJHDXT99');
    }
}
