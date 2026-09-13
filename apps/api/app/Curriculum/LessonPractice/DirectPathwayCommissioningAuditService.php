<?php

declare(strict_types=1);

namespace App\Curriculum\LessonPractice;

use App\Models\LessonPracticeBlueprint;
use App\Models\LessonPracticeMedia;
use App\Models\LessonPracticeReview;

/**
 * LPL-05: read-only completeness matrix for Direct lesson-practice Levels 1–17.
 */
final class DirectPathwayCommissioningAuditService
{
    public const string CHECKPOINT = 'LPL-05';

    public const string APPROVAL = 'WAVE-7.1';

    private const string ORG_ID = '01KYS97G7MX374P303XJHDXT72';

    private const string PROGRAM_ID = '01KZNK3BWD8A8NJ5A5JW0ZVZMT';

    public function __construct(
        private readonly LessonPracticeBlueprintValidator $validator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function audit(): array
    {
        $concepts = config('level_sessions.level_concepts', []);
        $levels = [];
        $matrix = [
            'media' => 0,
            'transcript' => 0,
            'objective' => 0,
            'blueprint' => 0,
            'english_review' => 0,
            'kannada_review' => 0,
            'quiz_rationale' => 0,
            'apply_task' => 0,
            'rights' => 0,
            'publication' => 0,
            'level_checkpoint' => 0,
        ];

        foreach ($concepts as $levelNumber => $conceptKey) {
            $levelNumber = (int) $levelNumber;
            if ($levelNumber < 1 || ! is_string($conceptKey)) {
                continue;
            }

            $row = $this->auditLevel($levelNumber, $conceptKey);
            $levels[] = $row;

            if (($row['availability'] ?? '') === 'available') {
                foreach ($matrix as $field => $count) {
                    if (($row['matrix'][$field] ?? false) === true) {
                        $matrix[$field]++;
                    }
                }
            }
        }

        usort(
            $levels,
            static fn (array $left, array $right): int => $left['level_number'] <=> $right['level_number'],
        );

        $enabled = count($levels);
        $commissioned = count(array_filter(
            $levels,
            static fn (array $row): bool => ($row['availability'] ?? '') === 'available',
        ));
        $complete = count(array_filter(
            $levels,
            static fn (array $row): bool => ($row['complete'] ?? false) === true,
        ));

        return [
            'checkpoint' => self::CHECKPOINT,
            'approval_reference' => self::APPROVAL,
            'organization_id' => self::ORG_ID,
            'program_id' => self::PROGRAM_ID,
            'levels_configured' => $enabled,
            'levels_commissioned' => $commissioned,
            'levels_complete' => $complete,
            'all_complete' => $enabled > 0 && $complete === $enabled,
            'waves' => $this->waveSummary($levels),
            'matrix_totals' => $matrix,
            'levels' => $levels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditLevel(int $levelNumber, string $conceptKey): array
    {
        $blueprint = LessonPracticeBlueprint::query()
            ->where('organization_id', self::ORG_ID)
            ->where('program_id', self::PROGRAM_ID)
            ->where('concept_key', $conceptKey)
            ->where('state', 'published')
            ->orderByDesc('revision_number')
            ->first();

        if ($blueprint === null) {
            return [
                'level_number' => $levelNumber,
                'concept_key' => $conceptKey,
                'wave' => $this->waveForLevel($levelNumber),
                'availability' => 'commissioning_pending',
                'unavailable_reason' => 'blueprint_not_published',
                'complete' => false,
                'matrix' => $this->emptyMatrix(),
            ];
        }

        $canonical = $blueprint->canonical_json;
        $contentHash = (string) $blueprint->content_hash;
        $media = LessonPracticeMedia::query()
            ->where('organization_id', self::ORG_ID)
            ->where('blueprint_id', $blueprint->getKey())
            ->orderBy('part_order')
            ->get();

        $expectedParts = config('level_sessions.session_part_ids', []);
        $attachedPartIds = $media
            ->map(static fn (LessonPracticeMedia $row): string => (string) $row->part_id)
            ->all();

        $englishReview = $this->hasReview(
            (string) $blueprint->getKey(),
            $contentHash,
            'english_academic',
        );
        $kannadaReview = $this->hasReview(
            (string) $blueprint->getKey(),
            $contentHash,
            'kannada',
        );

        $publication = is_array($canonical['publication'] ?? null)
            ? $canonical['publication']
            : [];
        $checkItems = is_array($canonical['check']['items'] ?? null)
            ? array_values($canonical['check']['items'])
            : [];
        $checkpointItems = is_array($canonical['level_checkpoint']['items'] ?? null)
            ? array_values($canonical['level_checkpoint']['items'])
            : [];
        $apply = is_array($canonical['apply'] ?? null) ? $canonical['apply'] : [];

        $matrix = [
            'media' => $media->count() === count($expectedParts)
                && $attachedPartIds === $expectedParts,
            'transcript' => $media->every(
                static fn (LessonPracticeMedia $row): bool => $row->metadata_revision_id !== ''
                    && $row->metadata_content_hash !== '',
            ),
            'objective' => is_string($canonical['lesson']['objective'] ?? null)
                && trim((string) $canonical['lesson']['objective']) !== '',
            'blueprint' => $this->validator->validate($canonical) === [],
            'english_review' => $englishReview,
            'kannada_review' => $kannadaReview,
            'quiz_rationale' => $this->itemsHaveRationales($checkItems),
            'apply_task' => is_string($apply['task'] ?? null)
                && trim((string) $apply['task']) !== ''
                && is_array($apply['rubric'] ?? null)
                && $apply['rubric'] !== [],
            'rights' => $media->every(
                static fn (LessonPracticeMedia $row): bool => $row->media_rights_revision_id !== '',
            ),
            'publication' => ($publication['eligible'] ?? false) === true
                && ($publication['blockers'] ?? null) === [],
            'level_checkpoint' => count($checkpointItems) >= 3
                && $this->itemsHaveRationales($checkpointItems),
        ];

        $complete = ! in_array(false, $matrix, true);

        return [
            'level_number' => $levelNumber,
            'concept_key' => $conceptKey,
            'wave' => $this->waveForLevel($levelNumber),
            'availability' => 'available',
            'unavailable_reason' => $complete ? null : 'commissioning_incomplete',
            'blueprint_id' => (string) $blueprint->getKey(),
            'revision_number' => (int) $blueprint->revision_number,
            'content_hash' => $contentHash,
            'complete' => $complete,
            'matrix' => $matrix,
            'blockers' => array_keys(array_filter(
                $matrix,
                static fn (bool $passed): bool => ! $passed,
            )),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function emptyMatrix(): array
    {
        return [
            'media' => false,
            'transcript' => false,
            'objective' => false,
            'blueprint' => false,
            'english_review' => false,
            'kannada_review' => false,
            'quiz_rationale' => false,
            'apply_task' => false,
            'rights' => false,
            'publication' => false,
            'level_checkpoint' => false,
        ];
    }

    private function hasReview(
        string $blueprintId,
        string $contentHash,
        string $reviewKind,
    ): bool {
        return LessonPracticeReview::query()
            ->where('organization_id', self::ORG_ID)
            ->where('blueprint_id', $blueprintId)
            ->where('review_kind', $reviewKind)
            ->where('blueprint_content_hash', $contentHash)
            ->where('decision', 'approved')
            ->exists();
    }

    /**
     * @param  list<mixed>  $items
     */
    private function itemsHaveRationales(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                return false;
            }

            $rationale = $item['rationale'] ?? null;
            if (! is_string($rationale) || trim($rationale) === '') {
                return false;
            }
        }

        return true;
    }

    private function waveForLevel(int $levelNumber): string
    {
        if ($levelNumber === 1) {
            return 'wave_1_level_1';
        }

        if ($levelNumber <= 3) {
            return 'wave_2_levels_2_3';
        }

        return 'wave_3_levels_4_17';
    }

    /**
     * @param  list<array<string, mixed>>  $levels
     * @return array<string, mixed>
     */
    private function waveSummary(array $levels): array
    {
        $waves = [
            'wave_1_level_1' => ['levels' => 0, 'complete' => 0],
            'wave_2_levels_2_3' => ['levels' => 0, 'complete' => 0],
            'wave_3_levels_4_17' => ['levels' => 0, 'complete' => 0],
        ];

        foreach ($levels as $row) {
            $wave = (string) ($row['wave'] ?? '');
            if (! isset($waves[$wave])) {
                continue;
            }

            $waves[$wave]['levels']++;
            if (($row['complete'] ?? false) === true) {
                $waves[$wave]['complete']++;
            }
        }

        foreach ($waves as $wave => $summary) {
            $waves[$wave]['all_complete'] = $summary['levels'] > 0
                && $summary['complete'] === $summary['levels'];
        }

        return $waves;
    }
}
