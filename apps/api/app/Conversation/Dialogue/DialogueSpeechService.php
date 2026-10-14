<?php

declare(strict_types=1);

namespace App\Conversation\Dialogue;

use App\Conversation\Avatar\ConversationSpeechPayloadBuilder;
use App\Conversation\ConversationProviderUsagePolicy;
use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Support\GuidanceTtsPolicy;
use App\Conversation\Voice\VoiceConsentService;
use App\Entitlements\EntitlementAccess;
use App\Entitlements\Exceptions\EntitlementDenied;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * Modern dialogue speech authorizes saved server text, never client narration.
 *
 * @phpstan-import-type ConversationSpeechPayload from ConversationSpeechPayloadBuilder
 */
final readonly class DialogueSpeechService
{
    public function __construct(
        private ConversationDialogueService $dialogue,
        private ConversationRecoveryStore $recovery,
        private TextToSpeechProvider $provider,
        private ConversationProviderUsagePolicy $usagePolicy,
        private ConversationSpeechPayloadBuilder $payloads,
        private EntitlementAccess $entitlements,
        private LearnerAuthority $authority,
        private VoiceConsentService $consents,
    ) {}

    /** @return ConversationSpeechPayload|null Null means a legacy session only. */
    public function synthesizeIfModern(User $learner, string $organizationId, string $membershipId, string $sessionId, string $text, string $purpose): ?array
    {
        if ($this->dialogue->state($organizationId, $membershipId, $sessionId) === null) {
            return null;
        }
        // Commit an at-most-once reservation BEFORE external I/O. If a process
        // dies after dispatch, a retry sees unknown outcome, not a free retry.
        $reservation = DB::transaction(function () use ($learner, $organizationId, $membershipId, $sessionId, $text, $purpose): array {
            $state = $this->lock($learner, $organizationId, $membershipId, $sessionId);
            if (! in_array($purpose, GuidanceTtsPolicy::purposes(), true)) {
                throw new ConversationOperationRejected;
            }
            if ((int) $state->revision > 0) {
                return ['speech' => $this->savedTurnSpeech($state, $text, $purpose), 'call_id' => null];
            }
            $opening = $this->opening($state);
            if ($purpose !== GuidanceTtsPolicy::PURPOSE_MODEL_EN || $text !== $opening) {
                throw new ConversationOperationRejected;
            }
            $call = DB::table('conversation_session_speech_calls')->where('organization_id', $organizationId)
                ->where('learner_membership_id', $membershipId)->where('conversation_session_id', $sessionId)
                ->where('operation', 'roleplay.opener')->first();
            if ($call instanceof stdClass) {
                $saved = is_string($state->opener_speech_artifact_id)
                    ? $this->recovery->read($organizationId, $membershipId, $sessionId, $state->opener_speech_artifact_id) : null;

                return ['speech' => $this->savedPayload($saved['speech'] ?? null, GuidanceTtsPolicy::PURPOSE_MODEL_EN), 'call_id' => null];
            }
            $this->preflightAllowance($learner);
            $id = (string) Str::ulid();
            DB::table('conversation_session_speech_calls')->insert([
                'id' => $id, 'organization_id' => $organizationId, 'learner_membership_id' => $membershipId,
                'conversation_session_id' => $sessionId, 'operation' => 'roleplay.opener', 'status' => 'dispatching',
                'created_at' => Date::now(),
            ]);

            return ['speech' => null, 'call_id' => $id];
        });
        if (is_array($reservation['speech'])) {
            return $reservation['speech'];
        }
        $callId = $reservation['call_id'];

        // No automatic transaction/provider retry: a failed reservation is
        // intentionally text-first until the learner starts a new session.
        return DB::transaction(function () use ($learner, $organizationId, $membershipId, $sessionId, $callId): array {
            $state = $this->lock($learner, $organizationId, $membershipId, $sessionId);
            if ((int) $state->revision !== 0) {
                throw new ConversationOperationRejected;
            }
            $opening = $this->opening($state);
            $this->preflightAllowance($learner);
            $speech = $this->textFirst();
            try {
                $result = $this->provider->synthesize(new TextToSpeechRequest(
                    interactionId: $callId, text: $opening,
                    voice: GuidanceTtsPolicy::voiceFor(GuidanceTtsPolicy::PURPOSE_MODEL_EN, 'en-IN'),
                    purpose: GuidanceTtsPolicy::PURPOSE_MODEL_EN, uiLocale: 'en-IN',
                ));
                $this->usagePolicy->assertAllowed('tts', $result->usage());
                $this->recordUsage($callId, $result->usage(), $result->playbackAvailable ? 'succeeded' : 'text_first');
                if ($result->audioBytes !== null && strlen($result->audioBytes) > 2 * 1024 * 1024) {
                    throw new ConversationOperationRejected;
                }
                $speech = $this->savedPayload($this->payloads->buildForPurpose($result, $opening, GuidanceTtsPolicy::PURPOSE_MODEL_EN), GuidanceTtsPolicy::PURPOSE_MODEL_EN);
            } catch (ConversationProviderException $exception) {
                $usage = new ProviderUsage($exception->providerKey, $exception->modelVersion, $exception->latencyMilliseconds, $exception->inputUnits, $exception->outputUnits, 0);
                try {
                    $this->usagePolicy->assertAllowed('tts', $usage);
                    $this->recordUsage($callId, $usage, 'failed', false);
                } catch (Throwable) {
                    $this->failed($callId);
                }
            } catch (Throwable) {
                $this->failed($callId);
                $speech = $this->textFirst();
            }
            try {
                $artifact = $this->recovery->persist($organizationId, $membershipId, $sessionId, null, ['speech' => $speech]);
                DB::table('conversation_dialogue_states')->where('organization_id', $organizationId)
                    ->where('learner_membership_id', $membershipId)->where('conversation_session_id', $sessionId)
                    ->update(['opener_speech_artifact_id' => $artifact]);
            } catch (ConversationRecoveryUnavailable) {
                // Preserve the durable call/known cost without a second dispatch.
                $speech = $this->textFirst();
            }

            return $speech;
        });
    }

    private function lock(User $learner, string $organizationId, string $membershipId, string $sessionId): stdClass
    {
        $context = $this->authority->require($learner);
        if ($context->organizationId !== $organizationId || $context->membershipId !== $membershipId) {
            throw new ConversationOperationRejected;
        }
        $this->consents->activeEvent($organizationId, $membershipId, VoiceConsentService::PROVIDER);
        $session = DB::table('conversation_sessions')->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)->where('id', $sessionId)->where('status', 'active')->lockForUpdate()->first();
        $state = $this->dialogue->state($organizationId, $membershipId, $sessionId);
        if (! $session instanceof stdClass || ! $state instanceof stdClass || $state->closed_at !== null
            || Date::parse($state->expires_at)->lessThanOrEqualTo(Date::now())
            || $this->recovery->read($organizationId, $membershipId, $sessionId, (string) $state->start_artifact_id) === null) {
            throw new ConversationOperationRejected('conversation_recovery_expired');
        }
        $definition = $this->recovery->read($organizationId, $membershipId, $sessionId, (string) $state->start_artifact_id);
        if (is_array($definition['lesson'] ?? null)) {
            $this->entitlements->assertLevel($learner, (int) $definition['lesson']['level_number']);
            $this->entitlements->assertSessionPart($learner, (string) $definition['lesson']['part_id']);
        } elseif ($state->flow === 'roleplay_dialogue') {
            $this->entitlements->assertRoleplay($learner);
        } else {
            $this->entitlements->assertFreeSpeech($learner);
        }

        return $state;
    }

    private function opening(stdClass $state): string
    {
        $saved = $this->recovery->read((string) $state->organization_id, (string) $state->learner_membership_id,
            (string) $state->conversation_session_id, (string) $state->start_artifact_id);
        $opening = $saved['opening_reply'] ?? null;
        if (! in_array($state->flow, ['roleplay_dialogue', 'free_speech'], true) || ! is_array($opening)
            || ($opening['locale'] ?? null) !== 'en-IN' || ! is_string($opening['text'] ?? null) || trim($opening['text']) === '') {
            throw new ConversationOperationRejected;
        }

        return $opening['text'];
    }

    /** @return ConversationSpeechPayload */
    private function savedTurnSpeech(stdClass $state, string $text, string $purpose): array
    {
        $latest = $this->dialogue->latest($state);
        if (! is_array($latest) || ($latest['status'] ?? null) !== 'completed') {
            throw new ConversationOperationRejected;
        }
        if ($state->flow === 'free_speech' && ($latest['explanation_target'] ?? null) === 'opening'
            && $purpose === GuidanceTtsPolicy::PURPOSE_MODEL_EN && $text === $this->opening($state)) {
            $saved = is_string($state->opener_speech_artifact_id)
                ? $this->recovery->read((string) $state->organization_id, (string) $state->learner_membership_id, (string) $state->conversation_session_id, $state->opener_speech_artifact_id) : null;

            return $this->savedPayload($saved['speech'] ?? null, $purpose);
        }
        if ($state->flow === 'free_speech' && is_string($latest['explanation_target'] ?? null)
            && $latest['explanation_target'] !== 'opening' && $purpose === GuidanceTtsPolicy::PURPOSE_MODEL_EN) {
            $latest = $this->dialogue->replay($state, $latest['explanation_target']);
        }
        $reply = $latest['reply'] ?? null;
        $correction = $latest['priority_correction'] ?? null;
        if ($purpose === GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1) {
            if ($state->flow !== 'roleplay_dialogue' || ! is_array($correction) || ($correction['explanation_l1'] ?? null) !== $text) {
                throw new ConversationOperationRejected;
            }
        } elseif (! is_array($reply) || ($reply['text'] ?? null) !== $text) {
            if (is_array($correction) && ($correction['correction_en'] ?? null) === $text) {
                // A distinct corrected sentence has no separately saved audio.
                return $this->textFirst();
            }
            throw new ConversationOperationRejected;
        } elseif (($reply['locale'] ?? 'en-IN') !== 'en-IN') {
            $accepted = DB::table('conversation_dialogue_outcomes')->where('organization_id', $state->organization_id)
                ->where('learner_membership_id', $state->learner_membership_id)->where('conversation_session_id', $state->conversation_session_id)
                ->where('conversation_turn_id', $state->latest_turn_id)->where('action', 'accept_explanation')->exists();
            if ($state->flow !== 'question_help' || ! $accepted) {
                throw new ConversationOperationRejected;
            }
        }

        return $this->savedPayload($latest['speech'] ?? null, $purpose);
    }

    /** @return ConversationSpeechPayload */
    private function savedPayload(mixed $saved, string $purpose): array
    {
        $payload = $this->textFirst();
        if (! is_array($saved) || ($saved['playback_available'] ?? false) !== true) {
            return $payload;
        }
        $prefix = $purpose === GuidanceTtsPolicy::PURPOSE_MODEL_EN ? 'model' : 'guidance';
        $audio = $saved[$prefix.'_audio_base64'] ?? null;
        $mime = $saved[$prefix.'_audio_mime_type'] ?? null;
        if (! is_string($audio) || $audio === '' || ! is_string($mime) || $mime === '') {
            return $payload;
        }
        $payload['playback_available'] = true;
        $payload['rendering_mode'] = is_string($saved['rendering_mode'] ?? null) ? $saved['rendering_mode'] : 'recorded-model-speech';
        $payload[$prefix.'_audio_base64'] = $audio;
        $payload[$prefix.'_audio_mime_type'] = $mime;
        $payload['guidance_speech_status'] = $prefix === 'guidance' ? 'ready' : 'unavailable';

        return $payload;
    }

    /** @return ConversationSpeechPayload */
    private function textFirst(): array
    {
        return ['playback_available' => false, 'rendering_mode' => 'client-text-to-speech-fallback',
            'model_audio_base64' => null, 'model_audio_mime_type' => null,
            'guidance_audio_base64' => null, 'guidance_audio_mime_type' => null,
            'model_audio_stream_path' => null, 'guidance_audio_stream_path' => null, 'guidance_speech_status' => 'unavailable',
            'model_avatar_viseme_timeline' => null, 'guidance_avatar_viseme_timeline' => null];
    }

    private function preflightAllowance(User $learner): void
    {
        $remaining = $this->entitlements->snapshot($learner)['live_ai_seconds_remaining'];
        if (is_int($remaining) && $remaining <= 0) {
            throw new EntitlementDenied(EntitlementDenied::AI_EXHAUSTED);
        }
    }

    private function recordUsage(string $callId, ProviderUsage $usage, string $status, bool $knownCost = true): void
    {
        DB::table('conversation_session_speech_calls')->where('id', $callId)->update([
            'status' => $status, 'provider_key' => $usage->providerKey, 'model_version' => $usage->modelVersion,
            'latency_ms' => $usage->latencyMilliseconds, 'input_units' => $usage->inputUnits, 'output_units' => $usage->outputUnits,
            'input_unit_kind' => $usage->inputUnitKind, 'output_unit_kind' => $usage->outputUnitKind,
            'estimated_cost_microunits' => $knownCost ? $usage->estimatedCostMicrounits : null,
            'dispatch_kind' => $usage->dispatchKind, 'api_version' => $usage->apiVersion, 'voice_key' => $usage->voiceKey,
            'locale' => $usage->locale, 'region' => $usage->region, 'configuration_sha256' => $usage->configurationSha256,
            'safe_failure_code' => $status === 'failed' ? 'speech_provider_unavailable' : null, 'completed_at' => Date::now(),
        ]);
    }

    private function failed(string $callId): void
    {
        DB::table('conversation_session_speech_calls')->where('id', $callId)->update([
            'status' => 'failed', 'safe_failure_code' => 'speech_provider_unavailable', 'completed_at' => Date::now(),
        ]);
    }
}
