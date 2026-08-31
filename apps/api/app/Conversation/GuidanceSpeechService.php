<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Support\GuidanceTtsPolicy;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class GuidanceSpeechService
{
    public function __construct(
        private ConversationEnvironmentGate $environment,
        private LearnerAuthority $authority,
        private ConversationProviderUsagePolicy $usagePolicy,
        private TextToSpeechProvider $textToSpeech,
    ) {}

    /**
     * @return array{
     *   playback_available: bool,
     *   rendering_mode: string,
     *   model_audio_base64: ?string,
     *   model_audio_mime_type: ?string,
     *   guidance_audio_base64: ?string,
     *   guidance_audio_mime_type: ?string,
     *   animation_payload: ?array<string, mixed>
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

        return $this->payload($result, $purpose, $utterance);
    }

    /**
     * @return array{
     *   playback_available: bool,
     *   rendering_mode: string,
     *   model_audio_base64: ?string,
     *   model_audio_mime_type: ?string,
     *   guidance_audio_base64: ?string,
     *   guidance_audio_mime_type: ?string,
     *   animation_payload: ?array<string, mixed>
     * }
     */
    private function payload(
        TextToSpeechResult $result,
        string $purpose,
        string $utterance,
    ): array {
        $audio = $result->audioBytes;
        $hasAudio = is_string($audio) && $audio !== '';
        $encoded = $hasAudio ? base64_encode($audio) : null;
        $mime = $hasAudio ? ($result->mimeType ?? 'audio/wav') : null;

        // Build animation payload if visemes are available
        $animationPayload = null;
        if ($hasAudio && ! empty($result->visemeTimestamps)) {
            $animationPayload = [
                'viseme_timestamps' => $result->visemeTimestamps,
                'avatar_gesture_trigger' => $this->gestureFor(
                    $purpose,
                    $utterance,
                ),
            ];
        }

        return [
            'playback_available' => $result->playbackAvailable,
            'rendering_mode' => $result->renderingMode,
            'model_audio_base64' => $purpose === GuidanceTtsPolicy::PURPOSE_MODEL_EN
                ? $encoded
                : null,
            'model_audio_mime_type' => $purpose === GuidanceTtsPolicy::PURPOSE_MODEL_EN
                ? $mime
                : null,
            'guidance_audio_base64' => $purpose === GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1
                ? $encoded
                : null,
            'guidance_audio_mime_type' => $purpose === GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1
                ? $mime
                : null,
            'animation_payload' => $animationPayload,
        ];
    }

    private function gestureFor(string $purpose, string $utterance): string
    {
        if (str_ends_with($utterance, '?')) {
            return 'ATTENTIVE_LEAN';
        }

        return $purpose === GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1
            ? 'ENCOURAGING_NOD'
            : 'WARM_SMILE';
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

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new ConversationOperationRejected;
        }

        return $value;
    }
}
