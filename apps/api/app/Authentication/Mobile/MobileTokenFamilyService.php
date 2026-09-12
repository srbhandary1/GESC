<?php

declare(strict_types=1);

namespace App\Authentication\Mobile;

use App\Audit\AuditEventRecorder;
use App\Authentication\AuthenticationSecretDigester;
use App\Models\Membership;
use App\Models\MobileRefreshToken;
use App\Models\MobileTokenFamily;
use App\Models\Organization;
use App\Tenancy\PlatformOperationAuthorizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class MobileTokenFamilyService
{
    public function __construct(
        private AuthenticationSecretDigester $secrets,
        private OAuthAccessTokenIdentifier $accessTokenIdentifiers,
        private AuditEventRecorder $audit,
        private PlatformOperationAuthorizer $platformOperations,
    ) {}

    public function lockRefreshToken(string $refreshToken): ?MobileRefreshToken
    {
        $digest = $this->secrets->digest('oauth-refresh-token', $refreshToken);

        /** @var MobileRefreshToken|null */
        return MobileRefreshToken::query()
            ->where('token_digest', $digest)
            ->lockForUpdate()
            ->first();
    }

    public function activeForAccessToken(
        string $accessTokenId,
        string $userId,
    ): ?MobileTokenFamily {
        return DB::transaction(function () use ($accessTokenId, $userId): ?MobileTokenFamily {
            $accessToken = DB::table('oauth_access_tokens')
                ->where('id', $accessTokenId)
                ->where('user_id', $userId)
                ->where('revoked', false)
                ->where('expires_at', '>', Date::now())
                ->lockForUpdate()
                ->first(['id']);

            if ($accessToken === null) {
                return null;
            }

            /** @var MobileRefreshToken|null $refreshToken */
            $refreshToken = MobileRefreshToken::query()
                ->where('access_token_id', $accessTokenId)
                ->lockForUpdate()
                ->first();

            if ($refreshToken === null) {
                return null;
            }

            /** @var MobileTokenFamily|null $family */
            $family = MobileTokenFamily::query()
                ->whereKey($refreshToken->family_id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($family === null || ! $this->isActive($family)) {
                return null;
            }

            return $family;
        });
    }

    public function selectMembership(
        string $familyId,
        string $userId,
        string $membershipId,
    ): ?MobileTokenFamily {
        return DB::transaction(function () use (
            $familyId,
            $userId,
            $membershipId,
        ): ?MobileTokenFamily {
            $membership = DB::table('memberships')
                ->where('id', $membershipId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first(['organization_id', 'status']);

            if (
                $membership === null
                || $membership->status !== Membership::STATUS_ACTIVE
                || ! is_string($membership->organization_id)
                || ! DB::table('organizations')
                    ->where('id', $membership->organization_id)
                    ->where('status', Organization::STATUS_ACTIVE)
                    ->exists()
            ) {
                return null;
            }

            /** @var MobileTokenFamily|null $family */
            $family = MobileTokenFamily::query()
                ->whereKey($familyId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($family === null || ! $this->isActive($family)) {
                return null;
            }

            $membershipRemainsSelectable = DB::table('memberships')
                ->join(
                    'organizations',
                    'organizations.id',
                    '=',
                    'memberships.organization_id',
                )
                ->where('memberships.id', $membershipId)
                ->where('memberships.user_id', $userId)
                ->where(
                    'memberships.organization_id',
                    $membership->organization_id,
                )
                ->where('memberships.status', Membership::STATUS_ACTIVE)
                ->where('organizations.status', Organization::STATUS_ACTIVE)
                ->exists();

            if (! $membershipRemainsSelectable) {
                return null;
            }

            $family->forceFill([
                'selected_organization_id' => $membership->organization_id,
                'selected_membership_id' => $membershipId,
            ])->save();

            return $family;
        });
    }

    public function clearSelectedMembership(
        string $familyId,
        string $userId,
    ): void {
        MobileTokenFamily::query()
            ->whereKey($familyId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'selected_organization_id' => null,
                'selected_membership_id' => null,
            ]);
    }

    public function validateRefresh(
        MobileRefreshToken $refreshToken,
        string $installationId,
    ): bool {
        /** @var MobileTokenFamily|null $family */
        $family = MobileTokenFamily::query()
            ->whereKey($refreshToken->family_id)
            ->lockForUpdate()
            ->first();

        if ($family === null || $family->revoked_at !== null) {
            return false;
        }

        if ($refreshToken->rotated_at !== null) {
            $this->revoke($family, 'refresh_token_reuse');

            return false;
        }

        $installationDigest = $this->secrets->digest(
            'mobile-installation',
            $installationId,
        );

        if (! hash_equals($family->installation_digest, $installationDigest)) {
            $this->revoke($family, 'installation_mismatch');

            return false;
        }

        $now = Date::now();
        $inactiveAt = $family->last_used_at->copy()->addDays(
            (int) config('authentication.mobile.family_inactivity_days', 30),
        );

        if (
            $inactiveAt->lessThanOrEqualTo($now)
            || $family->absolute_expires_at->lessThanOrEqualTo($now)
            || $refreshToken->expires_at->lessThanOrEqualTo($now)
        ) {
            $this->revoke($family, 'expired');

            return false;
        }

        return true;
    }

    public function trackIssuedTokens(
        string $grantType,
        string $installationId,
        string $accessToken,
        string $refreshToken,
        ?MobileRefreshToken $previousRefreshToken,
    ): void {
        $accessTokenId = $this->accessTokenIdentifiers->fromEncodedToken($accessToken);
        $accessTokenRecord = DB::table('oauth_access_tokens')
            ->where('id', $accessTokenId)
            ->lockForUpdate()
            ->first(['id', 'user_id', 'client_id']);

        if (
            $accessTokenRecord === null
            || ! is_string($accessTokenRecord->user_id)
            || ! is_string($accessTokenRecord->client_id)
        ) {
            throw new RuntimeException('The issued OAuth access-token record is unavailable.');
        }

        $passportRefreshToken = DB::table('oauth_refresh_tokens')
            ->where('access_token_id', $accessTokenId)
            ->lockForUpdate()
            ->first(['id', 'expires_at']);

        if (
            $passportRefreshToken === null
            || ! is_string($passportRefreshToken->id)
            || ! is_string($passportRefreshToken->expires_at)
        ) {
            throw new RuntimeException('The issued OAuth refresh-token record is unavailable.');
        }

        if (in_array($grantType, ['authorization_code', 'password_sign_in', 'federated_sign_in'], true)) {
            /** @var MobileTokenFamily $family */
            $family = MobileTokenFamily::query()->forceCreate([
                'user_id' => $accessTokenRecord->user_id,
                'client_id' => $accessTokenRecord->client_id,
                'installation_digest' => $this->secrets->digest(
                    'mobile-installation',
                    $installationId,
                ),
                'last_used_at' => Date::now(),
                'absolute_expires_at' => Date::now()->addDays(
                    (int) config('authentication.mobile.family_absolute_days', 90),
                ),
            ]);
        } else {
            if ($previousRefreshToken === null) {
                throw new RuntimeException('The prior refresh token is unavailable.');
            }

            /** @var MobileTokenFamily|null $family */
            $family = MobileTokenFamily::query()
                ->whereKey($previousRefreshToken->family_id)
                ->lockForUpdate()
                ->first();

            if (
                $family === null
                || $family->revoked_at !== null
                || ! hash_equals($family->user_id, $accessTokenRecord->user_id)
                || ! hash_equals($family->client_id, $accessTokenRecord->client_id)
            ) {
                throw new RuntimeException('The issued OAuth token does not match its family.');
            }

            $previousRefreshToken->forceFill(['rotated_at' => Date::now()])->save();
            $family->forceFill(['last_used_at' => Date::now()])->save();
        }

        MobileRefreshToken::query()->forceCreate([
            'family_id' => (string) $family->getKey(),
            'token_digest' => $this->secrets->digest('oauth-refresh-token', $refreshToken),
            'passport_refresh_token_id' => $passportRefreshToken->id,
            'access_token_id' => $accessTokenId,
            'expires_at' => $passportRefreshToken->expires_at,
        ]);
    }

    public function revokeByAccessToken(
        string $accessTokenId,
        string $installationId,
        string $reason = 'mobile_logout',
    ): void {
        DB::transaction(function () use ($accessTokenId, $installationId, $reason): void {
            /** @var MobileRefreshToken|null $refreshToken */
            $refreshToken = MobileRefreshToken::query()
                ->where('access_token_id', $accessTokenId)
                ->lockForUpdate()
                ->first();

            if ($refreshToken === null) {
                return;
            }

            /** @var MobileTokenFamily|null $family */
            $family = MobileTokenFamily::query()
                ->whereKey($refreshToken->family_id)
                ->lockForUpdate()
                ->first();

            if ($family === null || $family->revoked_at !== null) {
                return;
            }

            $matches = hash_equals(
                $family->installation_digest,
                $this->secrets->digest('mobile-installation', $installationId),
            );

            $this->revoke($family, $matches ? $reason : 'installation_mismatch');
        });
    }

    public function revoke(MobileTokenFamily $family, string $reason): void
    {
        if ($family->revoked_at !== null) {
            return;
        }

        $action = match ($reason) {
            'refresh_token_reuse' => 'authentication.mobile_credential.reuse_detected',
            'installation_mismatch' => 'authentication.mobile_installation.mismatch',
            default => 'authentication.mobile_credential.revoked',
        };
        $authorization = $this->platformOperations
            ->authorizeMobileCredentialAudit(
                action: $action,
                reasonKey: $reason,
                familyId: (string) $family->getKey(),
                actorUserId: $family->user_id,
            );

        $family->forceFill([
            'revoked_at' => Date::now(),
            'revocation_reason' => $reason,
        ])->save();

        $refreshTokens = MobileRefreshToken::query()
            ->where('family_id', (string) $family->getKey())
            ->get(['passport_refresh_token_id', 'access_token_id']);

        DB::table('oauth_refresh_tokens')
            ->whereIn('id', $refreshTokens->pluck('passport_refresh_token_id')->all())
            ->update(['revoked' => true]);

        DB::table('oauth_access_tokens')
            ->whereIn('id', $refreshTokens->pluck('access_token_id')->all())
            ->update(['revoked' => true]);

        $this->audit->recordPlatform($authorization);
    }

    public function revokeAllForUser(string $userId, string $reason): void
    {
        DB::transaction(function () use ($userId, $reason): void {
            /** @var Collection<int, MobileTokenFamily> $families */
            $families = MobileTokenFamily::query()
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();

            foreach ($families as $family) {
                $this->revoke($family, $reason);
            }
        });
    }

    private function isActive(MobileTokenFamily $family): bool
    {
        if ($family->revoked_at !== null) {
            return false;
        }

        $now = Date::now();
        $inactiveAt = $family->last_used_at->copy()->addDays(
            (int) config('authentication.mobile.family_inactivity_days', 30),
        );

        if (
            $inactiveAt->greaterThan($now)
            && $family->absolute_expires_at->greaterThan($now)
        ) {
            return true;
        }

        $this->revoke($family, 'expired');

        return false;
    }
}
