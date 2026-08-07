<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class OperatorCurriculumDraftQuery
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

        $rows = DB::table('curriculum_versions as versions')
            ->join('programs as programs', function ($join): void {
                $join
                    ->on(
                        'programs.organization_id',
                        '=',
                        'versions.organization_id',
                    )
                    ->on('programs.id', '=', 'versions.program_id');
            })
            ->leftJoin(
                'publication_heads as heads',
                function ($join): void {
                    $join
                        ->on(
                            'heads.organization_id',
                            '=',
                            'versions.organization_id',
                        )
                        ->on('heads.program_id', '=', 'versions.program_id');
                },
            )
            ->leftJoin(
                'curriculum_publications as publications',
                function ($join): void {
                    $join
                        ->on(
                            'publications.organization_id',
                            '=',
                            'heads.organization_id',
                        )
                        ->on(
                            'publications.id',
                            '=',
                            'heads.active_publication_id',
                        );
                },
            )
            ->where('versions.organization_id', $organizationId)
            ->orderBy('programs.key')
            ->orderBy('versions.key')
            ->get([
                'programs.id as program_id',
                'programs.key as program_key',
                'programs.internal_name as program_internal_name',
                'versions.id as curriculum_version_id',
                'versions.key as version_key',
                'heads.lock_version as head_version',
                'heads.active_publication_id as head_publication_id',
                'heads.active_curriculum_version_id as head_curriculum_version_id',
                'publications.manifest_hash as head_manifest_hash',
                'publications.publication_number as head_publication_number',
            ]);

        $items = [];

        foreach ($rows as $row) {
            if (! $row instanceof stdClass) {
                continue;
            }

            $versionId = $this->string($row, 'curriculum_version_id');
            $headVersionId = $this->nullableString(
                $row,
                'head_curriculum_version_id',
            );
            $publicationStatus = 'unpublished';

            if ($headVersionId !== null) {
                $publicationStatus = hash_equals($headVersionId, $versionId)
                    ? 'published_head'
                    : 'superseded_or_other_head';
            }

            $reviewState = $this->reviewState($organizationId, $versionId);

            $items[] = [
                'program_id' => $this->string($row, 'program_id'),
                'program_key' => $this->string($row, 'program_key'),
                'program_internal_name' => $this->string(
                    $row,
                    'program_internal_name',
                ),
                'curriculum_version_id' => $versionId,
                'version_key' => $this->string($row, 'version_key'),
                'publication_status' => $publicationStatus,
                'head_version' => $this->nullableInt($row, 'head_version') ?? 0,
                'head_publication_id' => $this->nullableString(
                    $row,
                    'head_publication_id',
                ),
                'head_manifest_hash' => $this->nullableString(
                    $row,
                    'head_manifest_hash',
                ),
                'head_publication_number' => $this->nullableInt(
                    $row,
                    'head_publication_number',
                ),
                'latest_english_decision' => $reviewState['english'],
                'latest_kannada_decision' => $reviewState['kannada'],
            ];
        }

        return ['items' => $items];
    }

    /**
     * @return array{english: ?string, kannada: ?string}
     */
    private function reviewState(
        string $organizationId,
        string $curriculumVersionId,
    ): array {
        $english = DB::table('content_reviews')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('review_kind', 'academic_en')
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->value('decision');
        $kannada = DB::table('content_reviews')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('review_kind', 'kannada')
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->value('decision');

        return [
            'english' => is_string($english) ? $english : null,
            'kannada' => is_string($kannada) ? $kannada : null,
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
}
