<?php

declare(strict_types=1);

namespace App\Conversation\Voice;

use App\Conversation\ConversationCommandIdempotency;
use App\Conversation\Observability\ProviderAttemptObservationRecorder;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
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
use Throwable;

final readonly class VoiceRecordingService
{
    public function __construct(
        private LearnerAuthority $authority,
        private VoiceFeatureGate $gate,
        private VoiceConsentService $consents,
        private ConversationCommandIdempotency $commands,
        private PcmWavInspector $wav,
        private RecordedSpeechToTextProvider $speechToText,
        private VoiceDeletionService $deletions,
        private EntitlementAccess $entitlements,
        private ProviderAttemptObservationRecorder $observations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function create(
        User $learner,
        string $sessionId,
        string $audioBytes,
        string $declaredMimeType,
        string $idempotencyKey,
    ): array {
        $this->commands->assertIdempotencyKey($idempotencyKey);
        $context = $this->authority->require($learner);
        $membershipId = (string) $context->membershipId;
        $this->gate->requireEnabled($context->organizationId);

        if (
            $declaredMimeType !== 'audio/wav'
            && $declaredMimeType !== 'audio/x-wav'
            && $declaredMimeType !== 'audio/wave'
        ) {
            throw new VoiceOperationRejected;
        }

        $metadata = $this->wav->inspect($audioBytes);
        $digest = hash('sha256', $audioBytes);
        $fingerprint = $this->commands->fingerprint([
            'audio_sha256' => $digest,
            'byte_size' => $metadata->byteSize,
            'conversation_session_id' => $sessionId,
        ]);
        $existing = DB::transaction(function () use (
            $learner,
            $sessionId,
            $fingerprint,
            $idempotencyKey,
            $context,
            $membershipId,
        ): ?stdClass {
            $this->requireActiveModernSession($context->organizationId, $membershipId, $sessionId);
            $replay = $this->commands->replay(
                actor: $learner,
                operation: 'voice.recording.create',
                aggregateType: 'conversation_session',
                aggregateId: $sessionId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            return $replay instanceof stdClass
                ? $this->owned(
                    $learner,
                    $this->string($replay, 'result_id'),
                    lock: false,
                )
                : null;
        });

        if ($existing instanceof stdClass) {
            return $this->present($existing, true);
        }

        $recordingId = (string) Str::ulid();
        $objectKey = 'voice/'.Str::random(64).'/'.Str::random(64).'.enc';
        $disk = (string) config('voice.storage_disk', 'local');
        $stored = false;

        try {
            DB::transaction(function () use (
                $learner,
                $sessionId,
                $membershipId,
                $context,
                $recordingId,
                $objectKey,
                $disk,
                $audioBytes,
                $digest,
                $metadata,
                $idempotencyKey,
                $fingerprint,
                &$stored,
            ): void {
                $lockedContext = $this->authority->require($learner);

                if (
                    $lockedContext->organizationId
                        !== $context->organizationId
                    || $lockedContext->membershipId !== $membershipId
                ) {
                    throw new VoiceOperationRejected;
                }

                $uploadConsent = $this->consents->activeEvent(
                    $context->organizationId,
                    $membershipId,
                    VoiceConsentService::UPLOAD,
                );
                $providerConsent = $this->consents->activeEvent(
                    $context->organizationId,
                    $membershipId,
                    VoiceConsentService::PROVIDER,
                );
                $session = DB::table('conversation_sessions')
                    ->where('organization_id', $context->organizationId)
                    ->where('learner_membership_id', $membershipId)
                    ->where('id', $sessionId)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first(['id']);

                if (! $session instanceof stdClass) {
                    throw new VoiceOperationRejected;
                }
                $this->requireActiveModernSession($context->organizationId, $membershipId, $sessionId);
                $this->entitlements->consumeLiveAi(
                    $learner,
                    $idempotencyKey,
                    max(1, (int) ceil($metadata->durationMs / 1000)),
                );

                $now = Date::now()->toImmutable();
                $ciphertext = Crypt::encryptString(base64_encode($audioBytes));

                if (! Storage::disk($disk)->put($objectKey, $ciphertext)) {
                    throw new VoiceOperationRejected;
                }
                $stored = true;

                DB::table('voice_recordings')->insert([
                    'id' => $recordingId,
                    'organization_id' => $context->organizationId,
                    'conversation_session_id' => $sessionId,
                    'learner_membership_id' => $membershipId,
                    'upload_consent_event_id' => $this->string(
                        $uploadConsent,
                        'id',
                    ),
                    'provider_consent_event_id' => $this->string(
                        $providerConsent,
                        'id',
                    ),
                    'status' => 'processing',
                    'source_object_key' => $objectKey,
                    'source_sha256' => $digest,
                    'byte_size' => $metadata->byteSize,
                    'duration_ms' => $metadata->durationMs,
                    'mime_type' => $metadata->mimeType,
                    'codec' => $metadata->codec,
                    'sample_rate_hz' => $metadata->sampleRateHz,
                    'channels' => $metadata->channels,
                    'transcript_ciphertext' => null,
                    'transcript_candidate_sha256' => null,
                    'transcript_confidence_bps' => null,
                    'transcript_action' => null,
                    'source_delete_by' => $now->addMinutes(
                        (int) config('voice.source_delete_minutes', 60),
                    ),
                    'source_deleted_at' => null,
                    'transcript_expires_at' => null,
                    'completed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->commands->record(
                    actor: $learner,
                    operation: 'voice.recording.create',
                    aggregateType: 'conversation_session',
                    aggregateId: $sessionId,
                    idempotencyKey: $idempotencyKey,
                    fingerprint: $fingerprint,
                    resultType: 'voice_recording',
                    resultId: $recordingId,
                    completedAt: $now,
                );
            }, 3);
        } catch (Throwable $error) {
            if ($stored) {
                Storage::disk($disk)->delete($objectKey);
            }
            throw $error;
        }

        return $this->process(
            learner: $learner,
            recordingId: $recordingId,
            audioBytes: $audioBytes,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function show(User $learner, string $recordingId): array
    {
        return DB::transaction(function () use ($learner, $recordingId): array {
            $row = $this->owned($learner, $recordingId);
            $this->requireActiveModernSession((string) $row->organization_id, (string) $row->learner_membership_id, (string) $row->conversation_session_id);

            return $this->present($this->owned($learner, $recordingId, lock: true), false);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function review(
        User $learner,
        string $recordingId,
        string $action,
        ?string $correctedTranscript,
        string $idempotencyKey,
    ): array {
        $this->commands->assertIdempotencyKey($idempotencyKey);

        if (! in_array($action, ['accept', 'correct', 'discard'], true)) {
            throw new VoiceOperationRejected;
        }

        return DB::transaction(function () use (
            $learner,
            $recordingId,
            $action,
            $correctedTranscript,
            $idempotencyKey,
        ): array {
            $context = $this->authority->require($learner);
            $unlocked = $this->owned($learner, $recordingId);
            $this->requireActiveModernSession((string) $unlocked->organization_id, (string) $unlocked->learner_membership_id, (string) $unlocked->conversation_session_id);
            $row = $this->owned($learner, $recordingId, lock: true);
            $fingerprint = $this->commands->fingerprint([
                'action' => $action,
                'corrected_transcript' => $correctedTranscript,
            ]);
            $replay = $this->commands->replay(
                actor: $learner,
                operation: 'voice.transcript.review',
                aggregateType: 'voice_recording',
                aggregateId: $recordingId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay instanceof stdClass) {
                return $this->present(
                    $this->owned($learner, $recordingId, lock: false),
                    true,
                );
            }

            if ($this->string($row, 'status') !== 'awaiting_learner_confirmation') {
                throw new VoiceOperationRejected;
            }

            $candidate = $this->transcript($row);
            $selected = null;
            $status = 'discarded';

            if ($action === 'accept') {
                $selected = $candidate;
                $status = 'confirmed';
            } elseif ($action === 'correct') {
                $selected = trim((string) $correctedTranscript);
                $status = 'corrected';

                if (
                    $selected === ''
                    || preg_match('//u', $selected) !== 1
                    || mb_strlen($selected) > 500
                ) {
                    throw new VoiceOperationRejected;
                }
            }

            $history = $this->historyActive(
                $context->organizationId,
                (string) $context->membershipId,
            );
            $now = Date::now()->toImmutable();
            DB::table('voice_recordings')
                ->where('organization_id', $context->organizationId)
                ->where('learner_membership_id', $context->membershipId)
                ->where('id', $recordingId)
                ->update([
                    'status' => $status,
                    'transcript_ciphertext' => $selected !== null && $history
                        ? Crypt::encryptString($selected)
                        : null,
                    'transcript_action' => $action,
                    'confirmed_transcript_sha256' => $selected === null ? null : hash('sha256', $selected),
                    'transcript_expires_at' => $selected !== null && $history
                        ? $now->addDays((int) config('voice.history_days', 30))
                        : $now,
                    'completed_at' => $now,
                    'updated_at' => $now,
                ]);
            $this->commands->record(
                actor: $learner,
                operation: 'voice.transcript.review',
                aggregateType: 'voice_recording',
                aggregateId: $recordingId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'voice_recording',
                resultId: $recordingId,
                completedAt: $now,
            );
            $result = $this->present(
                $this->owned($learner, $recordingId, lock: false),
                false,
            );
            $result['transcript'] = $selected;
            $result['transcript_original'] = ($result['transcript_source'] ?? null) === 'original_speech' ? $selected : null;
            $result['english_intent_text'] = ($result['transcript_source'] ?? null) === 'translated_speech' ? $selected : null;

            return $result;
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(
        User $learner,
        string $recordingId,
        string $reason = 'learner_request',
    ): array {
        $context = $this->authority->require($learner);
        $request = $this->deletions->requestAndProcess(
            organizationId: $context->organizationId,
            membershipId: (string) $context->membershipId,
            recordingId: $recordingId,
            reason: $reason,
        );

        return $this->presentDeletion($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function deletion(User $learner, string $recordingId): array
    {
        $row = $this->owned($learner, $recordingId);
        $request = DB::table('voice_deletion_requests')
            ->where('organization_id', $row->organization_id)
            ->where('learner_membership_id', $row->learner_membership_id)
            ->where('voice_recording_id', $recordingId)
            ->first();

        if (! $request instanceof stdClass) {
            throw new VoiceOperationRejected;
        }

        return $this->presentDeletion($request);
    }

    public function deleteForWithdrawal(User $learner): void
    {
        $context = $this->authority->require($learner);
        $ids = DB::table('voice_recordings')
            ->where('organization_id', $context->organizationId)
            ->where('learner_membership_id', $context->membershipId)
            ->whereNotIn('status', [
                'discarded',
                'expired_deleted',
                'rejected_deleted',
                'withdrawn_deleted',
            ])
            ->pluck('id');

        foreach ($ids as $id) {
            if (is_string($id)) {
                $this->delete($learner, $id, 'withdrawal');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function process(
        User $learner,
        string $recordingId,
        string $audioBytes,
    ): array {
        try {
            $result = $this->processAttempts($learner, $recordingId, $audioBytes);
            if (($result['modern_boundary_rejected'] ?? false) === true) {
                throw new VoiceOperationRejected;
            }

            return $result;
        } catch (VoiceOperationRejected $exception) {
            $row = $this->owned($learner, $recordingId);
            if ($row->status === 'processing' && DB::table('conversation_dialogue_states')
                ->where('organization_id', $row->organization_id)->where('learner_membership_id', $row->learner_membership_id)
                ->where('conversation_session_id', $row->conversation_session_id)->exists()) {
                // Commit a durable deletion intent after the rejected work
                // transaction, so a storage outage cannot revive processing.
                $this->deletions->requestAndProcess((string) $row->organization_id, (string) $row->learner_membership_id, $recordingId, 'rejection');
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function processAttempts(User $learner, string $recordingId, string $audioBytes): array
    {
        $maximumAttempts = (int) config('voice.maximum_provider_attempts', 2);
        $recording = $this->owned($learner, $recordingId);
        $modernSession = DB::table('conversation_dialogue_states')->where('organization_id', $recording->organization_id)
            ->where('learner_membership_id', $recording->learner_membership_id)->where('conversation_session_id', $recording->conversation_session_id)->exists();

        for ($attempt = 1; $attempt <= $maximumAttempts; $attempt++) {
            $terminal = DB::transaction(function () use (
                $learner,
                $recordingId,
                $audioBytes,
                $attempt,
                $maximumAttempts,
            ): ?array {
                $context = $this->authority->require($learner);
                $unlocked = $this->owned($learner, $recordingId);
                // Match exit/withdrawal: parent session before recording.
                $modern = $this->requireActiveModernSession((string) $unlocked->organization_id, (string) $unlocked->learner_membership_id, (string) $unlocked->conversation_session_id);
                $row = $this->owned($learner, $recordingId, lock: true);

                if ($this->string($row, 'status') !== 'processing') {
                    throw new VoiceOperationRejected;
                }

                $this->consents->activeEvent(
                    $context->organizationId,
                    (string) $context->membershipId,
                    VoiceConsentService::UPLOAD,
                );
                $this->consents->activeEvent(
                    $context->organizationId,
                    (string) $context->membershipId,
                    VoiceConsentService::PROVIDER,
                );

                try {
                    $result = $this->speechToText->transcribe(
                        new RecordedSpeechToTextRequest(
                            interactionId: $recordingId,
                            audioBytes: $audioBytes,
                            mimeType: 'audio/wav',
                            language: 'en-IN',
                            preserveOriginal: $modern,
                            recognitionLanguage: $modern && DB::table('conversation_dialogue_states')
                                ->where('organization_id', $row->organization_id)
                                ->where('learner_membership_id', $row->learner_membership_id)
                                ->where('conversation_session_id', $row->conversation_session_id)
                                ->whereIn('flow', ['roleplay_dialogue', 'free_speech'])->exists() ? 'en-IN' : null,
                        ),
                    );
                    $transcript = trim($result->transcript);

                    if (
                        $transcript === ''
                        || preg_match('//u', $transcript) !== 1
                        || mb_strlen($transcript) > 500
                        || ($result->confidenceBps !== null && ($result->confidenceBps < 0
                            || $result->confidenceBps > 10000))
                    ) {
                        throw new ConversationProviderException(
                            safeCode: 'malformed_response',
                            providerKey: $result->usage->providerKey,
                            modelVersion: $result->usage->modelVersion,
                            latencyMilliseconds: $result->usage->latencyMilliseconds,
                            inputUnits: $result->usage->inputUnits,
                            outputUnits: $result->usage->outputUnits,
                        );
                    }

                    $this->recordProviderRun(
                        $row,
                        $attempt,
                        'succeeded',
                        null,
                        $result->usage,
                    );
                    if ($modern) {
                        try {
                            $this->requireActiveModernSession((string) $row->organization_id, (string) $row->learner_membership_id, (string) $row->conversation_session_id);
                        } catch (VoiceOperationRejected) {
                            // Retain known usage, but never store a candidate
                            // after expiry/withdrawal during provider I/O.
                            return ['modern_boundary_rejected' => true];
                        }
                    }
                    $now = Date::now()->toImmutable();
                    DB::table('voice_recordings')
                        ->where('organization_id', $row->organization_id)
                        ->where('id', $recordingId)
                        ->update([
                            'status' => 'awaiting_learner_confirmation',
                            'transcript_ciphertext' => Crypt::encryptString(
                                $transcript,
                            ),
                            'transcript_candidate_sha256' => hash(
                                'sha256',
                                $transcript,
                            ),
                            'transcript_confidence_bps' => $result->confidenceBps,
                            'transcript_source' => $result->transcriptSource,
                            'transcript_locale' => $result->transcriptLocale,
                            'transcript_expires_at' => $now->addHours(
                                (int) config(
                                    'voice.candidate_expiry_hours',
                                    24,
                                ),
                            ),
                            'updated_at' => $now,
                        ]);
                    $fresh = $this->owned($learner, $recordingId, lock: true);

                    if (! $this->deleteSource($fresh)) {
                        throw new VoiceOperationRejected;
                    }

                    DB::table('voice_recordings')
                        ->where('organization_id', $row->organization_id)
                        ->where('id', $recordingId)
                        ->update([
                            'source_object_key' => null,
                            'source_deleted_at' => $now,
                            'updated_at' => $now,
                        ]);

                    return $this->present(
                        $this->owned($learner, $recordingId, lock: true),
                        false,
                    );
                } catch (ConversationProviderException $error) {
                    $usage = new ProviderUsage(
                        providerKey: $error->providerKey,
                        modelVersion: $error->modelVersion,
                        latencyMilliseconds: $error->latencyMilliseconds,
                        inputUnits: $error->inputUnits,
                        outputUnits: $error->outputUnits,
                        estimatedCostMicrounits: 0,
                    );
                    $this->recordProviderRun(
                        $row,
                        $attempt,
                        'failed',
                        $error->safeCode,
                        $usage,
                    );
                    if ($modern) {
                        try {
                            $this->requireActiveModernSession((string) $row->organization_id, (string) $row->learner_membership_id, (string) $row->conversation_session_id);
                        } catch (VoiceOperationRejected) {
                            return ['modern_boundary_rejected' => true];
                        }
                    }

                    if (
                        $error->retryable
                        && $attempt !== $maximumAttempts
                    ) {
                        return null;
                    }

                    $now = Date::now()->toImmutable();
                    DB::table('voice_recordings')
                        ->where('organization_id', $row->organization_id)
                        ->where('id', $recordingId)
                        ->update([
                            'status' => 'provider_failed_recoverable',
                            'source_delete_by' => $now->addHours(
                                (int) config(
                                    'voice.provider_failure_hours',
                                    24,
                                ),
                            ),
                            'updated_at' => $now,
                        ]);

                    return $this->present(
                        $this->owned($learner, $recordingId, lock: true),
                        false,
                    );
                }
            }, $modernSession ? 1 : 3);

            if (is_array($terminal)) {
                return $terminal;
            }
        }

        throw new VoiceOperationRejected;
    }

    /** Legacy sessions retain their existing lifecycle; modern sessions share the dialogue barrier. */
    private function requireActiveModernSession(string $organizationId, string $membershipId, string $sessionId): bool
    {
        $scope = DB::table('conversation_dialogue_states')->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)->where('conversation_session_id', $sessionId);
        if (! (clone $scope)->exists()) {
            return false;
        }
        $session = DB::table('conversation_sessions')->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)->where('id', $sessionId)->where('status', 'active')->lockForUpdate()->first(['id']);
        $state = $scope->first();
        if (! $session instanceof stdClass || ! $state instanceof stdClass || $state->closed_at !== null
            || ! in_array($state->state, ['awaiting_reply', 'retry_required', 'awaiting_question', 'awaiting_explanation_permission'], true)
            || Date::parse((string) $state->expires_at)->lessThanOrEqualTo(Date::now())) {
            throw new VoiceOperationRejected;
        }
        $this->consents->activeEvent($organizationId, $membershipId, VoiceConsentService::UPLOAD);
        $this->consents->activeEvent($organizationId, $membershipId, VoiceConsentService::PROVIDER);

        return true;
    }

    private function recordProviderRun(
        stdClass $row,
        int $attempt,
        string $status,
        ?string $failureCode,
        ProviderUsage $usage,
    ): void {
        $runId = (string) Str::ulid();
        $recordedAt = Date::now()->toImmutable();
        DB::table('voice_provider_runs')->insert([
            'id' => $runId,
            'organization_id' => $row->organization_id,
            'voice_recording_id' => $row->id,
            'conversation_session_id' => $row->conversation_session_id,
            'learner_membership_id' => $row->learner_membership_id,
            'provider_stage' => 'stt',
            'provider_key' => $usage->providerKey,
            'model_version' => $usage->modelVersion,
            'provider_attempt' => $attempt,
            'result_status' => $status,
            'safe_failure_code' => $failureCode,
            'latency_ms' => max(0, $usage->latencyMilliseconds),
            'input_units' => max(0, $usage->inputUnits),
            'output_units' => max(0, $usage->outputUnits),
            'estimated_cost_microunits' => max(
                0,
                $usage->estimatedCostMicrounits,
            ),
            'recorded_at' => $recordedAt,
        ]);

        $this->observations->recordVoiceRun(
            organizationId: (string) $row->organization_id,
            runId: $runId,
            sessionId: (string) $row->conversation_session_id,
            stage: 'stt',
            semanticOperation: 'voice_recording.transcribe',
            routeRole: 'primary',
            resultStatus: $status,
            safeFailureCode: $failureCode,
            usage: $usage,
            recordedAt: $recordedAt,
        );
    }

    private function historyActive(
        string $organizationId,
        string $membershipId,
    ): bool {
        $row = DB::table('voice_consent_events')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('purpose', VoiceConsentService::HISTORY)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first(['state']);

        return $row instanceof stdClass && $row->state === 'granted';
    }

    private function owned(
        User $learner,
        string $recordingId,
        bool $lock = false,
    ): stdClass {
        $context = $this->authority->require($learner);
        $query = DB::table('voice_recordings')
            ->where('organization_id', $context->organizationId)
            ->where('learner_membership_id', $context->membershipId)
            ->where('id', $recordingId);
        $row = $lock ? $query->lockForUpdate()->first() : $query->first();

        if (! $row instanceof stdClass) {
            throw new VoiceOperationRejected;
        }

        return $row;
    }

    private function deleteSource(stdClass $row): bool
    {
        $key = $row->source_object_key ?? null;

        if (! is_string($key) || $key === '') {
            return $row->source_deleted_at !== null;
        }

        return Storage::disk((string) config('voice.storage_disk', 'local'))
            ->delete($key);
    }

    private function transcript(stdClass $row): string
    {
        $ciphertext = $row->transcript_ciphertext ?? null;

        if (! is_string($ciphertext) || $ciphertext === '') {
            throw new VoiceOperationRejected;
        }

        try {
            $value = Crypt::decryptString($ciphertext);
        } catch (DecryptException) {
            throw new VoiceOperationRejected;
        }

        if ($value === '' || preg_match('//u', $value) !== 1) {
            throw new VoiceOperationRejected;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(stdClass $row, bool $replayed): array
    {
        $transcript = null;

        if (
            $row->status === 'awaiting_learner_confirmation'
            && is_string($row->transcript_ciphertext)
        ) {
            $transcript = $this->transcript($row);
        }

        return [
            'byte_size' => (int) $row->byte_size,
            'codec' => $this->string($row, 'codec'),
            'created_at' => $row->created_at,
            'deletion_url' => '/api/v1/voice-recordings/'.$row->id.'/deletion',
            'duration_ms' => (int) $row->duration_ms,
            'id' => $this->string($row, 'id'),
            'mime_type' => $this->string($row, 'mime_type'),
            'replayed' => $replayed,
            'source_deleted' => $row->source_deleted_at !== null,
            'status' => $this->string($row, 'status'),
            'transcript' => $transcript,
            'transcript_original' => ($row->transcript_source ?? 'translated_speech') === 'original_speech' ? $transcript : null,
            'transcript_source' => $row->transcript_source ?? 'translated_speech',
            'transcript_locale' => $row->transcript_locale ?? 'unknown',
            'english_intent_text' => ($row->transcript_source ?? 'translated_speech') === 'translated_speech' ? $transcript : null,
            'transcript_confidence' => $row->transcript_confidence_bps === null
                ? null
                : ((int) $row->transcript_confidence_bps) / 10000,
            'transcript_review_url' => '/api/v1/voice-recordings/'
                .$row->id.'/transcript-review',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDeletion(stdClass $row): array
    {
        return [
            'completed_at' => $row->completed_at,
            'id' => $this->string($row, 'id'),
            'provider_deletion_status' => $this->string(
                $row,
                'provider_deletion_status',
            ),
            'recording_id' => $this->string($row, 'voice_recording_id'),
            'requested_at' => $row->requested_at,
            'source_deleted' => (bool) $row->source_deleted,
            'status' => $this->string($row, 'status'),
            'transcript_deleted' => (bool) $row->transcript_deleted,
        ];
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
