<?php

declare(strict_types=1);

namespace App\Authentication\Otp;

use Illuminate\Http\Client\Factory;
use RuntimeException;

final readonly class LocalOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function __construct(private Factory $http) {}

    public function send(string $normalizedMobileNumber, string $code): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The local OTP adapter is disabled outside local and testing environments.');
        }

        $baseUrl = config('authentication.otp.local_base_url');
        $timeout = config('authentication.otp.local_timeout_seconds');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('The local OTP adapter URL is not configured.');
        }

        $this->http
            ->baseUrl($baseUrl)
            ->asJson()
            ->acceptJson()
            ->timeout(is_numeric($timeout) ? (int) $timeout : 2)
            ->post('/v1/messages', [
                'destination' => $normalizedMobileNumber,
                'template' => 'authentication_otp',
                'parameters' => [
                    'code' => $code,
                    'expires_in_minutes' => (int) config('authentication.otp.ttl_minutes', 5),
                ],
            ])
            ->throw();
    }
}
