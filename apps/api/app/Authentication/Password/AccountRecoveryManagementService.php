<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use App\Audit\AuditEventRecorder;
use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Authentication\PhoneNumberNormalizer;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Identity\IdentityIdentifierDigester;
use App\Models\AuthenticationSession;
use App\Models\User;
use App\Models\UserIdentity;
use App\Observability\CorrelationId;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\PlatformOperationAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class AccountRecoveryManagementService
{
    public function __construct(
        private RecoveryTokenDigester $digester,
        private IdentityIdentifierDigester $identifiers,
        private PhoneNumberNormalizer $phones,
        private PasswordCredentialService $passwords,
        private PasswordRecoveryService $recovery,
        private AuthenticationSessionService $sessions,
        private MobileTokenFamilyService $mobileFamilies,
        private FormerRecoveryContactNotifier $formerContactNotifier,
        private PlatformOperationAuthorizer $platformOperations,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    /** @return array{id: string, status: string, expires_at: string} */
    public function requestCredentialSetup(
        User $actor,
        AuthenticationSession $session,
        string $targetUserId,
        string $reasonKey,
        string $evidenceReference,
        Request $request,
    ): array {
        $this->assertActor($actor, $session);
        $this->assertInput(
            $reasonKey,
            'account_recovery.migration_setup',
            $evidenceReference,
        );

        if ((string) $actor->getKey() === $targetUserId || ! Str::isUlid($targetUserId)) {
            throw new PasswordRecoveryFailed;
        }

        /** @var array{event_id: string, target: User} $authorization */
        $authorization = DB::transaction(function () use (
            $actor,
            $evidenceReference,
            $request,
            $targetUserId,
        ): array {
            /** @var User|null $target */
            $target = User::query()->whereKey($targetUserId)->lockForUpdate()->first();

            if (! $target instanceof User
                || $target->status !== User::STATUS_ACTIVE
                || $target->username !== null
                || $target->passwordCredential()->exists()
                || ! $this->hasCurrentCutoverExclusion($targetUserId)
                || ! $this->hasVerifiedRecoveryContact($targetUserId)) {
                throw new PasswordRecoveryFailed;
            }

            $eventId = (string) Str::ulid();
            $now = Date::now();
            DB::table('account_recovery_management_events')->insert([
                'id' => $eventId,
                'target_user_id' => $targetUserId,
                'actor_user_id' => (string) $actor->getKey(),
                'action' => 'credential_setup_requested',
                'reason_key' => 'account_recovery.migration_setup',
                'evidence_digest' => $this->evidenceDigest($evidenceReference),
                'prior_identity_id' => null,
                'replacement_identity_id' => null,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->recordEvidence(
                'authentication.account_recovery.setup_requested',
                'account_recovery.migration_setup',
                $eventId,
                $actor,
                $request,
            );

            return ['event_id' => $eventId, 'target' => $target];
        }, 3);

        if (! $this->recovery->issueAccountSetup(
            $authorization['target'],
            $authorization['event_id'],
        )) {
            throw new PasswordRecoveryFailed;
        }

        $expiresAt = DB::table('password_recovery_challenges')
            ->where('account_recovery_management_event_id', $authorization['event_id'])
            ->value('expires_at');

        if ($expiresAt === null) {
            throw new PasswordRecoveryFailed;
        }

        return [
            'id' => $authorization['event_id'],
            'status' => 'delivered',
            'expires_at' => Date::parse((string) $expiresAt)->toIso8601String(),
        ];
    }

    /** @return array{id: string, status: string, notification_outcome: string} */
    public function replaceRecoveryContact(
        User $actor,
        AuthenticationSession $session,
        string $targetUserId,
        string $contactType,
        string $contact,
        string $reasonKey,
        string $evidenceReference,
        Request $request,
    ): array {
        $this->assertActor($actor, $session);
        $this->assertInput(
            $reasonKey,
            'account_recovery.verified_contact_replacement',
            $evidenceReference,
        );

        if ((string) $actor->getKey() === $targetUserId || ! Str::isUlid($targetUserId)) {
            throw new PasswordRecoveryFailed;
        }

        [$normalizedContact, $displayHint] = $this->normalizeContact(
            $contactType,
            $contact,
        );
        $identifierDigest = $this->identifiers->digest($contactType, $normalizedContact);

        /** @var array{event_id: string, former_identity: UserIdentity} $result */
        $result = DB::transaction(function () use (
            $actor,
            $contactType,
            $displayHint,
            $evidenceReference,
            $identifierDigest,
            $request,
            $targetUserId,
        ): array {
            $this->lockIdentity($identifierDigest);
            /** @var User|null $target */
            $target = User::query()->whereKey($targetUserId)->lockForUpdate()->first();

            if (! $target instanceof User
                || $target->status !== User::STATUS_ACTIVE
                || $target->username === null
                || ! $target->passwordCredential()->exists()
                || UserIdentity::query()
                    ->where('type', $contactType)
                    ->where('identifier_digest', $identifierDigest)
                    ->exists()) {
                throw new PasswordRecoveryFailed;
            }

            $formerIdentities = UserIdentity::query()
                ->where('user_id', $targetUserId)
                ->where('status', UserIdentity::STATUS_VERIFIED)
                ->whereIn('type', ['phone', 'email'])
                ->orderBy('verified_at')
                ->lockForUpdate()
                ->get();
            /** @var UserIdentity|null $formerIdentity */
            $formerIdentity = $formerIdentities->first();

            if (! $formerIdentity instanceof UserIdentity) {
                throw new PasswordRecoveryFailed;
            }

            UserIdentity::query()
                ->where('user_id', $targetUserId)
                ->where('status', UserIdentity::STATUS_VERIFIED)
                ->whereIn('type', ['phone', 'email'])
                ->update([
                    'status' => UserIdentity::STATUS_DISABLED,
                    'updated_at' => Date::now(),
                ]);
            $replacementIdentity = new UserIdentity;
            $replacementIdentity->forceFill([
                'user_id' => $targetUserId,
                'type' => $contactType,
                'identifier_digest' => $identifierDigest,
                'display_hint' => $displayHint,
                'status' => UserIdentity::STATUS_VERIFIED,
                'verified_at' => Date::now(),
            ])->save();

            $eventId = (string) Str::ulid();
            $now = Date::now();
            DB::table('account_recovery_management_events')->insert([
                'id' => $eventId,
                'target_user_id' => $targetUserId,
                'actor_user_id' => (string) $actor->getKey(),
                'action' => 'recovery_contact_replaced',
                'reason_key' => 'account_recovery.verified_contact_replacement',
                'evidence_digest' => $this->evidenceDigest($evidenceReference),
                'prior_identity_id' => (string) $formerIdentity->getKey(),
                'replacement_identity_id' => (string) $replacementIdentity->getKey(),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->mobileFamilies->revokeAllForUser(
                $targetUserId,
                'recovery_contact_replaced',
            );
            $this->sessions->revokeAll(
                $target,
                $request,
                'recovery_contact_replaced',
            );
            $this->recordEvidence(
                'authentication.account_recovery.contact_replaced',
                'account_recovery.verified_contact_replacement',
                $eventId,
                $actor,
                $request,
            );

            return [
                'event_id' => $eventId,
                'former_identity' => $formerIdentity,
            ];
        }, 3);

        $notificationOutcome = $this->notifyFormerContact(
            $result['former_identity'],
            $result['event_id'],
        );

        return [
            'id' => $result['event_id'],
            'status' => 'completed',
            'notification_outcome' => $notificationOutcome,
        ];
    }

    private function assertActor(User $actor, AuthenticationSession $session): void
    {
        $configured = config('platform.account_recovery.manager_user_ids', []);

        if (! is_array($configured)) {
            throw new PasswordRecoveryFailed;
        }

        foreach ($configured as $actorUserId) {
            if (! is_string($actorUserId) || ! Str::isUlid($actorUserId)) {
                throw new PasswordRecoveryFailed;
            }
        }

        if ($actor->status !== User::STATUS_ACTIVE
            || (string) $session->user_id !== (string) $actor->getKey()
            || $session->revoked_at !== null
            || ! $this->passwords->hasFreshConfirmation($session)
            || ! in_array((string) $actor->getKey(), $configured, true)) {
            throw new PasswordRecoveryFailed;
        }
    }

    private function assertInput(
        string $providedReason,
        string $expectedReason,
        string $evidenceReference,
    ): void {
        if ($providedReason !== $expectedReason
            || preg_match('/\A[a-zA-Z0-9._:\/-]{8,160}\z/D', $evidenceReference) !== 1) {
            throw new PasswordRecoveryFailed;
        }
    }

    /** @return array{string, string} */
    private function normalizeContact(string $type, string $contact): array
    {
        if ($type === 'phone') {
            $normalized = $this->phones->normalize($contact);

            return [$normalized, substr($normalized, 0, 3).'******'.substr($normalized, -4)];
        }

        if ($type !== 'email') {
            throw new PasswordRecoveryFailed;
        }

        $normalized = strtolower(trim($contact));

        if (strlen($normalized) > 254 || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new PasswordRecoveryFailed;
        }

        [$local, $domain] = explode('@', $normalized, 2);

        return [$normalized, substr(substr($local, 0, 1).'***@'.$domain, 0, 40)];
    }

    private function hasVerifiedRecoveryContact(string $userId): bool
    {
        return UserIdentity::query()
            ->where('user_id', $userId)
            ->where('status', UserIdentity::STATUS_VERIFIED)
            ->whereIn('type', ['phone', 'email'])
            ->exists();
    }

    private function hasCurrentCutoverExclusion(string $userId): bool
    {
        return DB::table('password_cutover_exclusions as exclusion')
            ->join(
                'password_authentication_cutovers as cutover',
                'cutover.scope_key',
                '=',
                'exclusion.cutover_scope_key',
            )
            ->where('exclusion.user_id', $userId)
            ->where('exclusion.cutover_scope_key', 'global')
            ->where('cutover.status', 'password_only')
            ->whereColumn('cutover.manifest_digest', 'exclusion.manifest_digest')
            ->exists();
    }

    private function evidenceDigest(string $reference): string
    {
        return $this->digester->digest('account-recovery-evidence', $reference);
    }

    private function notifyFormerContact(
        UserIdentity $formerIdentity,
        string $managementEventId,
    ): string {
        try {
            $outcome = $this->formerContactNotifier->notify(
                $formerIdentity,
                $managementEventId,
            );
        } catch (Throwable) {
            $outcome = FormerRecoveryContactNotifier::OUTCOME_UNAVAILABLE;
        }

        if (! in_array($outcome, [
            FormerRecoveryContactNotifier::OUTCOME_ACCEPTED,
            FormerRecoveryContactNotifier::OUTCOME_UNAVAILABLE,
            FormerRecoveryContactNotifier::OUTCOME_NOT_POSSIBLE,
        ], true)) {
            $outcome = FormerRecoveryContactNotifier::OUTCOME_UNAVAILABLE;
        }

        $eventId = (string) Str::ulid();
        $now = Date::now();
        DB::table('account_recovery_notification_events')->insert([
            'id' => $eventId,
            'management_event_id' => $managementEventId,
            'former_identity_id' => (string) $formerIdentity->getKey(),
            'outcome' => $outcome,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $outcome;
    }

    private function recordEvidence(
        string $action,
        string $reason,
        string $eventId,
        User $actor,
        Request $request,
    ): void {
        $correlationId = CorrelationId::forRequest($request);
        $this->audit->recordPlatform(
            $this->platformOperations->authorizeAccountRecoveryManagementAudit(
                $action,
                $reason,
                $eventId,
                (string) $actor->getKey(),
                $correlationId,
            ),
        );
        $this->outbox->recordPlatform(
            $action,
            'account_recovery_management_event',
            $eventId,
            ['account_recovery_management_event_id' => $eventId],
            $correlationId,
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
