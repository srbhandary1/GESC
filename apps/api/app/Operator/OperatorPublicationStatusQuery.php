<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Operator\Exceptions\InvalidOperatorCursor;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class OperatorPublicationStatusQuery
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OperatorContentAuthorization $authorization,
        private OperatorCursorCodec $cursors,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, next_cursor: ?string, allowed_actions: list<string>}
     */
    public function list(
        User $actor,
        int $limit = 25,
        ?string $cursor = null,
    ): array {
        $this->authorization->assertCanReadContent($actor);
        $organizationId = $this->contexts->current()->organizationId;
        $limit = max(1, min($limit, 25));

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
            ->join(
                'curriculum_versions as versions',
                function ($join): void {
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
                },
            )
            ->leftJoin('publication_heads as heads', function ($join): void {
                $join
                    ->on(
                        'heads.organization_id',
                        '=',
                        'publications.organization_id',
                    )
                    ->on(
                        'heads.program_id',
                        '=',
                        'publications.program_id',
                    );
            })
            ->where('publications.organization_id', $organizationId);

        if ($cursor !== null) {
            $position = $this->cursors->decode(
                $cursor,
                'operator.content-publications.v2',
                $organizationId,
            );
            $createdAt = $position['created_at'] ?? null;
            $id = $position['id'] ?? null;

            if (
                ! is_string($createdAt)
                || $createdAt === ''
                || strlen($createdAt) > 64
                || ! is_string($id)
                || ! Str::isUlid($id)
            ) {
                throw new InvalidOperatorCursor;
            }

            $query->where(function (Builder $position) use (
                $createdAt,
                $id,
            ): void {
                $position
                    ->where('publications.created_at', '<', $createdAt)
                    ->orWhere(function (Builder $sameTime) use (
                        $createdAt,
                        $id,
                    ): void {
                        $sameTime
                            ->where('publications.created_at', $createdAt)
                            ->where('publications.id', '<', $id);
                    });
            });
        }

        $rows = $query
            ->orderByDesc('publications.created_at')
            ->orderByDesc('publications.id')
            ->limit($limit + 1)
            ->get([
                'publications.id as publication_id',
                'publications.program_id',
                'programs.key as program_key',
                'publications.curriculum_version_id',
                'versions.key as version_key',
                'publications.publication_number',
                'publications.manifest_hash',
                'publications.created_at',
                'heads.lock_version as head_version',
                'heads.active_publication_id',
                'heads.is_suspended',
            ]);
        $hasMore = $rows->count() > $limit;
        $visibleRows = $rows->take($limit)->values();

        $items = [];

        foreach ($visibleRows as $row) {
            $publicationId = $this->string($row, 'publication_id');
            $activePublicationId = $this->nullableString(
                $row,
                'active_publication_id',
            );
            $headVersion = $this->nullableInt($row, 'head_version') ?? 0;
            $latestEvent = DB::table('publication_events')
                ->where('organization_id', $organizationId)
                ->where('curriculum_publication_id', $publicationId)
                // A future schedule can have an effective time after a later
                // cancellation. Lifecycle authority follows append order,
                // while effective_at remains the displayed activation time.
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first(['event_type', 'effective_at']);
            $eventType = $latestEvent instanceof stdClass
                ? $this->string($latestEvent, 'event_type')
                : 'scheduled';
            $effectiveAt = $latestEvent instanceof stdClass
                ? $this->isoTimestamp($latestEvent->effective_at ?? null)
                : $this->isoTimestamp($row->created_at ?? null);
            $isHead = $activePublicationId !== null
                && hash_equals($activePublicationId, $publicationId);
            $status = $this->lifecycleStatus(
                $eventType,
                $isHead,
                $this->boolean($row, 'is_suspended'),
            );

            $items[] = [
                'publication_id' => $publicationId,
                'program_id' => $this->string($row, 'program_id'),
                'program_key' => $this->string($row, 'program_key'),
                'curriculum_version_id' => $this->string(
                    $row,
                    'curriculum_version_id',
                ),
                'version_key' => $this->string($row, 'version_key'),
                'publication_number' => $this->nullableInt(
                    $row,
                    'publication_number',
                ) ?? 0,
                'manifest_hash' => $this->string($row, 'manifest_hash'),
                'is_head' => $isHead,
                'head_version' => $isHead ? $headVersion : null,
                'publication_head_version' => $headVersion,
                'lifecycle_status' => $status,
                'effective_at' => $effectiveAt,
                'publication_suspended' => $this->boolean(
                    $row,
                    'is_suspended',
                ),
                'allowed_actions' => $this->allowedActions(
                    $actor,
                    $status,
                    $isHead,
                    $effectiveAt,
                ),
                'created_at' => $this->isoTimestamp(
                    $row->created_at ?? null,
                ),
            ];
        }

        $nextCursor = null;

        if ($hasMore) {
            $last = $visibleRows->last();

            if (! $last instanceof stdClass) {
                throw new InvalidOperatorCursor;
            }

            $nextCursor = $this->cursors->encode(
                'operator.content-publications.v2',
                $organizationId,
                [
                    'created_at' => $this->isoTimestamp(
                        $last->created_at ?? null,
                    ),
                    'id' => $this->string($last, 'publication_id'),
                ],
            );
        }

        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
            'allowed_actions' => [],
        ];
    }

    private function lifecycleStatus(
        string $eventType,
        bool $isHead,
        bool $suspended,
    ): string {
        if ($suspended && $isHead) {
            return 'suspended';
        }

        return match ($eventType) {
            'scheduled' => 'scheduled',
            'schedule_cancelled' => 'cancelled',
            'retired' => 'retired',
            'activated', 'rollback_activated', 'resumed' => $isHead
                ? 'active'
                : 'superseded',
            'suspended' => 'suspended',
            default => $isHead ? 'active' : 'superseded',
        };
    }

    /** @return list<string> */
    private function allowedActions(
        User $actor,
        string $status,
        bool $isHead,
        string $effectiveAt,
    ): array {
        if (! $this->authorization->allows($actor, 'curriculum.publish')) {
            return [];
        }

        if ($status === 'scheduled') {
            $actions = ['cancel_schedule'];

            if (strtotime($effectiveAt) <= now()->getTimestamp()) {
                $actions[] = 'activate';
            }

            return $actions;
        }

        if ($isHead && $status === 'active') {
            return ['retire'];
        }

        if (in_array($status, ['superseded', 'retired'], true)) {
            return ['rollback'];
        }

        return [];
    }

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : '';
    }

    private function nullableInt(stdClass $row, string $property): ?int
    {
        $value = $row->{$property} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function nullableString(stdClass $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : null;
    }

    private function boolean(stdClass $row, string $property): bool
    {
        $value = $row->{$property} ?? null;

        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't';
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
