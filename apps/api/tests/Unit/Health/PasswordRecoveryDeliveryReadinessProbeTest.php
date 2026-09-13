<?php

declare(strict_types=1);

namespace Tests\Unit\Health;

use App\Authentication\Password\UnavailableFormerRecoveryContactNotifier;
use App\Authentication\Password\UnavailablePasswordRecoveryDeliveryAdapter;
use App\Health\PasswordRecoveryDeliveryReadinessProbe;
use Tests\TestCase;

final class PasswordRecoveryDeliveryReadinessProbeTest extends TestCase
{
    public function test_production_fails_closed_without_reviewed_delivery(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app->instance('env', 'production');

        try {
            $probe = new PasswordRecoveryDeliveryReadinessProbe(
                new UnavailablePasswordRecoveryDeliveryAdapter,
                new UnavailableFormerRecoveryContactNotifier,
            );

            self::assertSame('password_recovery_delivery', $probe->name());
            self::assertFalse($probe->isReady());
        } finally {
            $this->app->instance('env', $originalEnvironment);
        }
    }
}
