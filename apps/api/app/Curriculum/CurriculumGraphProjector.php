<?php

declare(strict_types=1);

namespace App\Curriculum;

use App\Curriculum\Exceptions\CurriculumOperationRejected;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

final class CurriculumGraphProjector
{
    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     graph_hash: string
     * }
     */
    public function project(
        string $organizationId,
        string $curriculumVersionId,
    ): array {
        $versionExists = DB::table('curriculum_versions')
            ->where('organization_id', $organizationId)
            ->where('id', $curriculumVersionId)
            ->exists();

        if (! $versionExists) {
            throw new CurriculumOperationRejected;
        }

        $objectives = $this->rows(
            DB::table('learning_objectives')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->orderBy('sequence')
                ->orderBy('key')
                ->orderBy('id')
                ->get(['id', 'key', 'outcome_code', 'sequence']),
        );
        $prerequisites = $this->rows(
            DB::table('objective_prerequisites')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->orderBy('objective_id')
                ->orderBy('prerequisite_objective_id')
                ->get(['objective_id', 'prerequisite_objective_id']),
        );
        $modules = $this->rows(
            DB::table('curriculum_modules')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->orderBy('sequence')
                ->orderBy('key')
                ->orderBy('id')
                ->get(['id', 'key', 'sequence', 'estimated_minutes']),
        );
        $lessons = $this->rows(
            DB::table('lessons')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->orderBy('curriculum_module_id')
                ->orderBy('sequence')
                ->orderBy('key')
                ->orderBy('id')
                ->get([
                    'id',
                    'curriculum_module_id',
                    'key',
                    'sequence',
                ]),
        );
        $lessonObjectives = $this->rows(
            DB::table('lesson_objectives')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->orderBy('lesson_id')
                ->orderBy('objective_id')
                ->get(['lesson_id', 'objective_id']),
        );
        $items = $this->rows(
            DB::table('learning_items')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->orderBy('lesson_id')
                ->orderBy('sequence')
                ->orderBy('key')
                ->orderBy('id')
                ->get([
                    'id',
                    'lesson_id',
                    'content_document_id',
                    'key',
                    'item_type',
                    'sequence',
                ]),
        );
        $missions = $this->rows(
            DB::table('curriculum_missions')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->orderBy('sequence')
                ->orderBy('key')
                ->orderBy('id')
                ->get([
                    'id',
                    'instruction_content_document_id',
                    'key',
                    'sequence',
                ]),
        );
        $missionItems = $this->rows(
            DB::table('mission_items')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->orderBy('mission_id')
                ->orderBy('sequence')
                ->orderBy('learning_item_id')
                ->get(['mission_id', 'learning_item_id', 'sequence']),
        );

        $objectiveIds = $this->idSet($objectives);
        $moduleIds = $this->idSet($modules);
        $lessonIds = $this->idSet($lessons);
        $itemIds = $this->idSet($items);
        $missionIds = $this->idSet($missions);

        if (
            $objectiveIds === []
            || $moduleIds === []
            || $lessonIds === []
            || $itemIds === []
            || $missionIds === []
        ) {
            throw new CurriculumOperationRejected;
        }

        $moduleLessonCount = array_fill_keys(array_keys($moduleIds), 0);
        $lessonObjectiveCount = array_fill_keys(array_keys($lessonIds), 0);
        $lessonItemCount = array_fill_keys(array_keys($lessonIds), 0);
        $objectiveUsageCount = array_fill_keys(array_keys($objectiveIds), 0);
        $itemUsageCount = array_fill_keys(array_keys($itemIds), 0);
        $missionItemCount = array_fill_keys(array_keys($missionIds), 0);

        foreach ($lessons as $lesson) {
            $moduleId = $this->string($lesson, 'curriculum_module_id');

            if (! isset($moduleIds[$moduleId])) {
                throw new CurriculumOperationRejected;
            }

            $moduleLessonCount[$moduleId]++;
        }

        foreach ($lessonObjectives as $edge) {
            $lessonId = $this->string($edge, 'lesson_id');
            $objectiveId = $this->string($edge, 'objective_id');

            if (
                ! isset($lessonIds[$lessonId])
                || ! isset($objectiveIds[$objectiveId])
            ) {
                throw new CurriculumOperationRejected;
            }

            $lessonObjectiveCount[$lessonId]++;
            $objectiveUsageCount[$objectiveId]++;
        }

        foreach ($items as $item) {
            $lessonId = $this->string($item, 'lesson_id');

            if (! isset($lessonIds[$lessonId])) {
                throw new CurriculumOperationRejected;
            }

            $lessonItemCount[$lessonId]++;
        }

        foreach ($missionItems as $edge) {
            $missionId = $this->string($edge, 'mission_id');
            $itemId = $this->string($edge, 'learning_item_id');

            if (
                ! isset($missionIds[$missionId])
                || ! isset($itemIds[$itemId])
            ) {
                throw new CurriculumOperationRejected;
            }

            $missionItemCount[$missionId]++;
            $itemUsageCount[$itemId]++;
        }

        $this->assertEveryNodeUsed($moduleLessonCount);
        $this->assertEveryNodeUsed($lessonObjectiveCount);
        $this->assertEveryNodeUsed($lessonItemCount);
        $this->assertEveryNodeUsed($objectiveUsageCount);
        $this->assertEveryNodeUsed($itemUsageCount);
        $this->assertEveryNodeUsed($missionItemCount);
        $this->assertAcyclicPrerequisites($objectiveIds, $prerequisites);

        $graph = [
            'schema' => 'global-english.curriculum-graph.v1',
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'objectives' => array_map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'key' => $this->string($row, 'key'),
                'outcome_code' => $this->string($row, 'outcome_code'),
                'sequence' => $this->integer($row, 'sequence'),
            ], $objectives),
            'objective_prerequisites' => array_map(
                fn (stdClass $row): array => [
                    'objective_id' => $this->string($row, 'objective_id'),
                    'prerequisite_objective_id' => $this->string(
                        $row,
                        'prerequisite_objective_id',
                    ),
                ],
                $prerequisites,
            ),
            'modules' => array_map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'key' => $this->string($row, 'key'),
                'sequence' => $this->integer($row, 'sequence'),
                'estimated_minutes' => $this->nullableInteger(
                    $row,
                    'estimated_minutes',
                ),
            ], $modules),
            'lessons' => array_map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'module_id' => $this->string(
                    $row,
                    'curriculum_module_id',
                ),
                'key' => $this->string($row, 'key'),
                'sequence' => $this->integer($row, 'sequence'),
            ], $lessons),
            'lesson_objectives' => array_map(
                fn (stdClass $row): array => [
                    'lesson_id' => $this->string($row, 'lesson_id'),
                    'objective_id' => $this->string($row, 'objective_id'),
                ],
                $lessonObjectives,
            ),
            'learning_items' => array_map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'lesson_id' => $this->string($row, 'lesson_id'),
                'content_document_id' => $this->string(
                    $row,
                    'content_document_id',
                ),
                'key' => $this->string($row, 'key'),
                'item_type' => $this->string($row, 'item_type'),
                'sequence' => $this->integer($row, 'sequence'),
            ], $items),
            'missions' => array_map(fn (stdClass $row): array => [
                'id' => $this->string($row, 'id'),
                'instruction_content_document_id' => $this->string(
                    $row,
                    'instruction_content_document_id',
                ),
                'key' => $this->string($row, 'key'),
                'sequence' => $this->integer($row, 'sequence'),
            ], $missions),
            'mission_items' => array_map(fn (stdClass $row): array => [
                'mission_id' => $this->string($row, 'mission_id'),
                'learning_item_id' => $this->string(
                    $row,
                    'learning_item_id',
                ),
                'sequence' => $this->integer($row, 'sequence'),
            ], $missionItems),
        ];

        return [
            'graph' => $graph,
            'graph_hash' => CanonicalJson::sha256($graph),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return list<stdClass>
     */
    private function rows(Collection $rows): array
    {
        $records = [];

        foreach ($rows as $row) {
            if (! $row instanceof stdClass) {
                throw new CurriculumOperationRejected;
            }

            $records[] = $row;
        }

        return $records;
    }

    /**
     * @param  list<stdClass>  $rows
     * @return array<string, true>
     */
    private function idSet(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $id = $this->string($row, 'id');

            if (isset($ids[$id])) {
                throw new CurriculumOperationRejected;
            }

            $ids[$id] = true;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $usage
     */
    private function assertEveryNodeUsed(array $usage): void
    {
        foreach ($usage as $count) {
            if ($count < 1) {
                throw new CurriculumOperationRejected;
            }
        }
    }

    /**
     * @param  array<string, true>  $objectiveIds
     * @param  list<stdClass>  $prerequisites
     */
    private function assertAcyclicPrerequisites(
        array $objectiveIds,
        array $prerequisites,
    ): void {
        $edges = [];

        foreach (array_keys($objectiveIds) as $objectiveId) {
            $edges[$objectiveId] = [];
        }

        foreach ($prerequisites as $edge) {
            $objectiveId = $this->string($edge, 'objective_id');
            $prerequisiteId = $this->string(
                $edge,
                'prerequisite_objective_id',
            );

            if (
                ! isset($objectiveIds[$objectiveId])
                || ! isset($objectiveIds[$prerequisiteId])
                || $objectiveId === $prerequisiteId
            ) {
                throw new CurriculumOperationRejected;
            }

            $edges[$objectiveId][] = $prerequisiteId;
        }

        $state = [];

        foreach (array_keys($objectiveIds) as $objectiveId) {
            $this->visitObjective($objectiveId, $edges, $state);
        }
    }

    /**
     * @param  array<string, list<string>>  $edges
     * @param  array<string, 1|2>  $state
     */
    private function visitObjective(
        string $objectiveId,
        array $edges,
        array &$state,
    ): void {
        $current = $state[$objectiveId] ?? null;

        if ($current === 1) {
            throw new CurriculumOperationRejected;
        }

        if ($current === 2) {
            return;
        }

        $state[$objectiveId] = 1;

        foreach ($edges[$objectiveId] ?? [] as $prerequisiteId) {
            $this->visitObjective($prerequisiteId, $edges, $state);
        }

        $state[$objectiveId] = 2;
    }

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (! is_string($value) || $value === '') {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function integer(stdClass $row, string $property): int
    {
        $value = $row->{$property} ?? null;

        if (! is_int($value) || $value < 0) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }

    private function nullableInteger(stdClass $row, string $property): ?int
    {
        $value = $row->{$property} ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $value < 0) {
            throw new CurriculumOperationRejected;
        }

        return $value;
    }
}
