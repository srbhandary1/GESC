<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Avatar\ConversationSpeechPayloadBuilder;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Support\GuidanceTtsPolicy;
use App\Conversation\Support\SpokenGrammarNotation;
use App\Entitlements\EntitlementAccess;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use stdClass;

final readonly class GuidanceSpeechService
{
    public function __construct(
        private ConversationEnvironmentGate $environment,
        private LearnerAuthority $authority,
        private ConversationProviderUsagePolicy $usagePolicy,
        private TextToSpeechProvider $textToSpeech,
        private EntitlementAccess $entitlements,
        private ConversationSpeechPayloadBuilder $speechPayloadBuilder,
    ) {}

    /**
     * @return array{
     *   playback_available: bool,
     *   rendering_mode: string,
     *   model_audio_base64: ?string,
     *   model_audio_mime_type: ?string,
     *   guidance_audio_base64: ?string,
     *   guidance_audio_mime_type: ?string
     * }
     */
    public function synthesize(
        User $learner,
        string $sessionId,
        string $text,
        string $purpose,
    ): array {
        $this->environment->requireAuthorizedConversationProvider();
        $utterance = trim($text);
        $maximumCharacters = min(
            (int) config('conversation.maximum_utterance_characters', 500),
            (int) config('conversation.guidance_tts.maximum_characters', 400),
        );

        if (
            $utterance === ''
            || ! in_array($purpose, GuidanceTtsPolicy::purposes(), true)
            || preg_match('//u', $utterance) !== 1
            || mb_strlen($utterance) > $maximumCharacters
        ) {
            throw new ConversationOperationRejected;
        }

        try {
            $context = $this->authority->require($learner);
        } catch (LearningOperationRejected) {
            throw new ConversationOperationRejected;
        }

        $membershipId = (string) $context->membershipId;
        $session = $this->session(
            organizationId: $context->organizationId,
            membershipId: $membershipId,
            sessionId: $sessionId,
        );
        $utterance = SpokenGrammarNotation::forSpeech(
            $utterance,
            $purpose === GuidanceTtsPolicy::PURPOSE_MODEL_EN
                && $this->sessionUsesGrammarDrill(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $sessionId,
                ),
        );
        $this->entitlements->consumeLiveAi(
            $learner,
            hash('sha256', $sessionId."\0".$purpose."\0".$utterance),
            (int) config('billing.live_ai_speech_seconds', 5),
        );
        $result = $this->textToSpeech->synthesize(new TextToSpeechRequest(
            interactionId: (string) Str::ulid(),
            text: $utterance,
            voice: GuidanceTtsPolicy::voiceFor(
                $purpose,
                $this->string($session, 'ui_locale'),
            ),
            purpose: $purpose,
            uiLocale: $this->string($session, 'ui_locale'),
        ));
        $this->usagePolicy->assertAllowed('tts', $result->usage());

        return $this->speechPayloadBuilder->buildForPurpose(
            result: $result,
            text: $utterance,
            purpose: $purpose,
        );
    }

    private function session(
        string $organizationId,
        string $membershipId,
        string $sessionId,
    ): stdClass {
        $session = DB::table('conversation_sessions')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('id', $sessionId)
            ->where('status', 'active')
            ->first(['id', 'ui_locale']);

        if (! $session instanceof stdClass) {
            throw new ConversationOperationRejected;
        }

        return $session;
    }

    private function sessionUsesGrammarDrill(
        string $organizationId,
        string $membershipId,
        string $sessionId,
    ): bool {
        if (! Schema::hasTable('conversation_lesson_drills')) {
            return false;
        }

        return DB::table('conversation_lesson_drills')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('conversation_session_id', $sessionId)
            ->exists();
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
