<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Operator\Exceptions\InvalidOperatorCursor;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use stdClass;

final readonly class OperatorSearchQuery
{
    private const string CURSOR_PURPOSE = 'operator.search.v1';

    private const string SOURCE_CURRICULUM_VERSIONS = 'curriculum_versions';

    private const string SOURCE_CURRICULUM_PUBLICATIONS = 'curriculum_publications';

    private const string SOURCE_STUCK_ATTEMPTS = 'stuck_attempts';

    private const string SOURCE_VOICE_FAILURES = 'voice_failures';

    private const string SOURCE_OPEN_DELETIONS = 'open_deletions';

    private const string SOURCE_AUDIT_EVENTS = 'audit_events';

    public function __construct(
        private OrganizationContextManager $contexts,
        private OperatorShellAuthorization $authorization,
        private OperatorCursorCodec $cursors,
    ) {}

    /**
     * @return array{
     *     generated_at: string,
     *     items: list<array<string, mixed>>,
     *     next_cursor: ?string
     * }
     */
    public function search(
        User $actor,
        string $term,
        int $limit = 20,
        ?string $cursor = null,
    ): array {
        $access = $this->authorization->access($actor);
        $organizationId = $this->contexts->current()->organizationId;
        $normalizedTerm = mb_strtolower(trim($term), 'UTF-8');
        $sources = $this->sources($access);
        $sourceHash = hash('sha256', implode('|', $sources));
        $termHash = hash('sha256', $normalizedTerm);
        $sourceIndex = 0;
        $offset = 0;

        if ($cursor !== null) {
            $position = $this->cursors->decode(
                $cursor,
                self::CURSOR_PURPOSE,
                $organizationId,
            );
            $sourceIndex = $position['source_index'] ?? null;
            $offset = $position['offset'] ?? null;
            $cursorTermHash = $position['term_hash'] ?? null;
            $cursorSourceHash = $position['source_hash'] ?? null;

            if (
                ! is_int($sourceIndex)
                || $sourceIndex < 0
                || $sourceIndex >= count($sources)
                || ! is_int($offset)
                || $offset < 0
                || ! is_string($cursorTermHash)
                || ! hash_equals($termHash, $cursorTermHash)
                || ! is_string($cursorSourceHash)
                || ! hash_equals($sourceHash, $cursorSourceHash)
            ) {
                throw new InvalidOperatorCursor;
            }
        }

        $items = [];
        $nextCursor = null;

        for ($index = $sourceIndex; $index < count($sources); $index++) {
            $sourceOffset = $index === $sourceIndex ? $offset : 0;
            $remaining = $limit - count($items);
            $take = $remaining > 0 ? $remaining + 1 : 1;
            $matches = $this->searchSource(
                source: $sources[$index],
                organizationId: $organizationId,
                normalizedTerm: $normalizedTerm,
                limit: $take,
                offset: $sourceOffset,
            );

            if ($remaining === 0) {
                if ($matches !== []) {
                    $nextCursor = $this->encodeCursor(
                        organizationId: $organizationId,
                        termHash: $termHash,
                        sourceHash: $sourceHash,
                        sourceIndex: $index,
                        offset: $sourceOffset,
                    );

                    break;
                }

                continue;
            }

            if (count($matches) > $remaining) {
                array_push($items, ...array_slice($matches, 0, $remaining));
                $nextCursor = $this->encodeCursor(
                    organizationId: $organizationId,
                    termHash: $termHash,
                    sourceHash: $sourceHash,
                    sourceIndex: $index,
                    offset: $sourceOffset + $remaining,
                );

                break;
            }

            array_push($items, ...$matches);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'items' => $items,
            'next_cursor' => $nextCursor,
        ];
    }

    /**
     * @param  array{
     *     content: bool,
     *     audit: bool,
     *     support: bool,
     *     people_panels: list<string>,
     *     search_available: bool
     * }  $access
     * @return list<string>
     */
    private function sources(array $access): array
    {
        $sources = [];

        if ($access['content']) {
            $sources[] = self::SOURCE_CURRICULUM_VERSIONS;
            $sources[] = self::SOURCE_CURRICULUM_PUBLICATIONS;
        }

        if ($access['support']) {
            $sources[] = self::SOURCE_STUCK_ATTEMPTS;
            $sources[] = self::SOURCE_VOICE_FAILURES;
            $sources[] = self::SOURCE_OPEN_DELETIONS;
        }

        if ($access['audit']) {
            $sources[] = self::SOURCE_AUDIT_EVENTS;
        }

        return $sources;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchSource(
        string $source,
        string $organizationId,
        string $normalizedTerm,
        int $limit,
        int $offset,
    ): array {
        return match ($source) {
            self::SOURCE_CURRICULUM_VERSIONS => $this->curriculumVersions(
                $organizationId,
                $normalizedTerm,
                $limit,
                $offset,
            ),
            self::SOURCE_CURRICULUM_PUBLICATIONS => $this->publications(
                $organizationId,
                $normalizedTerm,
                $limit,
                $offset,
            ),
            self::SOURCE_STUCK_ATTEMPTS => $this->stuckAttempts(
                $organizationId,
                $normalizedTerm,
                $limit,
                $offset,
            ),
            self::SOURCE_VOICE_FAILURES => $this->voiceFailures(
                $organizationId,
                $normalizedTerm,
                $limit,
                $offset,
            ),
            self::SOURCE_OPEN_DELETIONS => $this->openDeletions(
                $organizationId,
                $normalizedTerm,
                $limit,
                $offset,
            ),
            self::SOURCE_AUDIT_EVENTS => $this->auditEvents(
                $organizationId,
                $normalizedTerm,
                $limit,
                $offset,
            ),
            default => throw new InvalidOperatorCursor,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function curriculumVersions(
        string $organizationId,
        string $term,
        int $limit,
        int $offset,
    ): array {
        $query = DB::table('curriculum_versions as versions')
            ->join('programs as programs', function ($join): void {
                $join
                    ->on(
                        'programs.organization_id',
                        '=',
                        'versions.organization_id',
                    )
                    ->on('programs.id', '=', 'versions.program_id');
            })
            ->where('versions.organization_id', $organizationId);
        $this->whereContains($query, [
            'programs.internal_name',
            'programs.key',
            'versions.key',
            'versions.id',
        ], $term);
        $rows = $query
            ->orderBy('programs.internal_name')
            ->orderBy('versions.key')
            ->orderBy('versions.id')
            ->offset($offset)
            ->limit($limit)
            ->get([
                'programs.internal_name',
                'programs.key as program_key',
                'versions.key as version_key',
                'versions.id',
            ]);
        $items = [];

        foreach ($rows as $row) {
            $items[] = $this->item(
                kind: 'curriculum_version',
                id: $this->string($row, 'id'),
                section: 'learning',
                primaryText: $this->string($row, 'internal_name'),
                secondaryText: sprintf(
                    '%s / %s',
                    $this->string($row, 'program_key'),
                    $this->string($row, 'version_key'),
                ),
            );
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publications(
        string $organizationId,
        string $term,
        int $limit,
        int $offset,
    ): array {
        $query = DB::table('curriculum_publications as publications')
            ->join('programs as programs', function ($join): void {
                $join
                    ->on(
                        'programs.organization_id',
                        '=',
                        'publications.organization_id',
                    )
                    ->on('programs.id', '=', 'publications.program_id');
            })
            ->join('curriculum_versions as versions', function ($join): void {
                $join
                    ->on(
                        'versions.organization_id',
                        '=',
                        'publications.organization_id',
                    )
                    ->on(
                        'versions.id',
                        '=',
                        'publications.curriculum_version_id',
                    );
            })
            ->where('publications.organization_id', $organizationId);
        $this->whereContains($query, [
            'programs.internal_name',
            'programs.key',
            'versions.key',
            'publications.id',
        ], $term);
        $rows = $query
            ->orderBy('programs.internal_name')
            ->orderBy('versions.key')
            ->orderBy('publications.publication_number')
            ->orderBy('publications.id')
            ->offset($offset)
            ->limit($limit)
            ->get([
                'publications.id',
                'publications.publication_number',
                'publications.created_at',
                'programs.internal_name',
                'programs.key as program_key',
                'versions.key as version_key',
            ]);
        $items = [];

        foreach ($rows as $row) {
            $items[] = $this->item(
                kind: 'curriculum_publication',
                id: $this->string($row, 'id'),
                section: 'learning',
                primaryText: $this->string($row, 'internal_name'),
                secondaryText: sprintf(
                    '%s / %s · #%d',
                    $this->string($row, 'program_key'),
                    $this->string($row, 'version_key'),
                    $this->integer($row, 'publication_number'),
                ),
                occurredAt: $this->isoTimestamp($row->created_at ?? null),
            );
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stuckAttempts(
        string $organizationId,
        string $term,
        int $limit,
        int $offset,
    ): array {
        $query = DB::table('learner_attempts')
            ->where('organization_id', $organizationId)
            ->where('status', 'in_progress')
            ->where('updated_at', '<', now()->subHour());
        $this->whereDiagnosticMatches(
            $query,
            ['id', 'status'],
            $term,
            'stuck in progress learner attempt',
        );
        $rows = $query
            ->orderBy('updated_at')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'status', 'updated_at']);

        return $this->diagnosticItems(
            $rows->all(),
            'stuck_in_progress',
            'learner_attempt',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function voiceFailures(
        string $organizationId,
        string $term,
        int $limit,
        int $offset,
    ): array {
        $query = DB::table('voice_recordings')
            ->where('organization_id', $organizationId)
            ->where('status', 'provider_failed_recoverable');
        $this->whereDiagnosticMatches(
            $query,
            ['id', 'status'],
            $term,
            'provider failed recoverable voice recording',
        );
        $rows = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'status', 'updated_at']);

        return $this->diagnosticItems(
            $rows->all(),
            'provider_failed_recoverable',
            'voice_recording',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openDeletions(
        string $organizationId,
        string $term,
        int $limit,
        int $offset,
    ): array {
        $query = DB::table('voice_deletion_requests')
            ->where('organization_id', $organizationId)
            ->whereIn('status', [
                'pending',
                'partially_completed',
                'restricted',
            ]);
        $this->whereDiagnosticMatches(
            $query,
            ['id', 'status'],
            $term,
            'open deletion voice deletion request',
        );
        $rows = $query
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get(['id', 'status', 'requested_at as updated_at']);

        return $this->diagnosticItems(
            $rows->all(),
            'open_deletion',
            'voice_deletion_request',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function auditEvents(
        string $organizationId,
        string $term,
        int $limit,
        int $offset,
    ): array {
        $query = DB::table('audit_events')
            ->where('scope_kind', 'organization')
            ->where('organization_id', $organizationId)
            ->whereIn('action', OperatorAuditEventQuery::SAFE_ACTIONS);
        $this->whereContains($query, [
            'action',
            'subject_type',
            'subject_id',
        ], $term);
        $rows = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get([
                'id',
                'action',
                'subject_type',
                'subject_id',
                'occurred_at',
            ]);
        $items = [];

        foreach ($rows as $row) {
            $items[] = $this->item(
                kind: 'audit_event',
                id: $this->string($row, 'id'),
                section: 'safety',
                primaryText: $this->string($row, 'action'),
                secondaryText: sprintf(
                    '%s:%s',
                    $this->string($row, 'subject_type'),
                    $this->string($row, 'subject_id'),
                ),
                occurredAt: $this->isoTimestamp($row->occurred_at ?? null),
            );
        }

        return $items;
    }

    /**
     * @param  array<int, stdClass>  $rows
     * @return list<array<string, mixed>>
     */
    private function diagnosticItems(
        array $rows,
        string $kind,
        string $subjectType,
    ): array {
        $items = [];

        foreach ($rows as $row) {
            $id = $this->string($row, 'id');
            $status = $this->string($row, 'status');
            $items[] = $this->item(
                kind: 'support_diagnostic',
                id: $id,
                section: 'operations',
                primaryText: $kind,
                secondaryText: sprintf(
                    '%s:%s · %s',
                    $subjectType,
                    $id,
                    $status,
                ),
                occurredAt: $this->isoTimestamp($row->updated_at ?? null),
            );
        }

        return $items;
    }

    /**
     * @param  list<string>  $columns
     */
    private function whereContains(
        Builder $query,
        array $columns,
        string $term,
    ): void {
        $pattern = '%'.str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $term,
        ).'%';

        $query->where(function (Builder $nested) use ($columns, $pattern): void {
            foreach ($columns as $index => $column) {
                $sql = $this->lowerContainsSql($column);

                if ($index === 0) {
                    $nested->whereRaw($sql, [$pattern]);
                } else {
                    $nested->orWhereRaw($sql, [$pattern]);
                }
            }
        });
    }

    /**
     * @return literal-string
     */
    private function lowerContainsSql(string $column): string
    {
        return match ($column) {
            'programs.internal_name' => "LOWER(programs.internal_name) LIKE ? ESCAPE '\\'",
            'programs.key' => "LOWER(programs.key) LIKE ? ESCAPE '\\'",
            'versions.key' => "LOWER(versions.key) LIKE ? ESCAPE '\\'",
            'versions.id' => "LOWER(versions.id) LIKE ? ESCAPE '\\'",
            'publications.id' => "LOWER(publications.id) LIKE ? ESCAPE '\\'",
            'id' => "LOWER(id) LIKE ? ESCAPE '\\'",
            'status' => "LOWER(status) LIKE ? ESCAPE '\\'",
            'action' => "LOWER(action) LIKE ? ESCAPE '\\'",
            'subject_type' => "LOWER(subject_type) LIKE ? ESCAPE '\\'",
            'subject_id' => "LOWER(subject_id) LIKE ? ESCAPE '\\'",
            default => throw new LogicException(
                'Unsupported operator search column.',
            ),
        };
    }

    /**
     * @param  list<string>  $columns
     */
    private function whereDiagnosticMatches(
        Builder $query,
        array $columns,
        string $term,
        string $staticDescriptor,
    ): void {
        if (str_contains($staticDescriptor, $term)) {
            return;
        }

        $this->whereContains($query, $columns, $term);
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        string $kind,
        string $id,
        string $section,
        string $primaryText,
        string $secondaryText,
        ?string $occurredAt = null,
    ): array {
        return [
            'kind' => $kind,
            'id' => $id,
            'section' => $section,
            'primary_text' => $primaryText,
            'secondary_text' => $secondaryText,
            'occurred_at' => $occurredAt,
        ];
    }

    private function encodeCursor(
        string $organizationId,
        string $termHash,
        string $sourceHash,
        int $sourceIndex,
        int $offset,
    ): string {
        return $this->cursors->encode(
            self::CURSOR_PURPOSE,
            $organizationId,
            [
                'term_hash' => $termHash,
                'source_hash' => $sourceHash,
                'source_index' => $sourceIndex,
                'offset' => $offset,
            ],
        );
    }

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : '';
    }

    private function integer(stdClass $row, string $property): int
    {
        $value = $row->{$property} ?? null;

        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    private function isoTimestamp(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
