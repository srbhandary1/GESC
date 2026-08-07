<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Support\RoleplayStages;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class RoleplayStageService
{
    public function __construct(
        private ConversationEnvironmentGate $environment,
        private LearnerAuthority $authority,
        private ConversationCommandIdempotency $commands,
        private ConversationSessionService $sessions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function advance(
        User $learner,
        string $sessionId,
        string $toStage,
        string $idempotencyKey,
    ): array {
        $this->environment->requireDeterministicFakes();
        $this->commands->assertIdempotencyKey($idempotencyKey);

        if (! RoleplayStages::isValid($toStage)) {
            throw new ConversationOperationRejected;
        }

        return DB::transaction(function () use (
            $learner,
            $sessionId,
            $toStage,
            $idempotencyKey,
        ): array {
            $context = $this->authority->require($learner);
            $membershipId = (string) $context->membershipId;
            $fingerprint = $this->commands->fingerprint([
                'session_id' => $sessionId,
                'to_stage' => $toStage,
            ]);
            $replay = $this->commands->replay(
                actor: $learner,
                operation: 'conversation.session.stage.advance',
                aggregateType: 'conversation_session',
                aggregateId: $sessionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay instanceof stdClass) {
                return $this->sessions->existingFor(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $this->string($replay, 'result_id'),
                    replayed: true,
                );
            }

            $session = DB::table('conversation_sessions')
                ->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $membershipId)
                ->where('id', $sessionId)
                ->lockForUpdate()
                ->first([
                    'id',
                    'mode',
                    'scenario_id',
                    'scenario_version_id',
                ]);

            if (
                ! $session instanceof stdClass
                || $this->string($session, 'mode') !== 'roleplay'
            ) {
                throw new ConversationOperationRejected;
            }

            $currentStage = $this->currentStage(
                organizationId: $context->organizationId,
                sessionId: $sessionId,
            );
            $expected = RoleplayStages::nextStage($currentStage);

            if ($expected === null || $expected !== $toStage) {
                throw new ConversationOperationRejected;
            }

            $sequence = (int) DB::table('conversation_session_stage_events')
                ->where('organization_id', $context->organizationId)
                ->where('conversation_session_id', $sessionId)
                ->max('sequence');
            $now = Date::now()->toImmutable();

            DB::table('conversation_session_stage_events')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'conversation_session_id' => $sessionId,
                'learner_membership_id' => $membershipId,
                'from_stage' => $currentStage,
                'to_stage' => $toStage,
                'sequence' => $sequence + 1,
                'created_at' => $now,
            ]);

            if ($toStage === RoleplayStages::REFLECT) {
                $this->recordSyntheticFeedback(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                    scenarioId: $this->string($session, 'scenario_id'),
                    scenarioVersionId: $this->string(
                        $session,
                        'scenario_version_id',
                    ),
                    recordedAt: $now,
                );
            }

            $this->commands->record(
                actor: $learner,
                operation: 'conversation.session.stage.advance',
                aggregateType: 'conversation_session',
                aggregateId: $sessionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'conversation_session',
                resultId: $sessionId,
                completedAt: $now,
            );

            return $this->sessions->existingFor(
                organizationId: $context->organizationId,
                membershipId: $membershipId,
                sessionId: $sessionId,
                replayed: false,
            );
        }, 3);
    }

    private function currentStage(
        string $organizationId,
        string $sessionId,
    ): string {
        $toStage = DB::table('conversation_session_stage_events')
            ->where('organization_id', $organizationId)
            ->where('conversation_session_id', $sessionId)
            ->orderByDesc('sequence')
            ->lockForUpdate()
            ->value('to_stage');

        if (! is_string($toStage) || ! RoleplayStages::isValid($toStage)) {
            throw new ConversationOperationRejected;
        }

        return $toStage;
    }

    private function recordSyntheticFeedback(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        string $scenarioId,
        string $scenarioVersionId,
        CarbonInterface $recordedAt,
    ): void {
        $objectives = DB::table('scenario_objectives')
            ->where('organization_id', $organizationId)
            ->where('scenario_version_id', $scenarioVersionId)
            ->orderBy('sort_order')
            ->get([
                'id',
                'objective_key',
            ]);

        foreach ($objectives as $index => $objective) {
            if (! $objective instanceof stdClass) {
                continue;
            }

            $objectiveKey = $this->string($objective, 'objective_key');
            DB::table('roleplay_feedback_evidence')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'conversation_session_id' => $sessionId,
                'learner_membership_id' => $membershipId,
                'scenario_id' => $scenarioId,
                'scenario_version_id' => $scenarioVersionId,
                'scenario_objective_id' => $this->string($objective, 'id'),
                'evidence_digest' => hash(
                    'sha256',
                    $objectiveKey.'|'.$sessionId,
                ),
                'completion_state' => $index === 0 ? 'met' : 'unmet',
                'created_at' => $recordedAt,
            ]);
        }
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new ConversationOperationRejected;
        }

        return $value;
    }
}
