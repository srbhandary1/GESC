<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Authentication\Otp\LocalFixedOtpCodeGenerator;
use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\SecureOtpCodeGenerator;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

final class LocalOtpDevelopmentPolicyTest extends TestCase
{
    public function test_local_environment_uses_the_configured_fake_code(): void
    {
        $originalEnvironment = $this->app->environment();

        try {
            $this->app['env'] = 'local';
            config([
                'authentication.otp.driver' => 'local',
                'authentication.otp.local_fixed_code' => '000000',
            ]);
            $this->app->forgetInstance(OtpCodeGenerator::class);

            $generator = $this->app->make(OtpCodeGenerator::class);

            self::assertInstanceOf(LocalFixedOtpCodeGenerator::class, $generator);
            self::assertSame('000000', $generator->generate());
        } finally {
            $this->app['env'] = $originalEnvironment;
            $this->app->forgetInstance(OtpCodeGenerator::class);
        }
    }

    public function test_production_ignores_a_configured_fixed_code_and_uses_secure_randomness(): void
    {
        $originalEnvironment = $this->app->environment();

        try {
            $this->app['env'] = 'production';
            config([
                'authentication.otp.driver' => 'local',
                'authentication.otp.local_fixed_code' => '000000',
            ]);
            $this->app->forgetInstance(OtpCodeGenerator::class);

            self::assertInstanceOf(
                SecureOtpCodeGenerator::class,
                $this->app->make(OtpCodeGenerator::class),
            );
        } finally {
            $this->app['env'] = $originalEnvironment;
            $this->app->forgetInstance(OtpCodeGenerator::class);
        }
    }

    public function test_fixed_generator_rejects_invalid_codes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LocalFixedOtpCodeGenerator($this->app, '12345');
    }

    public function test_fixed_generator_rejects_non_local_execution(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->expectException(RuntimeException::class);

        try {
            $this->app['env'] = 'production';
            $generator = new LocalFixedOtpCodeGenerator($this->app, '123456');

            $generator->generate();
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }
}
