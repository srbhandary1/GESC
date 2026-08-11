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

final readonly class OperatorAuditEventQuery
{
    private const string CURSOR_PURPOSE = 'operator.audit-events.v1';

    /**
     * Privileged content-workflow actions exposed to operators.
     * Digests/safe descriptors only — never learner conversation bodies.
     *
     * @var list<string>
     */
    public const array SAFE_ACTIONS = [
        'curriculum.content_review.recorded',
        'curriculum.content_review.withdrawn',
        'curriculum.publication.scheduled',
        'curriculum.publication.activated',
        'curriculum.publication.rollback_activated',
        'curriculum.content_revision.appended',
        'curriculum.localized_variant.appended',
        'privacy.export.requested',
        'privacy.erasure.requested',
    ];

    public function __construct(
        private OrganizationContextManager $contexts,
        private OperatorContentAuthorization $authorization,
        private OperatorCursorCodec $cursors,
    ) {}

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     next_cursor: ?string
     * }
     */
    public function list(
        User $actor,
        int $limit = 50,
        ?string $cursor = null,
    ): array {
        $this->authorization->assertCanReadAudit($actor);
        $organizationId = $this->contexts->current()->organizationId;
        $limit = max(1, min($limit, 100));

        $query = DB::table('audit_events')
            ->where('scope_kind', 'organization')
            ->where('organization_id', $organizationId)
            ->whereIn('action', self::SAFE_ACTIONS);

        if ($cursor !== null) {
            $position = $this->cursors->decode(
                $cursor,
                self::CURSOR_PURPOSE,
                $organizationId,
            );
            $occurredAt = $position['occurred_at'] ?? null;
            $id = $position['id'] ?? null;

            if (
                ! is_string($occurredAt)
                || $occurredAt === ''
                || strlen($occurredAt) > 64
                || ! is_string($id)
                || ! Str::isUlid($id)
            ) {
                throw new InvalidOperatorCursor;
            }

            $query->where(function (Builder $nested) use (
                $occurredAt,
                $id,
            ): void {
                $nested
                    ->where('occurred_at', '<', $occurredAt)
                    ->orWhere(function (Builder $sameTime) use (
                        $occurredAt,
                        $id,
                    ): void {
                        $sameTime
                            ->where('occurred_at', $occurredAt)
                            ->where('id', '<', $id);
                    });
            });
        }

        $rows = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get([
                'id',
                'action',
                'subject_type',
                'subject_id',
                'actor_membership_id',
                'correlation_id',
                'occurred_at',
            ]);
        $hasMore = $rows->count() > $limit;
        $visibleRows = $rows->take($limit);

        $items = [];

        foreach ($visibleRows as $row) {
            $items[] = [
                'id' => $this->string($row, 'id'),
                'action' => $this->string($row, 'action'),
                'subject_type' => $this->string($row, 'subject_type'),
                'subject_id' => $this->string($row, 'subject_id'),
                'actor_membership_id' => $this->nullableString(
                    $row,
                    'actor_membership_id',
                ),
                'correlation_id' => $this->nullableString(
                    $row,
                    'correlation_id',
                ),
                'occurred_at' => $this->isoTimestamp(
                    $row->occurred_at ?? null,
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
                self::CURSOR_PURPOSE,
                $organizationId,
                [
                    'occurred_at' => $this->isoTimestamp(
                        $last->occurred_at ?? null,
                    ),
                    'id' => $this->string($last, 'id'),
                ],
            );
        }

        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
        ];
    }

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : '';
    }

    private function nullableString(
        stdClass $row,
        string $property,
    ): ?string {
        $value = $row->{$property} ?? null;

        return is_string($value) ? $value : null;
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
