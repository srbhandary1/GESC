<?php

declare(strict_types=1);

namespace App\Conversation\Metering;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Voice\PcmWavInspector;
use App\Conversation\Voice\VoiceOperationRejected;
use App\Entitlements\EntitlementAccess;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

/** Content-free, scoped playback progress. Never treats generated duration as listening. */
final readonly class ConversationPlaybackMeter
{
    public function __construct(private LearnerAuthority $authority, private EntitlementAccess $entitlements, private PcmWavInspector $wav) {}

    /**
     * @param  array<string, mixed>  $speech
     * @return array<string, mixed>
     */
    public function decorate(User $learner, array $speech): array
    {
        $context = $this->authority->require($learner);
        foreach (['model', 'guidance'] as $kind) {
            $audio = isset($speech[$kind.'_audio_base64']) && is_string($speech[$kind.'_audio_base64'])
                ? base64_decode($speech[$kind.'_audio_base64'], true) : false;
            $path = $speech[$kind.'_audio_stream_path'] ?? null;
            if ($audio === false && is_string($path) && preg_match('#\A/api/v1/conversation-turns/([0-9A-Z]{26})/speech/(model_en|guidance_l1)\z#', $path, $match)) {
                $row = DB::table('conversation_turn_speech_artifacts')->where('organization_id', $context->organizationId)
                    ->where('learner_membership_id', $context->membershipId)->where('conversation_turn_id', $match[1])
                    ->where('kind', $match[2])->where('expires_at', '>', Date::now())->first();
                if ($row instanceof stdClass) {
                    $audio = Storage::disk((string) config('conversation.speech_artifact_disk', 'local'))->get((string) $row->storage_path);
                }
            }
            if (! is_string($audio) || $audio === '') {
                continue;
            }
            try {
                $duration = $this->wav->inspect($audio, 120000)->durationMs;
            } catch (VoiceOperationRejected) {
                continue;
            }
            $speech[$kind.'_playback_grant'] = Crypt::encryptString(json_encode([
                'organization' => $context->organizationId, 'membership' => $context->membershipId,
                'duration_ms' => $duration, 'expires' => Date::now()->addMinutes(15)->getTimestamp(),
                'nonce' => (string) Str::ulid(),
            ], JSON_THROW_ON_ERROR));
        }

        return $speech;
    }

    /** @return array{played_ms: int, charged_seconds: int, allowed_ms: int, remaining_seconds: ?int} */
    public function progress(User $learner, string $grant, string $key, int $playedMs): array
    {
        $context = $this->authority->require($learner);
        try {
            $claims = json_decode(Crypt::decryptString($grant), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw new ConversationOperationRejected;
        }
        if (! is_array($claims) || ($claims['organization'] ?? null) !== $context->organizationId
            || ($claims['membership'] ?? null) !== $context->membershipId
            || ! is_int($claims['expires'] ?? null) || $claims['expires'] < Date::now()->getTimestamp()
            || ! is_int($claims['duration_ms'] ?? null) || $claims['duration_ms'] < 1 || $claims['duration_ms'] > 120000
            || ! preg_match('/\A[a-zA-Z0-9_-]{16,64}\z/', $key) || $playedMs < 0 || $playedMs > $claims['duration_ms']) {
            throw new ConversationOperationRejected;
        }

        return DB::transaction(function () use ($learner, $context, $claims, $grant, $key, $playedMs): array {
            // Same per-student lock order as recording/entitlement consumption.
            DB::table('memberships')->where('organization_id', $context->organizationId)->where('id', $context->membershipId)->lockForUpdate()->first();
            $query = DB::table('conversation_playback_meters')->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $context->membershipId)->where('playback_key', $key);
            $row = (clone $query)->lockForUpdate()->first();
            $snapshot = $this->entitlements->snapshot($learner);
            $remaining = $snapshot['live_ai_seconds_remaining'];
            $remaining = is_int($remaining) ? $remaining : null;
            if (! $row instanceof stdClass) {
                if ($playedMs !== 0) {
                    throw new ConversationOperationRejected;
                }
                $this->entitlements->assertLiveAiAvailable($learner);
                DB::table('conversation_playback_meters')->insert([
                    'id' => (string) Str::ulid(), 'organization_id' => $context->organizationId,
                    'learner_membership_id' => $context->membershipId, 'playback_key' => $key,
                    'grant_digest' => hash('sha256', $grant), 'duration_ms' => $claims['duration_ms'],
                    'played_ms' => 0, 'charged_seconds' => 0, 'started_at' => Date::now(),
                    'expires_at' => Date::now()->addMinutes(15),
                ]);

                return ['played_ms' => 0, 'charged_seconds' => 0, 'allowed_ms' => min($claims['duration_ms'], ($remaining ?? 120) * 1000), 'remaining_seconds' => $remaining];
            }
            if (! hash_equals((string) $row->grant_digest, hash('sha256', $grant))
                || Date::parse($row->expires_at)->lessThanOrEqualTo(Date::now())) {
                throw new ConversationOperationRejected;
            }
            $elapsed = max(0, (int) Date::parse($row->started_at)->diffInMilliseconds(Date::now()));
            if ($playedMs > $elapsed + 1500) {
                throw new ConversationOperationRejected;
            }
            $seconds = max((int) $row->charged_seconds, (int) ceil($playedMs / 1000));
            $delta = min($seconds - (int) $row->charged_seconds, $remaining ?? 120);
            if ($delta > 0) {
                $this->entitlements->consumeLiveAi($learner, hash('sha256', 'listening:'.$row->id.':'.$seconds), $delta);
            }
            $charged = (int) $row->charged_seconds + $delta;
            $accepted = max((int) $row->played_ms, min($playedMs, $charged * 1000));
            $query->update(['played_ms' => $accepted, 'charged_seconds' => $charged]);
            $left = $remaining === null ? null : max(0, $remaining - $delta);

            return ['played_ms' => $accepted, 'charged_seconds' => $charged,
                'allowed_ms' => min((int) $row->duration_ms, ($charged + ($left ?? 120)) * 1000), 'remaining_seconds' => $left];
        });
    }
}
