<?php

declare(strict_types=1);

namespace App\Conversation\Memory;

use App\Conversation\ConversationCommandIdempotency;
use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Memory\Exceptions\MemoryIdempotencyConflict;
use App\Conversation\Memory\Exceptions\MemoryOperationRejected;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class LearnerMemoryService
{
    public function __construct(
        private LearnerAuthority $authority,
        private ConversationCommandIdempotency $commands,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(User $learner): array
    {
        $context = $this->authority->require($learner);
        $membershipId = (string) $context->membershipId;

        $rows = DB::table('learner_memory_facts')
            ->where('organization_id', $context->organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get([
                'id',
                'purpose',
                'source',
                'status',
                'fact_text',
                'created_at',
                'updated_at',
            ]);

        $facts = [];

        foreach ($rows as $row) {
            $facts[] = [
                'id' => (string) $row->id,
                'purpose' => (string) $row->purpose,
                'source' => (string) $row->source,
                'status' => (string) $row->status,
                'fact_text' => (string) $row->fact_text,
                'created_at' => (string) $row->created_at,
                'updated_at' => (string) $row->updated_at,
            ];
        }

        return $facts;
    }

    /**
     * @return list<string>
     */
    public function activeFactTexts(
        string $organizationId,
        string $membershipId,
    ): array {
        $maximum = max(
            0,
            (int) config('memory.maximum_active_facts', 5),
        );
        $maximumCharacters = max(
            1,
            (int) config('memory.maximum_fact_characters', 200),
        );

        $texts = [];

        foreach (
            DB::table('learner_memory_facts')
                ->where('organization_id', $organizationId)
                ->where('learner_membership_id', $membershipId)
                ->where('status', 'active')
                ->orderBy('created_at')
                ->limit($maximum)
                ->pluck('fact_text') as $text
        ) {
            if (
                ! is_string($text)
                || $text === ''
                || mb_strlen($text) > $maximumCharacters
            ) {
                continue;
            }

            $texts[] = $text;
        }

        return $texts;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        User $learner,
        string $factText,
        string $idempotencyKey,
    ): array {
        $normalized = $this->normalizeFactText($factText);
        $this->commands->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $learner,
            $normalized,
            $idempotencyKey,
        ): array {
            $context = $this->authority->require($learner);
            $membershipId = (string) $context->membershipId;
            $fingerprint = $this->commands->fingerprint([
                'fact_text' => $normalized,
                'operation' => 'memory.fact.create',
            ]);

            try {
                $replay = $this->commands->replay(
                    actor: $learner,
                    operation: 'memory.fact.create',
                    aggregateType: 'membership',
                    aggregateId: $membershipId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                );
            } catch (ConversationIdempotencyConflict) {
                throw new MemoryIdempotencyConflict;
            } catch (ConversationOperationRejected) {
                throw new MemoryOperationRejected;
            }

            if ($replay instanceof stdClass) {
                return [
                    ...$this->existingFact(
                        organizationId: $context->organizationId,
                        membershipId: $membershipId,
                        factId: (string) $replay->result_id,
                    ),
                    'replayed' => true,
                ];
            }

            $activeRows = DB::table('learner_memory_facts')
                ->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $membershipId)
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if (
                count($activeRows) >= (int) config(
                    'memory.maximum_active_facts',
                    5,
                )
            ) {
                throw new MemoryOperationRejected;
            }

            $now = Date::now()->toImmutable();
            $factId = (string) Str::ulid();
            DB::table('learner_memory_facts')->insert([
                'id' => $factId,
                'organization_id' => $context->organizationId,
                'learner_membership_id' => $membershipId,
                'purpose' => (string) config(
                    'memory.purpose',
                    'conversation_context',
                ),
                'source' => (string) config(
                    'memory.source',
                    'learner_explicit',
                ),
                'status' => 'active',
                'fact_text' => $normalized,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
            $this->recordEvent(
                organizationId: $context->organizationId,
                membershipId: $membershipId,
                factId: $factId,
                actorUserId: (string) $learner->getKey(),
                eventCode: 'created',
                beforeDigest: null,
                afterDigest: hash('sha256', $normalized),
            );
            $this->commands->record(
                actor: $learner,
                operation: 'memory.fact.create',
                aggregateType: 'membership',
                aggregateId: $membershipId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'learner_memory_fact',
                resultId: $factId,
                completedAt: $now,
            );

            return [
                ...$this->existingFact(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    factId: $factId,
                ),
                'replayed' => false,
            ];
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function update(
        User $learner,
        string $factId,
        string $factText,
        string $idempotencyKey,
    ): array {
        $normalized = $this->normalizeFactText($factText);
        $this->commands->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $learner,
            $factId,
            $normalized,
            $idempotencyKey,
        ): array {
            $context = $this->authority->require($learner);
            $membershipId = (string) $context->membershipId;
            $fingerprint = $this->commands->fingerprint([
                'fact_id' => $factId,
                'fact_text' => $normalized,
                'operation' => 'memory.fact.update',
            ]);

            try {
                $replay = $this->commands->replay(
                    actor: $learner,
                    operation: 'memory.fact.update',
                    aggregateType: 'learner_memory_fact',
                    aggregateId: $factId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                );
            } catch (ConversationIdempotencyConflict) {
                throw new MemoryIdempotencyConflict;
            } catch (ConversationOperationRejected) {
                throw new MemoryOperationRejected;
            }

            if ($replay instanceof stdClass) {
                return [
                    ...$this->existingFact(
                        organizationId: $context->organizationId,
                        membershipId: $membershipId,
                        factId: $factId,
                        allowDeleted: false,
                    ),
                    'replayed' => true,
                ];
            }

            $fact = $this->lockedActiveFact(
                organizationId: $context->organizationId,
                membershipId: $membershipId,
                factId: $factId,
            );
            $before = (string) $fact->fact_text;
            $now = Date::now()->toImmutable();
            $changed = DB::table('learner_memory_facts')
                ->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $membershipId)
                ->where('id', $factId)
                ->where('status', 'active')
                ->update([
                    'fact_text' => $normalized,
                    'updated_at' => $now,
                ]);

            if ($changed !== 1) {
                throw new MemoryOperationRejected;
            }

            $this->recordEvent(
                organizationId: $context->organizationId,
                membershipId: $membershipId,
                factId: $factId,
                actorUserId: (string) $learner->getKey(),
                eventCode: 'updated',
                beforeDigest: hash('sha256', $before),
                afterDigest: hash('sha256', $normalized),
            );
            $this->commands->record(
                actor: $learner,
                operation: 'memory.fact.update',
                aggregateType: 'learner_memory_fact',
                aggregateId: $factId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'learner_memory_fact',
                resultId: $factId,
                completedAt: $now,
            );

            return [
                ...$this->existingFact(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    factId: $factId,
                ),
                'replayed' => false,
            ];
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(
        User $learner,
        string $factId,
        string $idempotencyKey,
    ): array {
        $this->commands->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $learner,
            $factId,
            $idempotencyKey,
        ): array {
            $context = $this->authority->require($learner);
            $membershipId = (string) $context->membershipId;
            $fingerprint = $this->commands->fingerprint([
                'fact_id' => $factId,
                'operation' => 'memory.fact.delete',
            ]);

            try {
                $replay = $this->commands->replay(
                    actor: $learner,
                    operation: 'memory.fact.delete',
                    aggregateType: 'learner_memory_fact',
                    aggregateId: $factId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                );
            } catch (ConversationIdempotencyConflict) {
                throw new MemoryIdempotencyConflict;
            } catch (ConversationOperationRejected) {
                throw new MemoryOperationRejected;
            }

            if ($replay instanceof stdClass) {
                return [
                    ...$this->existingFact(
                        organizationId: $context->organizationId,
                        membershipId: $membershipId,
                        factId: $factId,
                        allowDeleted: true,
                    ),
                    'replayed' => true,
                ];
            }

            $fact = $this->lockedActiveFact(
                organizationId: $context->organizationId,
                membershipId: $membershipId,
                factId: $factId,
            );
            $before = (string) $fact->fact_text;
            $now = Date::now()->toImmutable();
            $changed = DB::table('learner_memory_facts')
                ->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $membershipId)
                ->where('id', $factId)
                ->where('status', 'active')
                ->update([
                    'status' => 'deleted',
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($changed !== 1) {
                throw new MemoryOperationRejected;
            }

            $this->recordEvent(
                organizationId: $context->organizationId,
                membershipId: $membershipId,
                factId: $factId,
                actorUserId: (string) $learner->getKey(),
                eventCode: 'deleted',
                beforeDigest: hash('sha256', $before),
                afterDigest: null,
            );
            $this->commands->record(
                actor: $learner,
                operation: 'memory.fact.delete',
                aggregateType: 'learner_memory_fact',
                aggregateId: $factId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'learner_memory_fact',
                resultId: $factId,
                completedAt: $now,
            );

            return [
                ...$this->existingFact(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    factId: $factId,
                    allowDeleted: true,
                ),
                'replayed' => false,
            ];
        }, 3);
    }

    private function normalizeFactText(string $factText): string
    {
        $normalized = trim($factText);
        $maximum = (int) config('memory.maximum_fact_characters', 200);

        if (
            $normalized === ''
            || preg_match('//u', $normalized) !== 1
            || mb_strlen($normalized) > $maximum
            || str_contains($normalized, '<')
            || str_contains($normalized, '>')
        ) {
            throw new MemoryOperationRejected;
        }

        return $normalized;
    }

    private function lockedActiveFact(
        string $organizationId,
        string $membershipId,
        string $factId,
    ): stdClass {
        $fact = DB::table('learner_memory_facts')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('id', $factId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first(['id', 'fact_text']);

        if (! $fact instanceof stdClass) {
            throw new MemoryOperationRejected;
        }

        return $fact;
    }

    /**
     * @return array<string, mixed>
     */
    private function existingFact(
        string $organizationId,
        string $membershipId,
        string $factId,
        bool $allowDeleted = false,
    ): array {
        $query = DB::table('learner_memory_facts')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('id', $factId);

        if (! $allowDeleted) {
            $query->where('status', 'active');
        }

        $fact = $query->first([
            'id',
            'purpose',
            'source',
            'status',
            'fact_text',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        if (! $fact instanceof stdClass) {
            throw new MemoryOperationRejected;
        }

        return [
            'id' => (string) $fact->id,
            'purpose' => (string) $fact->purpose,
            'source' => (string) $fact->source,
            'status' => (string) $fact->status,
            'fact_text' => (string) $fact->fact_text,
            'created_at' => (string) $fact->created_at,
            'updated_at' => (string) $fact->updated_at,
            'deleted_at' => $fact->deleted_at === null
                ? null
                : (string) $fact->deleted_at,
        ];
    }

    private function recordEvent(
        string $organizationId,
        string $membershipId,
        string $factId,
        string $actorUserId,
        string $eventCode,
        ?string $beforeDigest,
        ?string $afterDigest,
    ): void {
        DB::table('memory_events')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organizationId,
            'learner_membership_id' => $membershipId,
            'memory_fact_id' => $factId,
            'actor_user_id' => $actorUserId,
            'event_code' => $eventCode,
            'before_digest' => $beforeDigest,
            'after_digest' => $afterDigest,
            'recorded_at' => Date::now()->toImmutable(),
        ]);
    }
}
