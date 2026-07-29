<?php

declare(strict_types=1);

namespace App\Authentication\Otp;

use App\Authentication\AuthenticationSecretDigester;
use App\Identity\IdentityIdentifierDigester;
use App\Models\OtpChallenge;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OtpChallengeService
{
    public function __construct(
        private IdentityIdentifierDigester $identifiers,
        private AuthenticationSecretDigester $secrets,
        private OtpCodeGenerator $codes,
        private OtpDeliveryAdapter $delivery,
    ) {}

    public function issue(string $normalizedMobileNumber): OtpChallengeIssued
    {
        $destinationDigest = $this->identifiers->digest('phone', $normalizedMobileNumber);
        $code = $this->codes->generate();
        $expiresAt = Date::now()->addMinutes((int) config('authentication.otp.ttl_minutes', 5));

        /** @var OtpChallenge $challenge */
        $challenge = DB::transaction(function () use ($destinationDigest, $code, $expiresAt): OtpChallenge {
            $this->lockDestination($destinationDigest);
            $now = Date::now();

            OtpChallenge::query()
                ->where('destination_digest', $destinationDigest)
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->update([
                    'consumed_at' => $now,
                    'superseded_at' => $now,
                    'updated_at' => $now,
                ]);

            $challengeId = (string) Str::ulid();
            $challenge = new OtpChallenge;
            $challenge->forceFill([
                'id' => $challengeId,
                'destination_digest' => $destinationDigest,
                'code_digest' => $this->secrets->digest(
                    'otp-code:'.$challengeId,
                    $code,
                ),
                'failed_attempts' => 0,
                'expires_at' => $expiresAt,
            ]);
            $challenge->save();

            return $challenge;
        });

        try {
            $this->delivery->send($normalizedMobileNumber, $code);
        } catch (Throwable) {
            $challenge->forceFill(['consumed_at' => Date::now()])->save();

            throw new OtpDeliveryUnavailable('OTP delivery is temporarily unavailable.');
        } finally {
            $code = str_repeat('0', strlen($code));
            unset($code);
        }

        return new OtpChallengeIssued(
            (string) $challenge->getKey(),
            CarbonImmutable::instance($expiresAt),
        );
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $afterVerification
     * @return TResult
     */
    public function consumeAndRun(
        string $challengeId,
        string $normalizedMobileNumber,
        string $code,
        Closure $afterVerification,
    ): mixed {
        $result = DB::transaction(function () use (
            $challengeId,
            $normalizedMobileNumber,
            $code,
            $afterVerification,
        ): array {
            /** @var OtpChallenge|null $challenge */
            $challenge = OtpChallenge::query()
                ->whereKey($challengeId)
                ->lockForUpdate()
                ->first();

            $destinationDigest = $this->identifiers->digest('phone', $normalizedMobileNumber);
            $now = Date::now();

            if (
                $challenge === null
                || ! hash_equals($challenge->destination_digest, $destinationDigest)
                || $challenge->consumed_at !== null
            ) {
                return ['verified' => false];
            }

            if ($challenge->expires_at->lessThanOrEqualTo($now)) {
                $challenge->forceFill(['consumed_at' => $now])->save();

                return ['verified' => false];
            }

            $candidateDigest = $this->secrets->digest('otp-code:'.$challengeId, $code);

            if (! hash_equals($challenge->code_digest, $candidateDigest)) {
                $failedAttempts = $challenge->failed_attempts + 1;
                $maximum = (int) config('authentication.otp.max_attempts', 5);

                $challenge->forceFill([
                    'failed_attempts' => $failedAttempts,
                    'consumed_at' => $failedAttempts >= $maximum ? $now : null,
                ])->save();

                return ['verified' => false];
            }

            $challenge->forceFill(['consumed_at' => $now])->save();

            return [
                'verified' => true,
                'value' => $afterVerification(),
            ];
        });

        if ($result['verified'] === false) {
            throw new OtpVerificationFailed('The OTP challenge could not be verified.');
        }

        return $result['value'];
    }

    private function lockDestination(string $destinationDigest): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::selectOne(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
            [$destinationDigest],
        );
    }
}
