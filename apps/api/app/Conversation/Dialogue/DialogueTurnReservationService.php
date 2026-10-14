<?php

declare(strict_types=1);

namespace App\Conversation\Dialogue;

use App\Conversation\ConversationCommandIdempotency;
use App\Conversation\Exceptions\ConversationIdempotencyConflict;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Voice\VoiceConsentService;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use stdClass;

/** Durable admission metadata; never stores a body or guesses unknown spend. */
final readonly class DialogueTurnReservationService
{
    public function __construct(
        private LearnerAuthority $authority,
        private ConversationCommandIdempotency $commands,
        private VoiceConsentService $consents,
    ) {}

    /**
     * Run BEFORE the provider transaction. A callback performs business
     * preflight only (no provider calls), returning the locked state revision.
     * Null means legacy or an existing committed command ready for exact replay.
     *
     * @param  callable(stdClass, stdClass): int  $admit  Scoped state, scoped session.
     */
    public function reserve(User $learner, string $sessionId, string $idempotencyKey, string $fingerprint, callable $admit): ?string
    {
        $enteredInsideTransaction = DB::transactionLevel() > 0;
        $this->commands->assertIdempotencyKey($idempotencyKey);
        if (preg_match('/\A[0-9a-f]{64}\z/', $fingerprint) !== 1) {
            throw new ConversationOperationRejected;
        }

        return DB::transaction(function () use ($learner, $sessionId, $idempotencyKey, $fingerprint, $admit, $enteredInsideTransaction): ?string {
            $context = $this->authority->require($learner);
            $membershipId = (string) $context->membershipId;
            $session = DB::table('conversation_sessions')->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $membershipId)->where('id', $sessionId)->where('status', 'active')->lockForUpdate()->first();
            if (! $session instanceof stdClass) {
                throw new ConversationOperationRejected;
            }
            $state = DB::table('conversation_dialogue_states')->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $membershipId)->where('conversation_session_id', $sessionId)->first();
            if (! $state instanceof stdClass) {
                return null;
            }
            // An enclosing runtime transaction would undo this crash barrier.
            // Tests use framework-owned rollback isolation; legacy is unchanged.
            if ($enteredInsideTransaction && ! app()->environment('testing')) {
                throw new LogicException('Dialogue admission must precede the provider transaction.');
            }
            $this->consents->activeEvent($context->organizationId, $membershipId, VoiceConsentService::PROVIDER);
            if ($state->closed_at !== null || Date::parse($state->expires_at)->lessThanOrEqualTo(Date::now())) {
                throw new ConversationOperationRejected('conversation_recovery_expired');
            }
            if ($this->commands->replay($learner, 'conversation.turn.create', 'conversation_session', $sessionId, $idempotencyKey, $fingerprint) instanceof stdClass) {
                return null;
            }
            $scope = DB::table('conversation_dialogue_turn_reservations')->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $membershipId)->where('conversation_session_id', $sessionId);
            $existing = (clone $scope)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing instanceof stdClass) {
                if ($existing->actor_user_id !== (string) $learner->getKey()
                    || ! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                    throw new ConversationIdempotencyConflict;
                }
                throw new DialogueTurnOutcomeUnknown;
            }
            $revision = (int) $state->revision;
            // A fresh client key cannot conceal the same unknown logical turn.
            if ((clone $scope)->where('expected_revision', $revision)->exists()) {
                throw new DialogueTurnOutcomeUnknown;
            }
            if ($admit($state, $session) !== $revision) {
                throw new ConversationIdempotencyConflict;
            }
            $id = (string) Str::ulid();
            DB::table('conversation_dialogue_turn_reservations')->insert([
                'id' => $id, 'organization_id' => $context->organizationId, 'learner_membership_id' => $membershipId,
                'conversation_session_id' => $sessionId, 'actor_user_id' => $learner->getKey(),
                'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $fingerprint,
                'expected_revision' => $revision, 'status' => 'reserved', 'created_at' => Date::now(),
            ]);

            return $id;
        });
    }

    /** Complete only alongside the authoritative command/outcome transaction. */
    public function complete(string $organizationId, string $membershipId, string $sessionId, string $reservationId, string $turnId): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Dialogue completion requires the command transaction.');
        }
        $query = DB::table('conversation_dialogue_turn_reservations')->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)->where('conversation_session_id', $sessionId)->where('id', $reservationId);
        $row = (clone $query)->lockForUpdate()->first();
        if (! $row instanceof stdClass || ($row->status === 'completed' && $row->completed_turn_id !== $turnId)) {
            throw new ConversationOperationRejected;
        }
        $command = DB::table('conversation_commands')->where('organization_id', $organizationId)
            ->where('actor_membership_id', $membershipId)->where('actor_user_id', $row->actor_user_id)
            ->where('operation', 'conversation.turn.create')->where('aggregate_type', 'conversation_session')
            ->where('aggregate_id', $sessionId)->where('idempotency_key', $row->idempotency_key)
            ->where('request_fingerprint', $row->request_fingerprint)->where('result_id', $turnId)->exists();
        if (! $command) {
            throw new ConversationOperationRejected;
        }
        if ($row->status !== 'completed') {
            $query->update(['status' => 'completed', 'completed_turn_id' => $turnId, 'completed_at' => Date::now()]);
        }
    }
}
