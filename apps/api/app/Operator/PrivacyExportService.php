<?php

declare(strict_types=1);

namespace App\Operator;

use App\Audit\AuditEventRecorder;
use App\Models\Membership;
use App\Models\User;
use App\Operator\Exceptions\OperatorPrivacyIdempotencyConflict;
use App\Operator\Exceptions\OperatorPrivacyRejected;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Complete, bounded-class org-scoped export — never credentials, audio, or transcripts.
 */
final readonly class PrivacyExportService
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OperatorPrivacyAuthorization $authorization,
        private AuditEventRecorder $audit,
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
        $this->authorization->assertCanRequestExport($actor);
        $this->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $targetMembershipId,
            $idempotencyKey,
        ): array {
            $context = $this->contexts->current();
            $organizationId = $context->organizationId;
            $actorMembershipId = $context->membershipId;

            if ($actorMembershipId === null) {
                throw new OperatorPrivacyRejected;
            }

            $existing = DB::table('privacy_export_requests')
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

                return $this->present($existing);
            }

            $membership = $this->ownedMembership(
                $organizationId,
                $targetMembershipId,
            );
            $summary = $this->inventory->export($organizationId, $membership);
            $id = (string) Str::ulid();
            $now = now();

            DB::table('privacy_export_requests')->insert([
                'id' => $id,
                'organization_id' => $organizationId,
                'target_membership_id' => $targetMembershipId,
                'requested_by_membership_id' => $actorMembershipId,
                'idempotency_key' => $idempotencyKey,
                'status' => 'completed',
                'summary_json' => json_encode($summary, JSON_THROW_ON_ERROR),
                'requested_at' => $now,
                'completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->recordTenant(
                'privacy.export.requested',
                'privacy_export_request',
                $id,
            );

            $row = DB::table('privacy_export_requests')
                ->where('organization_id', $organizationId)
                ->where('id', $id)
                ->first();

            if (! $row instanceof stdClass) {
                throw new OperatorPrivacyRejected;
            }

            return $this->present($row);
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $actor, string $exportRequestId): array
    {
        $this->authorization->assertCanRequestExport($actor);
        $organizationId = $this->contexts->current()->organizationId;

        $row = DB::table('privacy_export_requests')
            ->where('organization_id', $organizationId)
            ->where('id', $exportRequestId)
            ->first();

        if (! $row instanceof stdClass) {
            throw new OperatorPrivacyRejected;
        }

        return $this->present($row);
    }

    private function ownedMembership(
        string $organizationId,
        string $membershipId,
    ): stdClass {
        $row = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->where('membership_type', Membership::TYPE_LEARNER)
            ->lockForUpdate()
            ->first([
                'id',
                'user_id',
                'membership_type',
                'status',
            ]);

        if (! $row instanceof stdClass) {
            throw new OperatorPrivacyRejected;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(stdClass $row): array
    {
        $summary = $row->summary_json;

        if (is_string($summary)) {
            $decoded = json_decode($summary, true);
            $summary = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($summary)) {
            $summary = [];
        }

        if (! isset($summary['inventory_version'])) {
            $summary = $this->legacySummary($row, $summary);
        }

        return [
            'export_request_id' => (string) $row->id,
            'target_membership_id' => (string) $row->target_membership_id,
            'status' => (string) $row->status,
            'summary' => $summary,
            'requested_at' => $this->iso($row->requested_at ?? null),
            'completed_at' => $this->isoNullable($row->completed_at ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    private function legacySummary(stdClass $row, array $legacy): array
    {
        $groups = [];
        $legacyClasses = [
            'consent_event_ids' => 'voice_consent_events',
            'voice_recording_ids' => 'voice_recordings',
            'voice_deletion_receipt_ids' => 'voice_deletion_requests',
            'conversation_session_ids' => 'conversation_sessions',
            'learner_attempt_ids' => 'learner_attempts',
        ];

        foreach ($legacyClasses as $legacyKey => $recordClass) {
            $ids = $legacy[$legacyKey] ?? [];
            $records = [];

            if (is_array($ids)) {
                foreach ($ids as $id) {
                    if (is_string($id) && $id !== '') {
                        $records[] = [
                            'record_id' => $id,
                            'attributes' => [],
                        ];
                    }
                }
            }

            $groups[] = [
                'record_class' => $recordClass,
                'scope' => 'organization_membership',
                'record_count' => count($records),
                'export_disposition' => 'legacy_record_identifiers_only',
                'erasure_disposition' => 'see_current_inventory_policy',
                'retention_basis' => 'This payload predates learner-privacy-v1 and is retained as a historical export receipt.',
                'records' => $records,
            ];
        }

        return [
            'inventory_version' => 'legacy-privacy-v0',
            'scope' => [
                'kind' => 'organization_membership',
                'organization_id' => (string) $row->organization_id,
                'membership_id' => (string) $row->target_membership_id,
            ],
            'excluded_sensitive_values' => [
                'authentication_credentials',
                'cryptographic_digests',
                'raw_audio',
                'raw_transcripts',
                'provider_secret_payloads',
            ],
            'record_groups' => $groups,
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
