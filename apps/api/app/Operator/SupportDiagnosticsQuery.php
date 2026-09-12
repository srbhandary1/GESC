<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Org-scoped support diagnostics digests only — never transcripts, audio, or PII bodies.
 */
final readonly class SupportDiagnosticsQuery
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OperatorPrivacyAuthorization $authorization,
    ) {}

    /**
     * @return array{generated_at: string, items: list<array<string, mixed>>}
     */
    public function list(User $actor, int $limit = 50): array
    {
        $this->authorization->assertCanReadDiagnostics($actor);
        $organizationId = $this->contexts->current()->organizationId;
        $limit = max(1, min($limit, 100));
        $stuckBefore = now()->subHour();

        $items = [];
        $remaining = $limit;

        foreach ($this->stuckAttempts($organizationId, $stuckBefore, $remaining) as $item) {
            $items[] = $item;
            $remaining--;
        }

        if ($remaining > 0) {
            foreach ($this->failedVoice($organizationId, $remaining) as $item) {
                $items[] = $item;
                $remaining--;
            }
        }

        if ($remaining > 0) {
            foreach ($this->openDeletions($organizationId, $remaining) as $item) {
                $items[] = $item;
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'items' => $items,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stuckAttempts(
        string $organizationId,
        mixed $stuckBefore,
        int $limit,
    ): array {
        if ($limit < 1) {
            return [];
        }

        $rows = DB::table('learner_attempts')
            ->where('organization_id', $organizationId)
            ->where('status', 'in_progress')
            ->where('updated_at', '<', $stuckBefore)
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'status', 'updated_at']);

        return $this->mapRows($rows, 'learner_attempt', 'stuck_in_progress');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function failedVoice(string $organizationId, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $rows = DB::table('voice_recordings')
            ->where('organization_id', $organizationId)
            ->where('status', 'provider_failed_recoverable')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'status', 'updated_at']);

        return $this->mapRows($rows, 'voice_recording', 'provider_failed_recoverable');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openDeletions(string $organizationId, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $rows = DB::table('voice_deletion_requests')
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['pending', 'partially_completed', 'restricted'])
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'status', 'requested_at as updated_at']);

        return $this->mapRows($rows, 'voice_deletion_request', 'open_deletion');
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapRows($rows, string $subjectType, string $kind): array
    {
        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'kind' => $kind,
                'subject_type' => $subjectType,
                'subject_id' => is_string($row->id ?? null) ? $row->id : '',
                'status' => is_string($row->status ?? null) ? $row->status : '',
                'observed_at' => $this->isoTimestamp($row->updated_at ?? null),
            ];
        }

        return $items;
    }

    private function isoTimestamp(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return '';
    }
}
