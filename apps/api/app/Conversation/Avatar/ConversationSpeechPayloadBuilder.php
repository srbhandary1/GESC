<?php

declare(strict_types=1);

namespace App\Conversation\Avatar;

use App\Conversation\Providers\TextToSpeechResult;
use App\Conversation\Support\GuidanceTtsPolicy;

/**
 * @phpstan-type AvatarVisemeFrame array{offset_ms: int, viseme_id: int}
 * @phpstan-type AvatarVisemeTimelinePayload array{
 *   source: string,
 *   duration_ms: int,
 *   frames: list<AvatarVisemeFrame>
 * }
 * @phpstan-type ConversationSpeechPayload array{
 *   playback_available: bool,
 *   rendering_mode: string,
 *   model_audio_base64: ?string,
 *   model_audio_mime_type: ?string,
 *   guidance_audio_base64: ?string,
 *   guidance_audio_mime_type: ?string,
 *   model_audio_stream_path: ?string,
 *   guidance_audio_stream_path: ?string,
 *   guidance_speech_status: 'ready'|'deferred'|'unavailable',
 *   model_avatar_viseme_timeline: ?AvatarVisemeTimelinePayload,
 *   guidance_avatar_viseme_timeline: ?AvatarVisemeTimelinePayload
 * }
 */
final readonly class ConversationSpeechPayloadBuilder
{
    public function __construct(
        private AvatarVisemeTimelineEstimator $visemeTimelineEstimator,
    ) {}

    /** @return ConversationSpeechPayload */
    public function build(
        TextToSpeechResult $modelSpeech,
        ?TextToSpeechResult $guidanceSpeech,
        string $modelText,
        ?string $guidanceText = null,
        ?string $modelAudioStreamPath = null,
        ?string $guidanceAudioStreamPath = null,
        string $guidanceSpeechStatus = 'unavailable',
    ): array {
        $status = match ($guidanceSpeechStatus) {
            'ready', 'deferred', 'unavailable' => $guidanceSpeechStatus,
            default => 'unavailable',
        };
        $modelAudio = $modelSpeech->audioBytes;
        $guidanceAudio = $guidanceSpeech?->audioBytes;
        $streamModel = is_string($modelAudioStreamPath) && $modelAudioStreamPath !== '';
        $streamGuidance = is_string($guidanceAudioStreamPath) && $guidanceAudioStreamPath !== '';

        $guidancePlayback = $guidanceSpeech !== null
            && $guidanceSpeech->playbackAvailable;

        $modelMime = $streamModel || (is_string($modelAudio) && $modelAudio !== '')
            ? ($modelSpeech->mimeType ?? 'audio/wav')
            : null;
        $guidanceMime = $streamGuidance || (is_string($guidanceAudio) && $guidanceAudio !== '')
            ? ($guidanceSpeech !== null
                ? ($guidanceSpeech->mimeType ?? 'audio/wav')
                : 'audio/wav')
            : null;

        $modelTimeline = ! $streamModel && is_string($modelAudio) && $modelAudio !== ''
            ? $this->visemeTimelineEstimator
                ->estimate($modelText, $modelAudio)
                ?->toArray()
            : null;
        $guidanceTimeline = ! $streamGuidance
            && is_string($guidanceAudio)
            && $guidanceAudio !== ''
            && is_string($guidanceText)
            && trim($guidanceText) !== ''
            ? $this->visemeTimelineEstimator
                ->estimate($guidanceText, $guidanceAudio)
                ?->toArray()
            : null;

        return [
            'playback_available' => $modelSpeech->playbackAvailable
                || $guidancePlayback
                || $streamModel,
            'rendering_mode' => $modelSpeech->renderingMode,
            'model_audio_base64' => $streamModel
                ? null
                : ((is_string($modelAudio) && $modelAudio !== '')
                    ? base64_encode($modelAudio)
                    : null),
            'model_audio_mime_type' => $modelMime,
            'guidance_audio_base64' => $streamGuidance
                ? null
                : ((is_string($guidanceAudio) && $guidanceAudio !== '')
                    ? base64_encode($guidanceAudio)
                    : null),
            'guidance_audio_mime_type' => $guidanceMime,
            'model_audio_stream_path' => $streamModel ? $modelAudioStreamPath : null,
            'guidance_audio_stream_path' => $streamGuidance ? $guidanceAudioStreamPath : null,
            'guidance_speech_status' => $status,
            'model_avatar_viseme_timeline' => $modelTimeline,
            'guidance_avatar_viseme_timeline' => $guidanceTimeline,
        ];
    }

    /** @return ConversationSpeechPayload */
    public function buildForPurpose(
        TextToSpeechResult $result,
        string $text,
        string $purpose,
    ): array {
        $audio = $result->audioBytes;
        $hasAudio = is_string($audio) && $audio !== '';
        $encoded = $hasAudio ? base64_encode($audio) : null;
        $mime = $hasAudio ? ($result->mimeType ?? 'audio/wav') : null;
        $timeline = $hasAudio
            ? $this->visemeTimelineEstimator->estimate($text, $audio)?->toArray()
            : null;

        $payload = [
            'playback_available' => $result->playbackAvailable,
            'rendering_mode' => $result->renderingMode,
            'model_audio_base64' => null,
            'model_audio_mime_type' => null,
            'guidance_audio_base64' => null,
            'guidance_audio_mime_type' => null,
            'model_audio_stream_path' => null,
            'guidance_audio_stream_path' => null,
            'guidance_speech_status' => 'unavailable',
            'model_avatar_viseme_timeline' => null,
            'guidance_avatar_viseme_timeline' => null,
        ];

        if ($purpose === GuidanceTtsPolicy::PURPOSE_MODEL_EN) {
            $payload['model_audio_base64'] = $encoded;
            $payload['model_audio_mime_type'] = $mime;
            $payload['model_avatar_viseme_timeline'] = $timeline;
        } else {
            $payload['guidance_audio_base64'] = $encoded;
            $payload['guidance_audio_mime_type'] = $mime;
            $payload['guidance_avatar_viseme_timeline'] = $timeline;
        }

        return $payload;
    }
}
