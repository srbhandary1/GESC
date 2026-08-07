<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class OperatorPublicationStatusQuery
{
    public function __construct(
        private OrganizationContextManager $contexts,
        private OperatorContentAuthorization $authorization,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>}
     */
    public function list(User $actor): array
    {
        $this->authorization->assertCanReadContent($actor);
        $organizationId = $this->contexts->current()->organizationId;

        $rows = DB::table('curriculum_publications as publications')
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
                        'heads.active_publication_id',
                        '=',
                        'publications.id',
                    );
            })
            ->where('publications.organization_id', $organizationId)
            ->orderByDesc('publications.created_at')
            ->orderByDesc('publications.publication_number')
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
            ]);

        $items = [];

        foreach ($rows as $row) {
            if (! $row instanceof stdClass) {
                continue;
            }

            $headVersion = $this->nullableInt($row, 'head_version');

            $items[] = [
                'publication_id' => $this->string($row, 'publication_id'),
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
                'is_head' => $headVersion !== null,
                'head_version' => $headVersion,
                'created_at' => $this->isoTimestamp(
                    $row->created_at ?? null,
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
