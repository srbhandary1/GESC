<?php

declare(strict_types=1);

namespace App\Operator\OrganizationAdministration;

use App\Authentication\AuthenticationSecretDigester;
use App\Authentication\Sessions\AuthenticationSessionCleanupWorker;
use App\Curriculum\CanonicalJson;
use App\Models\AuthenticationSession;
use App\Operator\OrganizationAdministration\Exceptions\OrganizationAdministrationRejected;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class MembershipSelectedCredentialService
{
    public function __construct(
        private AuthenticationSecretDigester $secrets,
        private AuthenticationSessionCleanupWorker $cleanup,
    ) {}

    /**
     * @return array{
     *     web: Collection<int, stdClass>,
     *     mobile: Collection<int, stdClass>,
     *     revocable_web: Collection<int, stdClass>,
     *     revocable_mobile: Collection<int, stdClass>,
     *     active_web_count: int,
     *     active_mobile_count: int,
     *     latest_activity_at: string|null,
     *     fingerprint: string
     * }
     */
    public function snapshot(
        string $organizationId,
        string $membershipId,
        string $membershipStatus,
        int $membershipVersion,
        bool $lock = false,
    ): array {
        $now = now();
        $webQuery = DB::table('authentication_sessions')
            ->where('selected_organization_id', $organizationId)
            ->where('selected_membership_id', $membershipId)
            ->whereNull('revoked_at')
            ->orderBy('id');
        $mobileQuery = DB::table('mobile_token_families')
            ->where('selected_organization_id', $organizationId)
            ->where('selected_membership_id', $membershipId)
            ->whereNull('revoked_at')
            ->orderBy('id');

        if ($lock) {
            $webQuery->lockForUpdate();
            $mobileQuery->lockForUpdate();
        }

        $revocableWeb = $webQuery->get([
            'id',
            'last_seen_at',
            'idle_expires_at',
            'absolute_expires_at',
        ]);
        $revocableMobile = $mobileQuery->get([
            'id',
            'last_used_at',
            'absolute_expires_at',
        ]);
        $web = $revocableWeb->filter(fn (stdClass $row): bool => (
            $this->carbon($row, 'idle_expires_at')->greaterThan($now)
            && $this->carbon($row, 'absolute_expires_at')->greaterThan($now)
        ))->values();
        $mobileInactivityDays = (int) config(
            'authentication.mobile.family_inactivity_days',
            30,
        );
        $mobile = $revocableMobile->filter(fn (stdClass $row): bool => (
            $this->carbon($row, 'absolute_expires_at')->greaterThan($now)
            && $this->carbon($row, 'last_used_at')
                ->addDays($mobileInactivityDays)
                ->greaterThan($now)
        ))->values();
        $activities = [
            ...$web->map(fn (stdClass $row): CarbonImmutable => $this->carbon($row, 'last_seen_at'))->all(),
            ...$mobile->map(fn (stdClass $row): CarbonImmutable => $this->carbon($row, 'last_used_at'))->all(),
        ];
        usort(
            $activities,
            static fn (CarbonImmutable $left, CarbonImmutable $right): int => (
                $right->getTimestamp() <=> $left->getTimestamp()
            ),
        );
        $canonical = CanonicalJson::encode([
            'membership_id' => $membershipId,
            'membership_status' => $membershipStatus,
            'membership_version' => $membershipVersion,
            'mobile_ids' => $this->ids($mobile),
            'web_ids' => $this->ids($web),
        ]);

        return [
            'web' => $web,
            'mobile' => $mobile,
            'revocable_web' => $revocableWeb,
            'revocable_mobile' => $revocableMobile,
            'active_web_count' => $web->count(),
            'active_mobile_count' => $mobile->count(),
            'latest_activity_at' => isset($activities[0])
                ? $activities[0]->toIso8601String()
                : null,
            'fingerprint' => $this->secrets->digest(
                'membership-session-state',
                $canonical,
            ),
        ];
    }

    /**
     * @param  Collection<int, stdClass>  $web
     * @param  Collection<int, stdClass>  $mobile
     * @return array{web: int, mobile: int}
     */
    public function revoke(
        Collection $web,
        Collection $mobile,
        string $reason,
        CarbonInterface $revokedAt,
    ): array {
        $webIds = $this->ids($web);
        $mobileIds = $this->ids($mobile);

        if ($webIds !== []) {
            DB::table('authentication_sessions')
                ->whereIn('id', $webIds)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $revokedAt,
                    'revocation_reason' => $reason,
                    'updated_at' => $revokedAt,
                ]);

            /** @var Collection<int, AuthenticationSession> $sessions */
            $sessions = AuthenticationSession::query()
                ->whereIn('id', $webIds)
                ->orderBy('id')
                ->get();

            foreach ($sessions as $session) {
                $this->cleanup->enqueue($session);
            }
        }

        if ($mobileIds !== []) {
            DB::table('mobile_token_families')
                ->whereIn('id', $mobileIds)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $revokedAt,
                    'revocation_reason' => $reason,
                    'updated_at' => $revokedAt,
                ]);
            $tokens = DB::table('mobile_refresh_tokens')
                ->whereIn('family_id', $mobileIds)
                ->lockForUpdate()
                ->get(['passport_refresh_token_id', 'access_token_id']);
            $refreshTokenIds = $tokens->pluck('passport_refresh_token_id')
                ->filter(static fn (mixed $id): bool => is_string($id))
                ->values()
                ->all();
            $accessTokenIds = $tokens->pluck('access_token_id')
                ->filter(static fn (mixed $id): bool => is_string($id))
                ->values()
                ->all();

            if ($refreshTokenIds !== []) {
                DB::table('oauth_refresh_tokens')
                    ->whereIn('id', $refreshTokenIds)
                    ->update(['revoked' => true]);
            }

            if ($accessTokenIds !== []) {
                DB::table('oauth_access_tokens')
                    ->whereIn('id', $accessTokenIds)
                    ->update(['revoked' => true]);
            }
        }

        return ['web' => count($webIds), 'mobile' => count($mobileIds)];
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<string>
     */
    private function ids(Collection $rows): array
    {
        return array_values(
            $rows->map(fn (stdClass $row): string => $this->string($row, 'id'))->all(),
        );
    }

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (! is_string($value) || $value === '') {
            throw new OrganizationAdministrationRejected;
        }

        return $value;
    }

    private function carbon(stdClass $row, string $property): CarbonImmutable
    {
        $value = $row->{$property} ?? null;

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value);
        }

        throw new OrganizationAdministrationRejected;
    }
}
