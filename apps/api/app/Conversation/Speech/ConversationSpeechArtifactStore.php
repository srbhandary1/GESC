<?php

declare(strict_types=1);

namespace App\Conversation\Speech;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Providers\TextToSpeechResult;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

final readonly class ConversationSpeechArtifactStore
{
    public const string KIND_MODEL = 'model_en';

    public const string KIND_GUIDANCE = 'guidance_l1';

    /**
     * @return array{stream_path: string, mime_type: string}|null
     */
    public function persist(
        string $organizationId,
        string $membershipId,
        string $turnId,
        string $kind,
        TextToSpeechResult $speech,
    ): ?array {
        $audio = $speech->audioBytes;
        if (! is_string($audio) || $audio === '' || ! $speech->playbackAvailable) {
            return null;
        }

        $maximumBytes = max(1, (int) config(
            'conversation.speech_artifact_maximum_bytes',
            2_000_000,
        ));
        $bytes = strlen($audio);
        if ($bytes > $maximumBytes) {
            throw new ConversationOperationRejected;
        }

        $mime = $speech->mimeType ?? 'audio/wav';
        if ($mime !== 'audio/wav') {
            throw new ConversationOperationRejected;
        }

        $hash = hash('sha256', $audio);
        $path = sprintf(
            'conversation-speech/%s/%s/%s.wav',
            $organizationId,
            $turnId,
            $kind,
        );
        $this->disk()->put($path, $audio);
        $expiresAt = Date::now()->toImmutable()->addDays(max(
            1,
            (int) config('conversation.speech_artifact_retention_days', 30),
        ));
        $now = Date::now()->toImmutable();

        $existing = DB::table('conversation_turn_speech_artifacts')
            ->where('organization_id', $organizationId)
            ->where('conversation_turn_id', $turnId)
            ->where('kind', $kind)
            ->first();
        if ($existing === null) {
            DB::table('conversation_turn_speech_artifacts')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organizationId,
                'conversation_turn_id' => $turnId,
                'learner_membership_id' => $membershipId,
                'kind' => $kind,
                'mime_type' => $mime,
                'byte_size' => $bytes,
                'content_sha256' => $hash,
                'storage_path' => $path,
                'expires_at' => $expiresAt,
                'created_at' => $now,
            ]);
        }

        return [
            'stream_path' => $this->streamPath($turnId, $kind),
            'mime_type' => $mime,
        ];
    }

    public function streamPath(string $turnId, string $kind): string
    {
        return sprintf(
            '/api/v1/conversation-turns/%s/speech/%s',
            $turnId,
            $kind,
        );
    }

    public function find(
        string $organizationId,
        string $membershipId,
        string $turnId,
        string $kind,
    ): ?stdClass {
        $row = DB::table('conversation_turn_speech_artifacts')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('conversation_turn_id', $turnId)
            ->where('kind', $kind)
            ->where('expires_at', '>', Date::now())
            ->first();

        return $row instanceof stdClass ? $row : null;
    }

    private function disk(): Filesystem
    {
        $name = (string) config('conversation.speech_artifact_disk', 'local');

        return Storage::disk($name === '' ? 'local' : $name);
    }
}
