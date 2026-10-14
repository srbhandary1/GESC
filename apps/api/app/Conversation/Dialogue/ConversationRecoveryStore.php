<?php

declare(strict_types=1);

namespace App\Conversation\Dialogue;

use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * ADR-0042's private temporary recovery leaf. Bodies never enter a database,
 * cache, queue or log. Callers enforce notice/entitlement and bounded context.
 * Only ciphertext fingerprints are retained in ordinary database metadata.
 */
final class ConversationRecoveryStore
{
    public const string DISK = 'conversation_recovery';

    private const int MAX_TTL_SECONDS = 900;

    private const int MAX_PLAINTEXT_BYTES = 8 * 1024 * 1024;

    /** @param array<string, mixed> $payload */
    public function persist(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        ?string $turnId,
        array $payload,
    ): string {
        return DB::transaction(fn (): string => $this->persistLocked($organizationId, $membershipId, $sessionId, $turnId, $payload));
    }

    /** @param array<string, mixed> $payload */
    private function persistLocked(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        ?string $turnId,
        array $payload,
    ): string {
        $sessionExists = DB::table('conversation_sessions')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('id', $sessionId)
            ->where('status', 'active')->lockForUpdate()->first(['id']) instanceof stdClass;
        $turnExists = $turnId === null || DB::table('conversation_turns')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('conversation_session_id', $sessionId)
            ->where('id', $turnId)->exists();

        if (! $sessionExists || ! $turnExists || $this->closed($organizationId, $membershipId, $sessionId)) {
            throw new ConversationRecoveryUnavailable;
        }

        $id = (string) Str::ulid();
        $path = bin2hex(random_bytes(32)).'.enc';
        $now = Date::now()->toImmutable();
        $ttl = max(1, min(self::MAX_TTL_SECONDS, (int) config('conversation_recovery.ttl_seconds', 900)));
        $expiresAt = $now->addSeconds($ttl);

        try {
            $plaintext = json_encode([
                'artifact_id' => $id,
                'organization_id' => $organizationId,
                'learner_membership_id' => $membershipId,
                'conversation_session_id' => $sessionId,
                'conversation_turn_id' => $turnId,
                'expires_at' => $expiresAt->toIso8601String(),
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $limit = max(1, min(self::MAX_PLAINTEXT_BYTES, (int) config('conversation_recovery.max_plaintext_bytes', self::MAX_PLAINTEXT_BYTES)));

            if (strlen($plaintext) > $limit) {
                throw new ConversationRecoveryUnavailable;
            }

            $ciphertext = Crypt::encryptString($plaintext);
            DB::table('conversation_recovery_artifacts')->insert([
                'id' => $id,
                'organization_id' => $organizationId,
                'learner_membership_id' => $membershipId,
                'conversation_session_id' => $sessionId,
                'conversation_turn_id' => $turnId,
                'kind' => $turnId === null ? 'start' : 'turn',
                'storage_path' => $path,
                'ciphertext_sha256' => hash('sha256', $ciphertext),
                'status' => 'writing',
                'created_at' => $now,
                'expires_at' => $expiresAt,
            ]);

            if (! $this->disk()->put($path, $ciphertext, ['visibility' => 'private'])) {
                throw new ConversationRecoveryUnavailable;
            }

            if (DB::table('conversation_recovery_artifacts')->where('id', $id)
                ->where('status', 'writing')->update(['status' => 'active']) !== 1) {
                throw new ConversationRecoveryUnavailable;
            }
        } catch (Throwable) {
            // A partial write is unavailable and remains retryable for cleanup.
            $row = $this->scope($organizationId, $membershipId, $sessionId)->where('id', $id)->first();
            if ($row instanceof stdClass) {
                $this->deleteRows($this->scope($organizationId, $membershipId, $sessionId)->where('id', $id));
            }
            throw new ConversationRecoveryUnavailable;
        }

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function read(string $organizationId, string $membershipId, string $sessionId, string $artifactId): ?array
    {
        if ($this->closed($organizationId, $membershipId, $sessionId)) {
            return null;
        }
        $scope = $this->scope($organizationId, $membershipId, $sessionId)->where('id', $artifactId);
        $row = (clone $scope)->where('status', 'active')->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        if (Date::parse((string) $row->expires_at)->lessThanOrEqualTo(Date::now())) {
            $this->deleteRows($scope);

            return null;
        }

        try {
            $ciphertext = $this->disk()->get($this->path($row));
            if (! is_string($ciphertext) || ! hash_equals((string) $row->ciphertext_sha256, hash('sha256', $ciphertext))) {
                throw new ConversationRecoveryUnavailable;
            }
            $envelope = json_decode(Crypt::decryptString($ciphertext), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($envelope)
                || ($envelope['artifact_id'] ?? null) !== $artifactId
                || ($envelope['organization_id'] ?? null) !== $organizationId
                || ($envelope['learner_membership_id'] ?? null) !== $membershipId
                || ($envelope['conversation_session_id'] ?? null) !== $sessionId
                || ($envelope['conversation_turn_id'] ?? null) !== $row->conversation_turn_id
                || ! is_string($envelope['expires_at'] ?? null)
                || Date::parse($envelope['expires_at'])->lessThanOrEqualTo(Date::now())
                || ! is_array($envelope['payload'] ?? null)
            ) {
                throw new ConversationRecoveryUnavailable;
            }

            // Withdrawal/exit may have invalidated metadata during object I/O.
            if ($this->closed($organizationId, $membershipId, $sessionId)
                || ! (clone $scope)->where('status', 'active')->where('expires_at', '>', Date::now())->exists()) {
                return null;
            }

            /** @var array<string, mixed> */
            return $envelope['payload'];
        } catch (Throwable) {
            $this->deleteRows($scope);
            throw new ConversationRecoveryUnavailable;
        }
    }

    public function deleteSession(string $organizationId, string $membershipId, string $sessionId): int
    {
        return $this->deleteRows($this->scope($organizationId, $membershipId, $sessionId));
    }

    public function deleteMembership(string $organizationId, string $membershipId): int
    {
        DB::transaction(function () use ($organizationId, $membershipId): void {
            // Match turn dispatch's parent lock. Withdrawal/logout cannot run
            // ahead of an in-flight turn and then have its context recreated.
            $sessions = DB::table('conversation_sessions')->where('organization_id', $organizationId)
                ->where('learner_membership_id', $membershipId)->orderBy('id')->lockForUpdate()->pluck('id');
            DB::table('conversation_dialogue_states')->where('organization_id', $organizationId)
                ->where('learner_membership_id', $membershipId)->whereIn('conversation_session_id', $sessions)
                ->whereNull('closed_at')->update(['state' => 'closed', 'closed_at' => Date::now(), 'updated_at' => Date::now()]);
        });

        return $this->deleteRows($this->scope($organizationId, $membershipId));
    }

    /** Logout never leaves recoverable learner content in another device's session. */
    public function deleteForUser(string $userId): void
    {
        $memberships = DB::table('memberships')->where('user_id', $userId)->get(['id', 'organization_id']);
        $failed = false;
        foreach ($memberships as $membership) {
            try {
                $this->deleteMembership((string) $membership->organization_id, (string) $membership->id);
            } catch (ConversationRecoveryUnavailable) {
                $failed = true;
            }
        }
        if ($failed) {
            throw new ConversationRecoveryUnavailable;
        }
    }

    /** @return array{deleted: int, failed: int, orphan_deleted: int} */
    public function purgeExpired(): array
    {
        $result = ['deleted' => 0, 'failed' => 0, 'orphan_deleted' => 0];
        $rows = DB::table('conversation_recovery_artifacts')->where('status', '!=', 'deleted')
            ->where(function (Builder $query): void {
                $query->where('expires_at', '<=', Date::now())->orWhere('status', 'deletion_pending');
            })->orderBy('id')->get(['id', 'organization_id', 'learner_membership_id']);
        foreach ($rows as $row) {
            try {
                $result['deleted'] += $this->deleteRows($this->scope((string) $row->organization_id, (string) $row->learner_membership_id)->where('id', $row->id));
            } catch (ConversationRecoveryUnavailable) {
                $result['failed']++;
            }
        }

        // An enclosing transaction can roll back metadata after an object write.
        // Such ciphertext has no reader; the fixed-age backstop removes it too.
        try {
            $disk = $this->disk();
            foreach ($disk->files() as $path) {
                if (preg_match('/\A[0-9a-f]{64}\.enc\z/', $path) !== 1
                    || $disk->lastModified($path) > Date::now()->getTimestamp() - self::MAX_TTL_SECONDS
                    || DB::table('conversation_recovery_artifacts')->where('storage_path', $path)->where('status', '!=', 'deleted')->exists()
                ) {
                    continue;
                }
                if (! $disk->delete($path) || $disk->exists($path)) {
                    $result['failed']++;
                } else {
                    $result['orphan_deleted']++;
                }
            }
        } catch (Throwable) {
            $result['failed']++;
        }

        return $result;
    }

    private function scope(string $organizationId, string $membershipId, ?string $sessionId = null): Builder
    {
        return DB::table('conversation_recovery_artifacts')
            ->where('organization_id', $organizationId)->where('learner_membership_id', $membershipId)
            ->when($sessionId !== null, fn (Builder $query): Builder => $query->where('conversation_session_id', $sessionId));
    }

    private function deleteRows(Builder $query): int
    {
        $query = (clone $query)->where('status', '!=', 'deleted');
        $query->update(['status' => 'deletion_pending']);
        $deleted = 0;
        $failed = false;
        foreach ((clone $query)->get() as $row) {
            $record = $this->scope((string) $row->organization_id, (string) $row->learner_membership_id)->where('id', $row->id);
            $record->increment('deletion_attempts');
            try {
                $disk = $this->disk();
                $path = $this->path($row);
                if (! $disk->delete($path) || $disk->exists($path)) {
                    throw new ConversationRecoveryUnavailable;
                }
                $record->update(['status' => 'deleted', 'deleted_at' => Date::now()]);
                $deleted++;
            } catch (Throwable) {
                $failed = true;
            }
        }
        if ($failed) {
            throw new ConversationRecoveryUnavailable;
        }

        return $deleted;
    }

    private function path(stdClass $row): string
    {
        $path = (string) $row->storage_path;
        if (preg_match('/\A[0-9a-f]{64}\.enc\z/', $path) !== 1) {
            throw new ConversationRecoveryUnavailable;
        }

        return $path;
    }

    /**
     * Concurrent withdrawal can change this database-backed result between reads.
     *
     * @phpstan-impure
     */
    private function closed(string $organizationId, string $membershipId, string $sessionId): bool
    {
        return DB::table('conversation_dialogue_states')->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)->where('conversation_session_id', $sessionId)
            ->whereNotNull('closed_at')->exists();
    }

    private function disk(): FilesystemAdapter
    {
        if (config('filesystems.disks.'.self::DISK.'.driver') !== 'local'
            || config('filesystems.disks.'.self::DISK.'.serve') !== false
            || config('filesystems.disks.'.self::DISK.'.visibility') !== 'private'
        ) {
            throw new ConversationRecoveryUnavailable;
        }

        return Storage::disk(self::DISK);
    }
}
