<?php

declare(strict_types=1);

namespace App\Conversation\Observability;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class SpeechLatencyTelemetryService
{
    public function __construct(private LearnerAuthority $authority) {}

    /**
     * @param array{
     *   client_runtime: string,
     *   network_class: string,
     *   playback_outcome: string,
     *   release_to_ack_ms: int|null,
     *   release_to_first_signal_ms: int|null,
     *   release_to_first_audio_ms: int|null,
     *   turn_json_to_first_audio_ms: int|null
     * } $sample
     * @return array<string, mixed>
     */
    public function record(
        User $learner,
        string $turnId,
        string $idempotencyKey,
        array $sample,
    ): array {
        $context = $this->authority->require($learner);
        $membershipId = (string) $context->membershipId;
        $policy = (string) config(
            'speech_observability.client_sample_policy_version',
            'speech-first-audible-v1',
        );
        $sampleRate = min(10_000, max(1, (int) config(
            'speech_observability.client_sample_rate_bps',
            10_000,
        )));
        $turn = DB::table('conversation_turns as turns')
            ->join('conversation_sessions as sessions', function ($join): void {
                $join->on('sessions.organization_id', '=', 'turns.organization_id')
                    ->on('sessions.id', '=', 'turns.conversation_session_id');
            })
            ->where('turns.organization_id', $context->organizationId)
            ->where('turns.learner_membership_id', $membershipId)
            ->where('turns.id', $turnId)
            ->first([
                'turns.id',
                'turns.conversation_session_id',
                'turns.learner_membership_id',
                'sessions.mode as conversation_mode',
            ]);

        if (! $turn instanceof stdClass) {
            throw new ConversationOperationRejected;
        }

        if (! $this->selected($context->organizationId, $turnId, $policy, $sampleRate)) {
            return [
                'accepted' => false,
                'sampled' => false,
                'sample_policy_version' => $policy,
                'retention_days' => (int) config(
                    'speech_observability.client_sample_retention_days',
                    30,
                ),
            ];
        }

        $payloadHash = hash('sha256', json_encode(
            [$policy, $sample],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));

        return DB::transaction(function () use (
            $context,
            $membershipId,
            $turn,
            $turnId,
            $idempotencyKey,
            $sample,
            $policy,
            $sampleRate,
            $payloadHash,
        ): array {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select(
                    'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    [implode('|', [
                        'speech-client-latency',
                        $context->organizationId,
                        $turnId,
                        $policy,
                    ])],
                );
                DB::select(
                    'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    [implode('|', [
                        'speech-client-latency-idempotency',
                        $context->organizationId,
                        $idempotencyKey,
                    ])],
                );
            }

            $existing = DB::table('speech_client_latency_samples')
                ->where('organization_id', $context->organizationId)
                ->where(function ($query) use (
                    $idempotencyKey,
                    $turnId,
                    $policy,
                ): void {
                    $query->where('idempotency_key', $idempotencyKey)
                        ->orWhere(function ($turnQuery) use ($turnId, $policy): void {
                            $turnQuery
                                ->where('conversation_turn_id', $turnId)
                                ->where('sample_policy_version', $policy);
                        });
                })
                ->lockForUpdate()
                ->first(['id', 'payload_sha256']);

            if ($existing instanceof stdClass) {
                if (! hash_equals((string) $existing->payload_sha256, $payloadHash)) {
                    throw new SpeechTelemetryIdempotencyConflict;
                }

                return $this->result($policy, true, true);
            }

            $now = Date::now()->toImmutable();
            DB::table('speech_client_latency_samples')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $context->organizationId,
                'conversation_turn_id' => $turnId,
                'conversation_session_id' => (string) $turn->conversation_session_id,
                'learner_membership_id' => $membershipId,
                'idempotency_key' => $idempotencyKey,
                'payload_sha256' => $payloadHash,
                'sample_policy_version' => $policy,
                'sample_rate_bps' => $sampleRate,
                'environment' => app()->environment(),
                'conversation_mode' => (string) $turn->conversation_mode,
                'client_runtime' => $sample['client_runtime'],
                'network_class' => $sample['network_class'],
                'playback_outcome' => $sample['playback_outcome'],
                'release_to_ack_ms' => $sample['release_to_ack_ms'],
                'release_to_first_signal_ms' => $sample['release_to_first_signal_ms'],
                'release_to_first_audio_ms' => $sample['release_to_first_audio_ms'],
                'turn_json_to_first_audio_ms' => $sample['turn_json_to_first_audio_ms'],
                'recorded_at' => $now,
                'expires_at' => $now->addDays((int) config(
                    'speech_observability.client_sample_retention_days',
                    30,
                )),
            ]);

            return $this->result($policy, true, false);
        }, 3);
    }

    private function selected(
        string $organizationId,
        string $turnId,
        string $policy,
        int $sampleRate,
    ): bool {
        $bucket = (int) hexdec(substr(hash(
            'sha256',
            $policy.'|'.$organizationId.'|'.$turnId,
        ), 0, 8)) % 10_000;

        return $bucket < $sampleRate;
    }

    /** @return array<string, mixed> */
    private function result(string $policy, bool $accepted, bool $replayed): array
    {
        return [
            'accepted' => $accepted,
            'sampled' => true,
            'replayed' => $replayed,
            'sample_policy_version' => $policy,
            'retention_days' => (int) config(
                'speech_observability.client_sample_retention_days',
                30,
            ),
        ];
    }
}
