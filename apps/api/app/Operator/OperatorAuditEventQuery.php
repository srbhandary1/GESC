<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class OperatorAuditEventQuery
{
    /**
     * Privileged content-workflow actions exposed to operators.
     * Digests/safe descriptors only — never learner conversation bodies.
     *
     * @var list<string>
     */
    private const array ALLOWED_ACTIONS = [
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
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public function list(User $actor, int $limit = 50): array
    {
        $this->authorization->assertCanReadAudit($actor);
        $organizationId = $this->contexts->current()->organizationId;
        $limit = max(1, min($limit, 100));

        $rows = DB::table('audit_events')
            ->where('scope_kind', 'organization')
            ->where('organization_id', $organizationId)
            ->whereIn('action', self::ALLOWED_ACTIONS)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'action',
                'subject_type',
                'subject_id',
                'actor_membership_id',
                'correlation_id',
                'occurred_at',
            ]);

        $items = [];

        foreach ($rows as $row) {
            if (! $row instanceof stdClass) {
                continue;
            }

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

        return ['items' => $items];
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
