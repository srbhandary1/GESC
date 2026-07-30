<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class ConversationEventService
{
    public function __construct(
        private ConversationEnvironmentGate $environment,
        private LearnerAuthority $authority,
    ) {}

    /**
     * @return list<array<string, int|string|null>>
     */
    public function forTurn(User $learner, string $turnId): array
    {
        $this->environment->requireDeterministicFakes();
        $context = $this->authority->require($learner);
        $membershipId = (string) $context->membershipId;
        $turn = DB::table('conversation_turns')
            ->where('organization_id', $context->organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('id', $turnId)
            ->first(['id']);

        if (! $turn instanceof stdClass) {
            throw new ConversationOperationRejected;
        }

        $rows = DB::table('conversation_turn_events')
            ->where('organization_id', $context->organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('conversation_turn_id', $turnId)
            ->orderBy('sequence')
            ->get([
                'sequence',
                'state',
                'event_code',
                'provider_stage',
                'provider_attempt',
                'recorded_at',
            ]);
        $events = [];

        foreach ($rows as $row) {
            $events[] = [
                'sequence' => (int) $row->sequence,
                'state' => (string) $row->state,
                'event_code' => (string) $row->event_code,
                'provider_stage' => is_string($row->provider_stage)
                    ? $row->provider_stage
                    : null,
                'provider_attempt' => $row->provider_attempt === null
                    ? null
                    : (int) $row->provider_attempt,
                'recorded_at' => (string) $row->recorded_at,
            ];
        }

        return $events;
    }
}
