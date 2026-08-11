<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use App\Audit\AuditEventRecorder;
use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Models\PasswordRecoveryChallenge;
use App\Models\User;
use App\Observability\CorrelationId;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\PlatformOperationAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class PrivilegedPasswordRecoveryService
{
    public function __construct(
        private RecoveryTokenDigester $digester,
        private PasswordRecoveryService $recovery,
        private AuthenticationSessionService $sessions,
        private MobileTokenFamilyService $mobileFamilies,
        private PlatformOperationAuthorizer $platformOperations,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function open(
        User $actor,
        string $targetUserId,
        string $reasonKey,
        string $evidenceReference,
        Request $request,
    ): array {
        $this->assertActor($actor);
        $this->assertInput($reasonKey, $evidenceReference);

        if ((string) $actor->getKey() === $targetUserId || ! Str::isUlid($targetUserId)) {
            throw new PasswordRecoveryFailed;
        }

        return DB::transaction(function () use (
            $actor,
            $evidenceReference,
            $reasonKey,
            $request,
            $targetUserId,
        ): array {
            /** @var User|null $target */
            $target = User::query()->whereKey($targetUserId)->lockForUpdate()->first();

            if (! $target instanceof User || $target->status !== User::STATUS_ACTIVE) {
                throw new PasswordRecoveryFailed;
            }

            $credentialReady = $target->username !== null
                && $target->passwordCredential()->exists();
            $credentialUnready = $target->username === null
                && ! $target->passwordCredential()->exists();
            $recoveryKind = PasswordRecoveryChallenge::PURPOSE_PASSWORD_RESET;

            if ($credentialUnready) {
                $ownedExclusion = DB::table('password_cutover_exclusions')
                    ->where('user_id', $targetUserId)
                    ->where('owner_user_id', $actor->getKey())
                    ->where('cutover_scope_key', 'global')
                    ->exists();
                $verifiedContact = DB::table('user_identities')
                    ->where('user_id', $targetUserId)
                    ->where('status', 'verified')
                    ->whereIn('type', ['phone', 'email'])
                    ->exists();

                if (! $ownedExclusion || ! $verifiedContact) {
                    throw new PasswordRecoveryFailed;
                }

                $recoveryKind = PasswordRecoveryChallenge::PURPOSE_EXCLUDED_CREDENTIAL_ESTABLISHMENT;
            } elseif (! $credentialReady) {
                throw new PasswordRecoveryFailed;
            }

            $now = Date::now();
            $caseId = (string) Str::ulid();
            DB::table('privileged_password_recovery_cases')->insert([
                'id' => $caseId,
                'target_user_id' => $targetUserId,
                'requested_by_user_id' => (string) $actor->getKey(),
                'reason_key' => $reasonKey,
                'recovery_kind' => $recoveryKind,
                'evidence_reference' => $evidenceReference,
                'evidence_digest' => $this->evidenceDigest($evidenceReference),
                'status' => 'pending_approval',
                'version' => 1,
                'requested_at' => $now,
                'not_before_at' => $now->copy()->addHours(24),
                'expires_at' => $now->copy()->addDays(8),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->evidence(
                'authentication.password_recovery.case_opened',
                'case_opened',
                $caseId,
                $actor,
                $request,
            );

            return $this->payload($caseId);
        }, 3);
    }

    /** @return array<string, mixed> */
    public function approve(
        User $actor,
        string $caseId,
        string $evidenceReference,
        Request $request,
    ): array {
        $this->assertActor($actor);

        return DB::transaction(function () use ($actor, $caseId, $evidenceReference, $request): array {
            $case = DB::table('privileged_password_recovery_cases')
                ->where('id', $caseId)
                ->lockForUpdate()
                ->first();

            if (
                ! $case instanceof \stdClass
                || ! in_array($case->status, ['pending_approval', 'waiting_delay'], true)
                || (string) $case->target_user_id === (string) $actor->getKey()
                || ! hash_equals((string) $case->evidence_digest, $this->evidenceDigest($evidenceReference))
                || Date::parse((string) $case->expires_at)->lessThanOrEqualTo(Date::now())
            ) {
                throw new PasswordRecoveryFailed;
            }

            $approvalId = (string) Str::ulid();

            try {
                DB::table('privileged_password_recovery_approvals')->insert([
                    'id' => $approvalId,
                    'case_id' => $caseId,
                    'actor_user_id' => (string) $actor->getKey(),
                    'case_version' => (int) $case->version,
                    'evidence_digest' => (string) $case->evidence_digest,
                    'approved_at' => Date::now(),
                    'created_at' => Date::now(),
                    'updated_at' => Date::now(),
                ]);
            } catch (\Throwable) {
                throw new PasswordRecoveryFailed;
            }

            $this->evidence(
                'authentication.password_recovery.approved',
                'case_approved',
                $caseId,
                $actor,
                $request,
            );
            $approvals = DB::table('privileged_password_recovery_approvals')
                ->where('case_id', $caseId)
                ->where('case_version', (int) $case->version)
                ->distinct()
                ->count('actor_user_id');

            if ($approvals >= 2) {
                DB::table('privileged_password_recovery_cases')->where('id', $caseId)->update([
                    'status' => 'waiting_delay',
                    'not_before_at' => Date::now()->addHours(24),
                    'updated_at' => Date::now(),
                ]);
                $this->evidence(
                    'authentication.password_recovery.scheduled',
                    'delay_scheduled',
                    $caseId,
                    $actor,
                    $request,
                );
            }

            return $this->payload($caseId);
        }, 3);
    }

    /** @return array<string, mixed> */
    public function complete(
        User $actor,
        string $caseId,
        string $evidenceReference,
        Request $request,
    ): array {
        $this->assertActor($actor);

        return DB::transaction(function () use ($actor, $caseId, $evidenceReference, $request): array {
            $case = DB::table('privileged_password_recovery_cases')
                ->where('id', $caseId)
                ->lockForUpdate()
                ->first();
            $now = Date::now();

            if (
                ! $case instanceof \stdClass
                || $case->status !== 'waiting_delay'
                || $now->lessThan(Date::parse((string) $case->not_before_at))
                || $now->greaterThanOrEqualTo(Date::parse((string) $case->expires_at))
                || ! hash_equals((string) $case->evidence_digest, $this->evidenceDigest($evidenceReference))
            ) {
                throw new PasswordRecoveryFailed;
            }

            $approverIds = DB::table('privileged_password_recovery_approvals')
                ->where('case_id', $caseId)
                ->where('case_version', (int) $case->version)
                ->pluck('actor_user_id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->unique()
                ->values()
                ->all();

            if (count($approverIds) < 2) {
                throw new PasswordRecoveryFailed;
            }

            foreach ($approverIds as $approverId) {
                /** @var User|null $approver */
                $approver = User::query()->whereKey($approverId)->lockForUpdate()->first();

                if (! $approver instanceof User) {
                    throw new PasswordRecoveryFailed;
                }

                $this->assertActor($approver);
            }

            /** @var User|null $target */
            $target = User::query()->whereKey((string) $case->target_user_id)->lockForUpdate()->first();

            if (! $target instanceof User || $target->status !== User::STATUS_ACTIVE) {
                throw new PasswordRecoveryFailed;
            }

            if (! $this->recovery->issuePrivileged($target, $caseId)) {
                throw new PasswordRecoveryFailed;
            }

            $this->mobileFamilies->revokeAllForUser((string) $target->getKey(), 'password_recovered');
            $this->sessions->revokeAll($target, $request, 'password_recovered');
            DB::table('privileged_password_recovery_cases')->where('id', $caseId)->update([
                'status' => 'completed',
                'completed_at' => $now,
                'updated_at' => $now,
            ]);
            $this->evidence(
                'authentication.password_recovery.completed',
                'reset_issued',
                $caseId,
                $actor,
                $request,
            );

            return $this->payload($caseId);
        }, 3);
    }

    private function assertActor(User $actor): void
    {
        $configured = config('platform.identity_recovery.actor_user_ids', []);

        if (! is_array($configured)) {
            throw new PasswordRecoveryFailed;
        }

        foreach ($configured as $actorUserId) {
            if (! is_string($actorUserId) || ! Str::isUlid($actorUserId)) {
                throw new PasswordRecoveryFailed;
            }
        }

        if (
            $actor->status !== User::STATUS_ACTIVE
            || ! in_array((string) $actor->getKey(), $configured, true)
        ) {
            throw new PasswordRecoveryFailed;
        }
    }

    private function assertInput(string $reasonKey, string $evidenceReference): void
    {
        if (
            $reasonKey !== 'identity_recovery.verified_support_case'
            || preg_match('/\A[a-zA-Z0-9._:\/-]{8,160}\z/D', $evidenceReference) !== 1
        ) {
            throw new PasswordRecoveryFailed;
        }
    }

    private function evidenceDigest(string $reference): string
    {
        return $this->digester->digest('privileged-password-recovery-evidence', $reference);
    }

    private function evidence(
        string $action,
        string $reason,
        string $caseId,
        User $actor,
        Request $request,
    ): void {
        $correlationId = CorrelationId::forRequest($request);
        $this->audit->recordPlatform(
            $this->platformOperations->authorizePasswordIdentityRecoveryAudit(
                $action,
                $reason,
                $caseId,
                (string) $actor->getKey(),
                $correlationId,
            ),
        );
        $this->outbox->recordPlatform(
            $action,
            'privileged_password_recovery_case',
            $caseId,
            ['privileged_password_recovery_case_id' => $caseId],
            $correlationId,
        );
    }

    /** @return array<string, mixed> */
    private function payload(string $caseId): array
    {
        $case = DB::table('privileged_password_recovery_cases')->where('id', $caseId)->first();

        if (! $case instanceof \stdClass) {
            throw new PasswordRecoveryFailed;
        }

        return [
            'id' => (string) $case->id,
            'status' => (string) $case->status,
            'recovery_kind' => (string) $case->recovery_kind,
            'version' => (int) $case->version,
            'not_before_at' => Date::parse((string) $case->not_before_at)->toIso8601String(),
            'expires_at' => Date::parse((string) $case->expires_at)->toIso8601String(),
            'approval_count' => DB::table('privileged_password_recovery_approvals')
                ->where('case_id', $caseId)
                ->where('case_version', (int) $case->version)
                ->count(),
        ];
    }
}
