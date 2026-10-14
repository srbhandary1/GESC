<?php

declare(strict_types=1);

namespace App\Conversation\Voice;

use App\Conversation\ConversationCommandIdempotency;
use App\Conversation\Dialogue\ConversationRecoveryStore;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class VoiceConsentService
{
    public const string UPLOAD = 'voice_upload_and_transcription';

    public const string PROVIDER = 'conversation_provider_processing';

    public const string HISTORY = 'transcript_history';

    public function __construct(
        private LearnerAuthority $authority,
        private ConversationCommandIdempotency $commands,
        private VoiceFeatureGate $gate,
        private ConversationRecoveryStore $recovery,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function notice(User $learner, string $requestedLocale): array
    {
        $context = $this->authority->require($learner);
        $this->gate->requireEnabled($context->organizationId);
        $this->requireAdult($context->organizationId, (string) $context->membershipId);
        $resolvedLocale = in_array(
            $requestedLocale,
            config('voice.notice_locales', []),
            true,
        ) ? $requestedLocale : 'en-IN';
        $notices = config('voice.notices', []);
        $text = is_array($notices) ? ($notices[$resolvedLocale] ?? null) : null;

        if (! is_string($text) || $text === '') {
            throw new VoiceOperationRejected;
        }

        $policyVersion = (string) config('voice.policy_version');
        $noticeRevision = (string) config('voice.notice_revision');

        return [
            'history_optional' => true,
            'notice' => $text,
            'notice_revision' => $noticeRevision,
            'notice_sha256' => hash(
                'sha256',
                $policyVersion."\0".$noticeRevision."\0"
                    .$resolvedLocale."\0".$text,
            ),
            'policy_version' => $policyVersion,
            'purposes' => [self::UPLOAD, self::PROVIDER, self::HISTORY],
            'requested_locale' => $requestedLocale,
            'resolved_locale' => $resolvedLocale,
            'review_status' => 'implementation_draft',
        ];
    }

    /**
     * @return array{consents: list<array<string, mixed>>, notice: array<string, mixed>}
     */
    public function listing(User $learner, string $requestedLocale): array
    {
        $context = $this->authority->require($learner);
        $membershipId = (string) $context->membershipId;
        $consents = [];

        foreach ($this->purposes() as $purpose) {
            $row = $this->latest(
                $context->organizationId,
                $membershipId,
                $purpose,
            );
            $consents[] = [
                'purpose' => $purpose,
                'state' => $row instanceof stdClass
                    ? $this->string($row, 'state')
                    : 'not_granted',
                'effective_at' => $row?->effective_at,
            ];
        }

        return [
            'consents' => $consents,
            'notice' => $this->notice($learner, $requestedLocale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function grant(
        User $learner,
        string $purpose,
        string $requestedLocale,
        string $idempotencyKey,
    ): array {
        return $this->record(
            learner: $learner,
            purpose: $purpose,
            state: 'granted',
            requestedLocale: $requestedLocale,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function withdraw(
        User $learner,
        string $purpose,
        string $requestedLocale,
        string $idempotencyKey,
    ): array {
        $result = $this->record(
            learner: $learner,
            purpose: $purpose,
            state: 'withdrawn',
            requestedLocale: $requestedLocale,
            idempotencyKey: $idempotencyKey,
        );

        if ($purpose === self::PROVIDER) {
            $context = $this->authority->require($learner);
            // Run after the consent commit, including command replays. A
            // storage outage must never restore withdrawn processing consent.
            $this->recovery->deleteMembership($context->organizationId, (string) $context->membershipId);
        }

        return $result;
    }

    public function activeEvent(
        string $organizationId,
        string $membershipId,
        string $purpose,
    ): stdClass {
        $row = $this->latest($organizationId, $membershipId, $purpose);

        if (
            ! $row instanceof stdClass
            || $this->string($row, 'state') !== 'granted'
        ) {
            throw new VoiceOperationRejected;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function record(
        User $learner,
        string $purpose,
        string $state,
        string $requestedLocale,
        string $idempotencyKey,
    ): array {
        if (! in_array($purpose, $this->purposes(), true)) {
            throw new VoiceOperationRejected;
        }

        $this->commands->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $learner,
            $purpose,
            $state,
            $requestedLocale,
            $idempotencyKey,
        ): array {
            $context = $this->authority->require($learner);
            $this->gate->requireEnabled($context->organizationId);
            $membershipId = (string) $context->membershipId;
            $this->requireAdult($context->organizationId, $membershipId);
            $notice = $this->notice($learner, $requestedLocale);
            $operation = 'voice.consent.'.$state;
            $fingerprint = $this->commands->fingerprint([
                'notice_revision' => $notice['notice_revision'],
                'purpose' => $purpose,
                'requested_locale' => $requestedLocale,
                'state' => $state,
            ]);
            $replay = $this->commands->replay(
                actor: $learner,
                operation: $operation,
                aggregateType: 'membership',
                aggregateId: $membershipId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay instanceof stdClass) {
                return $this->present(
                    $this->event(
                        $context->organizationId,
                        $membershipId,
                        $this->string($replay, 'result_id'),
                    ),
                    true,
                );
            }

            $now = Date::now()->toImmutable();
            $id = (string) Str::ulid();
            DB::table('voice_consent_events')->insert([
                'id' => $id,
                'organization_id' => $context->organizationId,
                'learner_membership_id' => $membershipId,
                'actor_user_id' => $learner->getKey(),
                'purpose' => $purpose,
                'policy_version' => $notice['policy_version'],
                'notice_revision' => $notice['notice_revision'],
                'notice_sha256' => $notice['notice_sha256'],
                'requested_locale' => $requestedLocale,
                'resolved_locale' => $notice['resolved_locale'],
                'state' => $state,
                'interaction_id' => $idempotencyKey,
                'effective_at' => $now,
                'created_at' => $now,
            ]);
            $this->commands->record(
                actor: $learner,
                operation: $operation,
                aggregateType: 'membership',
                aggregateId: $membershipId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'voice_consent_event',
                resultId: $id,
                completedAt: $now,
            );

            return $this->present(
                $this->event($context->organizationId, $membershipId, $id),
                false,
            );
        }, 3);
    }

    private function requireAdult(
        string $organizationId,
        string $membershipId,
    ): void {
        if (! DB::table('learner_profiles')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->whereNotNull('adult_confirmed_at')
            ->whereNotNull('onboarding_completed_at')
            ->exists()
        ) {
            throw new VoiceOperationRejected;
        }
    }

    private function latest(
        string $organizationId,
        string $membershipId,
        string $purpose,
    ): ?stdClass {
        return DB::table('voice_consent_events')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('purpose', $purpose)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    private function event(
        string $organizationId,
        string $membershipId,
        string $id,
    ): stdClass {
        $row = DB::table('voice_consent_events')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('id', $id)
            ->first();

        if (! $row instanceof stdClass) {
            throw new VoiceOperationRejected;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(stdClass $row, bool $replayed): array
    {
        return [
            'effective_at' => $row->effective_at,
            'id' => $this->string($row, 'id'),
            'notice_revision' => $this->string($row, 'notice_revision'),
            'notice_sha256' => $this->string($row, 'notice_sha256'),
            'policy_version' => $this->string($row, 'policy_version'),
            'purpose' => $this->string($row, 'purpose'),
            'replayed' => $replayed,
            'requested_locale' => $this->string($row, 'requested_locale'),
            'resolved_locale' => $this->string($row, 'resolved_locale'),
            'state' => $this->string($row, 'state'),
        ];
    }

    /**
     * @return list<string>
     */
    private function purposes(): array
    {
        return [self::UPLOAD, self::PROVIDER, self::HISTORY];
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new VoiceOperationRejected;
        }

        return $value;
    }
}
