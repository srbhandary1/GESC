<?php

declare(strict_types=1);

namespace App\Conversation\Realtime;

use App\Conversation\ConversationEnvironmentGate;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Routing\SpeechDispatchAuthority;
use App\Conversation\Voice\VoiceConsentService;
use App\Conversation\Voice\VoiceFeatureGate;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class RealtimeSpeechTicketService
{
    public const int TTL_SECONDS = 60;

    public function __construct(
        private ConversationEnvironmentGate $environment,
        private LearnerAuthority $authority,
        private VoiceConsentService $consents,
        private VoiceFeatureGate $voiceGate,
        private SpeechDispatchAuthority $dispatch,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function issue(
        User $learner,
        string $sessionId,
        string $idempotencyKey,
    ): array {
        $this->environment->requireAuthorizedConversationProvider();
        $context = $this->authority->require($learner);
        $membershipId = (string) $context->membershipId;
        $this->voiceGate->requireEnabled($context->organizationId);
        $this->ownedSession(
            $context->organizationId,
            $membershipId,
            $sessionId,
        );
        $this->consents->activeEvent(
            $context->organizationId,
            $membershipId,
            VoiceConsentService::UPLOAD,
        );
        $this->consents->activeEvent(
            $context->organizationId,
            $membershipId,
            VoiceConsentService::PROVIDER,
        );

        $replayKey = $this->commandKey(
            $context->organizationId,
            $membershipId,
            $idempotencyKey,
        );
        $replay = Cache::get($replayKey);
        if (is_array($replay) && isset($replay['payload']) && is_array($replay['payload'])) {
            return $replay['payload'];
        }

        $denial = array_values(array_unique([
            ...$this->dispatch->denialReasons('sarvam-saaras-realtime', 'stt'),
            ...$this->dispatch->denialReasons('elevenlabs-stt-realtime', 'stt'),
        ]));
        $ticketId = (string) Str::ulid();
        $expiresAt = Date::now()->toImmutable()->addSeconds(self::TTL_SECONDS);
        $payload = [
            'ticket_id' => $ticketId,
            'conversation_session_id' => $sessionId,
            'transport' => 'recorded_recovery',
            'websocket_path' => sprintf(
                '/api/v1/conversation-sessions/%s/realtime-stt',
                $sessionId,
            ),
            'provider_dispatch' => 'denied',
            'denial_reasons' => $denial,
            'recovery_route' => 'recorded_upload',
            'capture_mode' => 'push_to_talk',
            'always_on' => false,
            'full_duplex' => false,
            'preconnect_allowed' => false,
            'expires_at' => $expiresAt->toIso8601String(),
        ];

        Cache::put($replayKey, ['payload' => $payload], self::TTL_SECONDS);
        Cache::put(
            $this->ticketKey($context->organizationId, $ticketId),
            [
                'organization_id' => $context->organizationId,
                'membership_id' => $membershipId,
                'session_id' => $sessionId,
                'user_id' => (string) $learner->getKey(),
            ],
            self::TTL_SECONDS,
        );

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectSocket(
        User $learner,
        string $sessionId,
        string $ticketId,
    ): array {
        $context = $this->authority->require($learner);
        $membershipId = (string) $context->membershipId;
        $this->ownedSession(
            $context->organizationId,
            $membershipId,
            $sessionId,
        );
        $ticket = Cache::get($this->ticketKey($context->organizationId, $ticketId));
        if (
            ! is_array($ticket)
            || ($ticket['membership_id'] ?? null) !== $membershipId
            || ($ticket['session_id'] ?? null) !== $sessionId
            || ($ticket['user_id'] ?? null) !== (string) $learner->getKey()
        ) {
            throw new ConversationOperationRejected;
        }

        return [
            'status' => 'recorded_recovery',
            'provider_dispatch' => 'denied',
            'recovery_route' => 'recorded_upload',
            'always_on' => false,
            'full_duplex' => false,
        ];
    }

    private function ownedSession(
        string $organizationId,
        string $membershipId,
        string $sessionId,
    ): stdClass {
        $session = DB::table('conversation_sessions')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('id', $sessionId)
            ->first(['id']);

        if (! $session instanceof stdClass) {
            throw new ConversationOperationRejected;
        }

        return $session;
    }

    private function commandKey(
        string $organizationId,
        string $membershipId,
        string $idempotencyKey,
    ): string {
        return 'realtime-stt-cmd:'.$organizationId.':'.$membershipId.':'.$idempotencyKey;
    }

    private function ticketKey(string $organizationId, string $ticketId): string
    {
        return 'realtime-stt-ticket:'.$organizationId.':'.$ticketId;
    }
}
