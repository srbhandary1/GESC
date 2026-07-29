<?php

declare(strict_types=1);

namespace App\Identity;

use App\Models\User;
use App\Models\UserIdentity;
use App\Tenancy\Exceptions\IdentityUnavailable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class VerifiedIdentityProvisioner
{
    public function __construct(
        private IdentityIdentifierDigester $identifiers,
    ) {}

    public function provision(
        string $type,
        string $normalizedIdentifier,
        ?string $displayHint = null,
    ): VerifiedIdentityProvisioningResult {
        if ($displayHint !== null && strlen($displayHint) > 40) {
            throw new InvalidArgumentException(
                'Identity display hints are limited to 40 characters.',
            );
        }

        $identifierDigest = $this->identifiers->digest(
            $type,
            $normalizedIdentifier,
        );

        return DB::transaction(
            function () use (
                $type,
                $identifierDigest,
                $displayHint,
            ): VerifiedIdentityProvisioningResult {
                $this->lockIdentity($identifierDigest);

                $identity = UserIdentity::query()
                    ->where('type', $type)
                    ->where('identifier_digest', $identifierDigest)
                    ->lockForUpdate()
                    ->first();

                if ($identity instanceof UserIdentity) {
                    if ($identity->status !== UserIdentity::STATUS_VERIFIED) {
                        throw new IdentityUnavailable;
                    }

                    $user = User::query()
                        ->whereKey($identity->user_id)
                        ->lockForUpdate()
                        ->first();

                    if (
                        ! $user instanceof User
                        || $user->status !== User::STATUS_ACTIVE
                    ) {
                        throw new IdentityUnavailable;
                    }

                    return new VerifiedIdentityProvisioningResult(
                        user: $user,
                        identity: $identity,
                        replayed: true,
                    );
                }

                $user = User::query()->forceCreate([
                    'display_name' => null,
                    'status' => User::STATUS_ACTIVE,
                ]);
                $identity = new UserIdentity;
                $identity->forceFill([
                    'user_id' => $user->getKey(),
                    'type' => $type,
                    'identifier_digest' => $identifierDigest,
                    'display_hint' => $displayHint,
                    'status' => UserIdentity::STATUS_VERIFIED,
                    'verified_at' => now(),
                ])->save();

                return new VerifiedIdentityProvisioningResult(
                    user: $user,
                    identity: $identity,
                    replayed: false,
                );
            },
            3,
        );
    }

    private function lockIdentity(string $identifierDigest): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::selectOne(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
            [$identifierDigest],
        );
    }
}
