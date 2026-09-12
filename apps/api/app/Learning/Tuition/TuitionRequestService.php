<?php

declare(strict_types=1);

namespace App\Learning\Tuition;

use App\Curriculum\CanonicalJson;
use App\Learning\LearnerAuthority;
use App\Learning\Tuition\Exceptions\TuitionRequestIdempotencyConflict;
use App\Models\TuitionRequest;
use App\Models\User;
use App\Operator\LearnerAdministration\Exceptions\LearnerAdministrationRejected;
use App\Operator\LearnerAdministration\LearnerAdministrationAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TuitionRequestService
{
    public function __construct(
        private readonly LearnerAuthority $learnerAuthority,
        private readonly LearnerAdministrationAuthorization $operatorAuthorization,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listMine(User $learner): array
    {
        $context = $this->learnerAuthority->require($learner);

        return array_values(TuitionRequest::query()
            ->where('organization_id', $context->organizationId)
            ->where('user_id', $learner->getKey())
            ->where('membership_id', $context->membershipId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TuitionRequest $row): array => $this->learnerPayload($row))
            ->all());
    }

    /**
     * @return array{request: array<string, mixed>, created: bool}
     */
    public function create(
        User $learner,
        string $preferredWindow,
        string $topic,
        ?string $notes,
        string $idempotencyKey,
    ): array {
        $context = $this->learnerAuthority->require($learner);
        $preferredWindow = trim($preferredWindow);
        $topic = trim($topic);
        $notes = $notes === null ? null : trim($notes);
        if ($notes === '') {
            $notes = null;
        }
        $hash = CanonicalJson::sha256([
            'preferred_window' => $preferredWindow,
            'topic' => $topic,
            'notes' => $notes,
        ]);

        return DB::transaction(function () use (
            $learner,
            $context,
            $preferredWindow,
            $topic,
            $notes,
            $idempotencyKey,
            $hash,
        ): array {
            $existing = TuitionRequest::query()
                ->where('organization_id', $context->organizationId)
                ->where('user_id', $learner->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                if ($existing->request_hash !== $hash) {
                    throw new TuitionRequestIdempotencyConflict;
                }

                return [
                    'request' => $this->learnerPayload($existing),
                    'created' => false,
                ];
            }

            $row = TuitionRequest::query()->create([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'user_id' => $learner->getKey(),
                'membership_id' => $context->membershipId,
                'preferred_window' => $preferredWindow,
                'topic' => $topic,
                'notes' => $notes,
                'status' => TuitionRequest::STATUS_SUBMITTED,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $hash,
            ]);

            return [
                'request' => $this->learnerPayload($row),
                'created' => true,
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForOperator(User $actor): array
    {
        $this->operatorAuthorization->assert(
            $actor,
            LearnerAdministrationAuthorization::LEARNERS_READ,
        );
        $context = $this->operatorAuthorization->contextFor($actor);

        return array_values(TuitionRequest::query()
            ->where('organization_id', $context->organizationId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (TuitionRequest $row): array => $this->operatorPayload($row))
            ->all());
    }

    /**
     * @return array{request: array<string, mixed>, created: bool}
     */
    public function acknowledge(
        User $actor,
        string $tuitionRequestId,
        string $idempotencyKey,
    ): array {
        $this->operatorAuthorization->assert(
            $actor,
            LearnerAdministrationAuthorization::LEARNERS_READ,
        );
        $context = $this->operatorAuthorization->contextFor($actor);

        return DB::transaction(function () use (

            $context,
            $tuitionRequestId,
            $idempotencyKey,
        ): array {
            $row = TuitionRequest::query()
                ->where('organization_id', $context->organizationId)
                ->whereKey($tuitionRequestId)
                ->lockForUpdate()
                ->first();
            if ($row === null) {
                throw new LearnerAdministrationRejected;
            }
            if ($row->status === TuitionRequest::STATUS_ACKNOWLEDGED) {
                if ($row->acknowledge_idempotency_key === $idempotencyKey) {
                    return [
                        'request' => $this->operatorPayload($row),
                        'created' => false,
                    ];
                }
                throw new TuitionRequestIdempotencyConflict;
            }

            $row->forceFill([
                'status' => TuitionRequest::STATUS_ACKNOWLEDGED,
                'acknowledge_idempotency_key' => $idempotencyKey,
                'acknowledged_by_membership_id' => $context->membershipId,
                'acknowledged_at' => now(),
            ])->save();

            return [
                'request' => $this->operatorPayload($row->fresh() ?? $row),
                'created' => true,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function learnerPayload(TuitionRequest $row): array
    {
        return [
            'id' => (string) $row->getKey(),
            'preferred_window' => (string) $row->preferred_window,
            'topic' => (string) $row->topic,
            'notes' => $row->notes,
            'status' => (string) $row->status,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operatorPayload(TuitionRequest $row): array
    {
        $displayName = User::query()->whereKey($row->user_id)->value('display_name');

        return [
            'id' => (string) $row->getKey(),
            'membership_id' => (string) $row->membership_id,
            'display_name' => is_string($displayName) ? $displayName : null,
            'preferred_window' => (string) $row->preferred_window,
            'topic' => (string) $row->topic,
            'notes' => $row->notes,
            'status' => (string) $row->status,
            'created_at' => $row->created_at?->toIso8601String(),
            'acknowledged_at' => $row->acknowledged_at?->toIso8601String(),
        ];
    }
}
