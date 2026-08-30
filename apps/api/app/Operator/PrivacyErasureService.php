<?php

declare(strict_types=1);

namespace App\Operator;

use App\Audit\AuditEventRecorder;
use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Conversation\Voice\VoiceDeletionService;
use App\Models\Membership;
use App\Models\User;
use App\Operator\Exceptions\OperatorPrivacyIdempotencyConflict;
use App\Operator\Exceptions\OperatorPrivacyRejected;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use stdClass;

/**
 * Org-scoped account-erasure workflow aligned with ADR-0007 status machine.
 */
final readonly class PrivacyErasureService
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OperatorPrivacyAuthorization $authorization,
        private AuditEventRecorder $audit,
        private VoiceDeletionService $voiceDeletions,
        private MobileTokenFamilyService $mobileCredentials,
        private LearnerPrivacyInventory $inventory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function create(
        User $actor,
        string $targetMembershipId,
        string $idempotencyKey,
    ): array {
        $this->authorization->assertCanRequestErasure($actor);
        $this->assertIdempotencyKey($idempotencyKey);

        $context = $this->contexts->current();
        $organizationId = $context->organizationId;
        $erasureRequestId = DB::transaction(function () use (
            $targetMembershipId,
            $idempotencyKey,
            $organizationId,
        ): string {
            $context = $this->contexts->current();
            $actorMembershipId = $context->membershipId;

            if ($actorMembershipId === null) {
                throw new OperatorPrivacyRejected;
            }

            $existing = DB::table('privacy_erasure_requests')
                ->where('organization_id', $organizationId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof stdClass) {
                if (
                    (string) $existing->target_membership_id !== $targetMembershipId
                    || (string) $existing->requested_by_membership_id !== $actorMembershipId
                ) {
                    throw new OperatorPrivacyIdempotencyConflict;
                }

                return (string) $existing->id;
            }

            $membership = $this->ownedLearnerMembership(
                $organizationId,
                $targetMembershipId,
            );
            $outcome = $this->inventory->erasureOutcome(
                $organizationId,
                $membership,
            );
            $id = (string) Str::ulid();
            $now = Date::now()->toImmutable();
            DB::table('privacy_erasure_requests')->insert([
                'id' => $id,
                'organization_id' => $organizationId,
                'target_membership_id' => $targetMembershipId,
                'requested_by_membership_id' => $actorMembershipId,
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending',
                'outcome_json' => json_encode($outcome, JSON_THROW_ON_ERROR),
                'voice_recording_count' => 0,
                'voice_deletion_completed_count' => 0,
                'requested_at' => $now,
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->applyDatabaseErasure(
                organizationId: $organizationId,
                membership: $membership,
                actorUserId: $context->actorUserId,
                now: $now,
            );
            $recordings = DB::table('voice_recordings')
                ->where('organization_id', $organizationId)
                ->where('learner_membership_id', $targetMembershipId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($recordings as $recording) {
                $this->voiceDeletions->recordPrivacyIntent(
                    recording: $recording,
                    privacyErasureRequestId: $id,
                    now: $now,
                );
            }

            $this->audit->recordTenant(
                'privacy.erasure.requested',
                'privacy_erasure_request',
                $id,
            );

            return $id;
        }, 3);

        $this->voiceDeletions->processForPrivacyErasure(
            $organizationId,
            $erasureRequestId,
        );
        $row = DB::table('privacy_erasure_requests')
            ->where('organization_id', $organizationId)
            ->where('id', $erasureRequestId)
            ->first();

        if (! $row instanceof stdClass) {
            throw new OperatorPrivacyRejected;
        }

        return $this->present($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $actor, string $erasureRequestId): array
    {
        $this->authorization->assertCanRequestErasure($actor);
        $organizationId = $this->contexts->current()->organizationId;

        $row = DB::table('privacy_erasure_requests')
            ->where('organization_id', $organizationId)
            ->where('id', $erasureRequestId)
            ->first();

        if (! $row instanceof stdClass) {
            throw new OperatorPrivacyRejected;
        }

        return $this->present($row);
    }

    private function ownedLearnerMembership(
        string $organizationId,
        string $membershipId,
    ): stdClass {
        $membership = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->where('membership_type', Membership::TYPE_LEARNER)
            ->lockForUpdate()
            ->first(['id', 'user_id', 'membership_type', 'status']);

        if (! $membership instanceof stdClass) {
            throw new OperatorPrivacyRejected;
        }

        return $membership;
    }

    private function applyDatabaseErasure(
        string $organizationId,
        stdClass $membership,
        string $actorUserId,
        \DateTimeImmutable $now,
    ): void {
        $membershipId = (string) $membership->id;
        $userId = (string) $membership->user_id;

        DB::table('membership_roles')
            ->where('organization_id', $organizationId)
            ->where('membership_id', $membershipId)
            ->delete();
        DB::table('enrollments')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->whereIn('status', ['pending_curriculum', 'active'])
            ->update([
                'status' => 'withdrawn',
                'version' => DB::raw('version + 1'),
                'ended_at' => $now,
                'updated_at' => $now,
            ]);
        DB::table('learner_error_patterns')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->delete();

        $activeMemoryFacts = DB::table('learner_memory_facts')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'active')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'fact_text']);

        foreach ($activeMemoryFacts as $fact) {
            DB::table('memory_events')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'learner_membership_id' => $membershipId,
                'memory_fact_id' => (string) $fact->id,
                'actor_user_id' => $actorUserId,
                'event_code' => 'deleted',
                'before_digest' => hash('sha256', (string) $fact->fact_text),
                'after_digest' => null,
                'recorded_at' => $now,
            ]);
        }

        DB::table('learner_memory_facts')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'active')
            ->update([
                'status' => 'deleted',
                'fact_text' => '[privacy-erased]',
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
        DB::table('learner_memory_facts')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'deleted')
            ->update([
                'fact_text' => '[privacy-erased]',
                'updated_at' => $now,
            ]);

        if (Schema::hasTable('tuition_requests')) {
            DB::table('tuition_requests')
                ->where('organization_id', $organizationId)
                ->where('membership_id', $membershipId)
                ->update([
                    'preferred_window' => '[privacy-erased]',
                    'topic' => '[privacy-erased]',
                    'notes' => null,
                    'updated_at' => $now,
                ]);
        }

        DB::table('privacy_export_requests')
            ->where('organization_id', $organizationId)
            ->where('target_membership_id', $membershipId)
            ->update([
                'summary_json' => json_encode(
                    $this->redactedExportSummary(
                        $organizationId,
                        $membershipId,
                        $now,
                    ),
                    JSON_THROW_ON_ERROR,
                ),
                'updated_at' => $now,
            ]);

        DB::table('sessions')->where('user_id', $userId)->delete();
        DB::table('authentication_sessions')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $now,
                'revocation_reason' => 'privacy_erasure',
                'updated_at' => $now,
            ]);
        DB::table('mobile_token_families')
            ->where('user_id', $userId)
            ->where('selected_organization_id', $organizationId)
            ->where('selected_membership_id', $membershipId)
            ->update([
                'selected_organization_id' => null,
                'selected_membership_id' => null,
                'updated_at' => $now,
            ]);
        $this->mobileCredentials->revokeAllForUser(
            $userId,
            'privacy_erasure',
        );
        DB::table('oauth_auth_codes')
            ->where('user_id', $userId)
            ->update(['revoked' => true]);
        $accessTokenIds = DB::table('oauth_access_tokens')
            ->where('user_id', $userId)
            ->pluck('id')
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->values()
            ->all();
        DB::table('oauth_access_tokens')
            ->where('user_id', $userId)
            ->update(['revoked' => true]);

        if ($accessTokenIds !== []) {
            DB::table('oauth_refresh_tokens')
                ->whereIn('access_token_id', $accessTokenIds)
                ->update(['revoked' => true]);
        }

        if ((string) $membership->status !== Membership::STATUS_ENDED) {
            DB::table('memberships')
                ->where('organization_id', $organizationId)
                ->where('id', $membershipId)
                ->update([
                    'status' => Membership::STATUS_ENDED,
                    'ended_at' => $now,
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function redactedExportSummary(
        string $organizationId,
        string $membershipId,
        \DateTimeImmutable $now,
    ): array {
        return [
            'inventory_version' => LearnerPrivacyInventory::VERSION,
            'scope' => [
                'kind' => 'organization_membership',
                'organization_id' => $organizationId,
                'membership_id' => $membershipId,
            ],
            'excluded_sensitive_values' => [
                'authentication_credentials',
                'cryptographic_digests',
                'raw_audio',
                'raw_transcripts',
                'provider_secret_payloads',
            ],
            'record_groups' => [],
            'privacy_erased_at' => $now->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(stdClass $row): array
    {
        $outcome = $row->outcome_json ?? null;

        if (is_string($outcome)) {
            $decoded = json_decode($outcome, true);
            $outcome = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($outcome)) {
            $outcome = [];
        }

        if ($outcome === []) {
            $outcome = [
                'inventory_version' => 'legacy-privacy-v0',
                'scope' => [
                    'kind' => 'organization_membership',
                    'organization_id' => (string) $row->organization_id,
                    'membership_id' => (string) $row->target_membership_id,
                ],
                'record_outcomes' => [],
            ];
        }

        return [
            'erasure_request_id' => (string) $row->id,
            'target_membership_id' => (string) $row->target_membership_id,
            'status' => (string) $row->status,
            'outcome' => $outcome,
            'voice_recording_count' => (int) $row->voice_recording_count,
            'voice_deletion_completed_count' => (int) $row->voice_deletion_completed_count,
            'requested_at' => $this->iso($row->requested_at ?? null),
            'completed_at' => $this->isoNullable($row->completed_at ?? null),
        ];
    }

    private function assertIdempotencyKey(string $key): void
    {
        if (preg_match(
            '/\A(?:[0-9A-HJKMNP-TV-Z]{26}|[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\z/',
            $key,
        ) !== 1) {
            throw new OperatorPrivacyRejected;
        }
    }

    private function iso(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return is_string($value) ? $value : '';
    }

    private function isoNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $formatted = $this->iso($value);

        return $formatted === '' ? null : $formatted;
    }
}
