<?php

declare(strict_types=1);

namespace App\Operator\ContentWorkspace;

use App\Curriculum\CanonicalJson;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ContentDraftGraphService
{
    public const string DRAFT_SCHEMA = 'global-english.curriculum-draft-graph.v2';

    /** @var array<string, int> */
    private const array LIMITS = [
        'levels' => 25,
        'modules' => 100,
        'objectives' => 500,
        'lessons' => 500,
        'items' => 2_000,
        'delivery_bundles' => 500,
    ];

    /**
     * Validate, materialize and return one canonical graph-v2 draft.
     *
     * @param  array<string, mixed>  $input
     * @return array{graph: array<string, mixed>, graph_hash: string, blocker_codes: list<string>, is_complete: bool}
     */
    public function replace(
        string $organizationId,
        string $curriculumVersionId,
        array $input,
        CarbonInterface $occurredAt,
    ): array {
        $normalized = $this->normalize($input);
        $documentIds = $this->documentIds(
            $organizationId,
            $curriculumVersionId,
            $normalized,
        );

        $this->retireProjection(
            $organizationId,
            $curriculumVersionId,
            $occurredAt,
        );
        $ids = $this->materializeNodes(
            $organizationId,
            $curriculumVersionId,
            $normalized,
            $documentIds,
            $occurredAt,
        );
        $this->materializeEdges(
            $organizationId,
            $curriculumVersionId,
            $normalized,
            $ids,
            $occurredAt,
        );

        $blockers = $this->blockers(
            $organizationId,
            $curriculumVersionId,
            $normalized,
            $documentIds,
        );
        $graph = $this->canonicalGraph(
            $organizationId,
            $curriculumVersionId,
            $normalized,
            $documentIds,
            $ids,
        );

        return [
            'graph' => $graph,
            'graph_hash' => CanonicalJson::sha256($graph),
            'blocker_codes' => $blockers,
            'is_complete' => $blockers === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, list<array<string, mixed>>>
     */
    private function normalize(array $input): array
    {
        $this->exactKeys($input, [
            'schema',
            'levels',
            'objectives',
            'modules',
            'lessons',
            'items',
            'delivery_bundles',
        ]);

        if (($input['schema'] ?? null) !== self::DRAFT_SCHEMA) {
            $this->invalid('The draft graph schema is unsupported.');
        }

        foreach (array_keys(self::LIMITS) as $collection) {
            $value = $input[$collection] ?? null;

            if (
                ! is_array($value)
                || ! array_is_list($value)
                || count($value) > self::LIMITS[$collection]
            ) {
                $this->invalid("The {$collection} collection is invalid.");
            }
        }

        /** @var list<mixed> $levelInput */
        $levelInput = $input['levels'];
        /** @var list<mixed> $objectiveInput */
        $objectiveInput = $input['objectives'];
        /** @var list<mixed> $moduleInput */
        $moduleInput = $input['modules'];
        /** @var list<mixed> $lessonInput */
        $lessonInput = $input['lessons'];
        /** @var list<mixed> $itemInput */
        $itemInput = $input['items'];
        /** @var list<mixed> $bundleInput */
        $bundleInput = $input['delivery_bundles'];

        $levels = [];

        foreach ($levelInput as $row) {
            $row = $this->object($row, ['key', 'sequence']);
            $levels[] = [
                'key' => $this->key($row['key'] ?? null),
                'sequence' => $this->sequence($row['sequence'] ?? null),
            ];
        }

        $objectives = [];

        foreach ($objectiveInput as $row) {
            $row = $this->object(
                $row,
                ['key', 'outcome_code', 'sequence', 'prerequisite_keys'],
            );
            $objectives[] = [
                'key' => $this->key($row['key'] ?? null),
                'outcome_code' => $this->outcomeCode(
                    $row['outcome_code'] ?? null,
                ),
                'sequence' => $this->sequence($row['sequence'] ?? null),
                'prerequisite_keys' => $this->keyList(
                    $row['prerequisite_keys'] ?? null,
                ),
            ];
        }

        $modules = [];

        foreach ($moduleInput as $row) {
            $row = $this->object(
                $row,
                ['key', 'level_key', 'sequence', 'estimated_minutes'],
            );
            $estimatedMinutes = $row['estimated_minutes'] ?? null;

            if (
                $estimatedMinutes !== null
                && (
                    ! is_int($estimatedMinutes)
                    || $estimatedMinutes < 1
                    || $estimatedMinutes > 10_000
                )
            ) {
                $this->invalid('A module estimate is invalid.');
            }

            $modules[] = [
                'key' => $this->key($row['key'] ?? null),
                'level_key' => $this->key($row['level_key'] ?? null),
                'sequence' => $this->sequence($row['sequence'] ?? null),
                'estimated_minutes' => $estimatedMinutes,
            ];
        }

        $lessons = [];

        foreach ($lessonInput as $row) {
            $row = $this->object(
                $row,
                ['key', 'module_key', 'sequence', 'objective_keys'],
            );
            $lessons[] = [
                'key' => $this->key($row['key'] ?? null),
                'module_key' => $this->key($row['module_key'] ?? null),
                'sequence' => $this->sequence($row['sequence'] ?? null),
                'objective_keys' => $this->keyList(
                    $row['objective_keys'] ?? null,
                ),
            ];
        }

        $items = [];

        foreach ($itemInput as $row) {
            $row = $this->object(
                $row,
                [
                    'key',
                    'lesson_key',
                    'content_document_key',
                    'item_type',
                    'sequence',
                ],
            );
            $items[] = [
                'key' => $this->key($row['key'] ?? null),
                'lesson_key' => $this->key($row['lesson_key'] ?? null),
                'content_document_key' => $this->documentKey(
                    $row['content_document_key'] ?? null,
                ),
                'item_type' => $this->itemType($row['item_type'] ?? null),
                'sequence' => $this->sequence($row['sequence'] ?? null),
            ];
        }

        $bundles = [];

        foreach ($bundleInput as $row) {
            $row = $this->object(
                $row,
                [
                    'key',
                    'instruction_document_key',
                    'sequence',
                    'item_keys',
                ],
            );
            $bundles[] = [
                'key' => $this->key($row['key'] ?? null),
                'instruction_document_key' => $this->documentKey(
                    $row['instruction_document_key'] ?? null,
                ),
                'sequence' => $this->sequence($row['sequence'] ?? null),
                'item_keys' => $this->keyList($row['item_keys'] ?? null),
            ];
        }

        $normalized = [
            'levels' => $levels,
            'objectives' => $objectives,
            'modules' => $modules,
            'lessons' => $lessons,
            'items' => $items,
            'delivery_bundles' => $bundles,
        ];
        $this->assertRelationships($normalized);
        $this->sort($normalized);

        if (
            strlen(CanonicalJson::encode([
                'schema' => self::DRAFT_SCHEMA,
                ...$normalized,
            ])) > 1_048_576
        ) {
            $this->invalid('The draft graph is too large.');
        }

        return $normalized;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $graph
     */
    private function assertRelationships(array $graph): void
    {
        $levelKeys = $this->uniqueKeys($graph['levels'], 'level');
        $objectiveKeys = $this->uniqueKeys(
            $graph['objectives'],
            'objective',
        );
        $moduleKeys = $this->uniqueKeys($graph['modules'], 'module');
        $lessonKeys = $this->uniqueKeys($graph['lessons'], 'lesson');
        $itemKeys = $this->uniqueKeys($graph['items'], 'item');
        $this->uniqueKeys($graph['delivery_bundles'], 'delivery bundle');

        $this->uniqueSequences($graph['levels'], 'level');
        $this->uniqueSequences($graph['objectives'], 'objective');
        $this->uniqueSequences($graph['delivery_bundles'], 'delivery bundle');

        $moduleSequences = [];

        foreach ($graph['modules'] as $module) {
            $levelKey = (string) $module['level_key'];

            if (! isset($levelKeys[$levelKey])) {
                $this->invalid('A module references a missing level.');
            }

            $this->parentSequence(
                $moduleSequences,
                $levelKey,
                (int) $module['sequence'],
                'module',
            );
        }

        $lessonSequences = [];
        $edgeCount = count($graph['modules']);

        foreach ($graph['objectives'] as $objective) {
            $key = (string) $objective['key'];

            foreach ($objective['prerequisite_keys'] as $prerequisiteKey) {
                if (
                    $prerequisiteKey === $key
                    || ! isset($objectiveKeys[$prerequisiteKey])
                ) {
                    $this->invalid('An objective prerequisite is invalid.');
                }

                $edgeCount++;
            }
        }

        $this->assertAcyclic($graph['objectives']);

        foreach ($graph['lessons'] as $lesson) {
            $moduleKey = (string) $lesson['module_key'];

            if (! isset($moduleKeys[$moduleKey])) {
                $this->invalid('A lesson references a missing module.');
            }

            $this->parentSequence(
                $lessonSequences,
                $moduleKey,
                (int) $lesson['sequence'],
                'lesson',
            );
            $edgeCount++;

            foreach ($lesson['objective_keys'] as $objectiveKey) {
                if (! isset($objectiveKeys[$objectiveKey])) {
                    $this->invalid('A lesson references a missing objective.');
                }

                $edgeCount++;
            }
        }

        $itemSequences = [];

        foreach ($graph['items'] as $item) {
            $lessonKey = (string) $item['lesson_key'];

            if (! isset($lessonKeys[$lessonKey])) {
                $this->invalid('An item references a missing lesson.');
            }

            $this->parentSequence(
                $itemSequences,
                $lessonKey,
                (int) $item['sequence'],
                'item',
            );
            $edgeCount += 2;
        }

        foreach ($graph['delivery_bundles'] as $bundle) {
            $edgeCount++;

            foreach ($bundle['item_keys'] as $itemKey) {
                if (! isset($itemKeys[$itemKey])) {
                    $this->invalid('A delivery bundle references a missing item.');
                }

                $edgeCount++;
            }
        }

        if ($edgeCount > 5_000) {
            $this->invalid('The draft graph has too many relationships.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $objectives
     */
    private function assertAcyclic(array $objectives): void
    {
        $edges = [];

        foreach ($objectives as $objective) {
            $edges[(string) $objective['key']] = $objective['prerequisite_keys'];
        }

        $state = [];

        foreach (array_keys($edges) as $key) {
            $this->visit($key, $edges, $state);
        }
    }

    /**
     * @param  array<string, list<string>>  $edges
     * @param  array<string, 1|2>  $state
     */
    private function visit(string $key, array $edges, array &$state): void
    {
        if (($state[$key] ?? null) === 1) {
            $this->invalid('Objective prerequisites must be acyclic.');
        }

        if (($state[$key] ?? null) === 2) {
            return;
        }

        $state[$key] = 1;

        foreach ($edges[$key] ?? [] as $dependency) {
            $this->visit($dependency, $edges, $state);
        }

        $state[$key] = 2;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $graph
     * @return array<string, string>
     */
    private function documentIds(
        string $organizationId,
        string $curriculumVersionId,
        array $graph,
    ): array {
        $keys = [];

        foreach ($graph['items'] as $item) {
            $keys[] = (string) $item['content_document_key'];
        }

        foreach ($graph['delivery_bundles'] as $bundle) {
            $keys[] = (string) $bundle['instruction_document_key'];
        }

        $keys = array_values(array_unique($keys));

        if ($keys === []) {
            return [];
        }

        $rows = DB::table('content_documents')
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->whereIn('key', $keys)
            ->get(['id', 'key']);
        $ids = [];

        foreach ($rows as $row) {
            $ids[(string) $row->key] = (string) $row->id;
        }

        if (count($ids) !== count($keys)) {
            $this->invalid('A graph document reference does not exist.');
        }

        return $ids;
    }

    private function retireProjection(
        string $organizationId,
        string $curriculumVersionId,
        CarbonInterface $occurredAt,
    ): void {
        foreach ([
            'curriculum_levels',
            'learning_objectives',
            'curriculum_modules',
            'lessons',
            'learning_items',
            'curriculum_missions',
        ] as $table) {
            DB::table($table)
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->whereNull('retired_at')
                ->update([
                    'retired_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
        }

        foreach ([
            'mission_items',
            'lesson_objectives',
            'objective_prerequisites',
        ] as $table) {
            DB::table($table)
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->delete();
        }
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $graph
     * @param  array<string, string>  $documentIds
     * @return array<string, array<string, string>>
     */
    private function materializeNodes(
        string $organizationId,
        string $curriculumVersionId,
        array $graph,
        array $documentIds,
        CarbonInterface $occurredAt,
    ): array {
        $ids = [
            'levels' => $this->existingIds(
                'curriculum_levels',
                $organizationId,
                $curriculumVersionId,
            ),
            'objectives' => $this->existingIds(
                'learning_objectives',
                $organizationId,
                $curriculumVersionId,
            ),
            'modules' => $this->existingIds(
                'curriculum_modules',
                $organizationId,
                $curriculumVersionId,
            ),
            'lessons' => $this->existingIds(
                'lessons',
                $organizationId,
                $curriculumVersionId,
            ),
            'items' => $this->existingIds(
                'learning_items',
                $organizationId,
                $curriculumVersionId,
            ),
            'delivery_bundles' => $this->existingIds(
                'curriculum_missions',
                $organizationId,
                $curriculumVersionId,
            ),
        ];

        foreach ($graph['levels'] as $row) {
            $id = $ids['levels'][$row['key']] ?? (string) Str::ulid();
            $ids['levels'][$row['key']] = $id;
            $this->persistNode('curriculum_levels', $id, $organizationId, $curriculumVersionId, [
                'key' => $row['key'],
                'sequence' => $row['sequence'],
                'retired_at' => null,
            ], $occurredAt);
        }

        foreach ($graph['objectives'] as $row) {
            $id = $ids['objectives'][$row['key']] ?? (string) Str::ulid();
            $ids['objectives'][$row['key']] = $id;
            $this->persistNode('learning_objectives', $id, $organizationId, $curriculumVersionId, [
                'key' => $row['key'],
                'outcome_code' => $row['outcome_code'],
                'sequence' => $row['sequence'],
                'retired_at' => null,
            ], $occurredAt);
        }

        foreach ($graph['modules'] as $row) {
            $id = $ids['modules'][$row['key']] ?? (string) Str::ulid();
            $ids['modules'][$row['key']] = $id;
            $this->persistNode('curriculum_modules', $id, $organizationId, $curriculumVersionId, [
                'curriculum_level_id' => $ids['levels'][$row['level_key']],
                'key' => $row['key'],
                'sequence' => $row['sequence'],
                'estimated_minutes' => $row['estimated_minutes'],
                'retired_at' => null,
            ], $occurredAt);
        }

        foreach ($graph['lessons'] as $row) {
            $id = $ids['lessons'][$row['key']] ?? (string) Str::ulid();
            $ids['lessons'][$row['key']] = $id;
            $this->persistNode('lessons', $id, $organizationId, $curriculumVersionId, [
                'curriculum_module_id' => $ids['modules'][$row['module_key']],
                'key' => $row['key'],
                'sequence' => $row['sequence'],
                'retired_at' => null,
            ], $occurredAt);
        }

        foreach ($graph['items'] as $row) {
            $id = $ids['items'][$row['key']] ?? (string) Str::ulid();
            $ids['items'][$row['key']] = $id;
            $this->persistNode('learning_items', $id, $organizationId, $curriculumVersionId, [
                'lesson_id' => $ids['lessons'][$row['lesson_key']],
                'content_document_id' => $documentIds[$row['content_document_key']],
                'key' => $row['key'],
                'item_type' => $row['item_type'],
                'sequence' => $row['sequence'],
                'retired_at' => null,
            ], $occurredAt);
        }

        foreach ($graph['delivery_bundles'] as $row) {
            $id = $ids['delivery_bundles'][$row['key']] ?? (string) Str::ulid();
            $ids['delivery_bundles'][$row['key']] = $id;
            $this->persistNode('curriculum_missions', $id, $organizationId, $curriculumVersionId, [
                'instruction_content_document_id' => $documentIds[$row['instruction_document_key']],
                'key' => $row['key'],
                'sequence' => $row['sequence'],
                'retired_at' => null,
            ], $occurredAt);
        }

        return $ids;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $graph
     * @param  array<string, array<string, string>>  $ids
     */
    private function materializeEdges(
        string $organizationId,
        string $curriculumVersionId,
        array $graph,
        array $ids,
        CarbonInterface $occurredAt,
    ): void {
        foreach ($graph['objectives'] as $objective) {
            foreach ($objective['prerequisite_keys'] as $prerequisiteKey) {
                DB::table('objective_prerequisites')->insert([
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'objective_id' => $ids['objectives'][$objective['key']],
                    'prerequisite_objective_id' => $ids['objectives'][$prerequisiteKey],
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
            }
        }

        foreach ($graph['lessons'] as $lesson) {
            foreach ($lesson['objective_keys'] as $objectiveKey) {
                DB::table('lesson_objectives')->insert([
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'lesson_id' => $ids['lessons'][$lesson['key']],
                    'objective_id' => $ids['objectives'][$objectiveKey],
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
            }
        }

        foreach ($graph['delivery_bundles'] as $bundle) {
            foreach (array_values($bundle['item_keys']) as $index => $itemKey) {
                DB::table('mission_items')->insert([
                    'organization_id' => $organizationId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'mission_id' => $ids['delivery_bundles'][$bundle['key']],
                    'learning_item_id' => $ids['items'][$itemKey],
                    'sequence' => $index + 1,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
            }
        }
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $graph
     * @param  array<string, string>  $documentIds
     * @return list<string>
     */
    private function blockers(
        string $organizationId,
        string $curriculumVersionId,
        array $graph,
        array $documentIds,
    ): array {
        $incomplete = false;

        foreach (array_keys(self::LIMITS) as $collection) {
            if ($graph[$collection] === []) {
                $incomplete = true;
            }
        }

        $levelUse = array_fill_keys(array_column($graph['levels'], 'key'), 0);
        $moduleUse = array_fill_keys(array_column($graph['modules'], 'key'), 0);
        $lessonObjectiveUse = array_fill_keys(array_column($graph['lessons'], 'key'), 0);
        $lessonItemUse = $lessonObjectiveUse;
        $objectiveUse = array_fill_keys(array_column($graph['objectives'], 'key'), 0);
        $itemUse = array_fill_keys(array_column($graph['items'], 'key'), 0);

        foreach ($graph['modules'] as $module) {
            $levelUse[$module['level_key']]++;
        }

        foreach ($graph['lessons'] as $lesson) {
            $moduleUse[$lesson['module_key']]++;
            $lessonObjectiveUse[$lesson['key']] += count($lesson['objective_keys']);

            foreach ($lesson['objective_keys'] as $objectiveKey) {
                $objectiveUse[$objectiveKey]++;
            }
        }

        foreach ($graph['items'] as $item) {
            $lessonItemUse[$item['lesson_key']]++;
        }

        foreach ($graph['delivery_bundles'] as $bundle) {
            if ($bundle['item_keys'] === []) {
                $incomplete = true;
            }

            foreach ($bundle['item_keys'] as $itemKey) {
                $itemUse[$itemKey]++;
            }
        }

        foreach ([
            $levelUse,
            $moduleUse,
            $lessonObjectiveUse,
            $lessonItemUse,
            $objectiveUse,
            $itemUse,
        ] as $usage) {
            if (in_array(0, $usage, true)) {
                $incomplete = true;
            }
        }

        if (
            $documentIds !== []
            && DB::table('content_revision_heads')
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->whereIn('content_document_id', array_values($documentIds))
                ->count() !== count($documentIds)
        ) {
            $incomplete = true;
        }

        $blockers = $incomplete ? ['workspace_incomplete'] : [];

        if (isset($levelUse['legacy-unassigned'])) {
            $blockers[] = 'legacy_level_assignment_required';
        }

        sort($blockers, SORT_STRING);

        return $blockers;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $graph
     * @param  array<string, string>  $documentIds
     * @param  array<string, array<string, string>>  $ids
     * @return array<string, mixed>
     */
    private function canonicalGraph(
        string $organizationId,
        string $curriculumVersionId,
        array $graph,
        array $documentIds,
        array $ids,
    ): array {
        return [
            'schema' => self::DRAFT_SCHEMA,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            'levels' => array_map(fn (array $row): array => [
                'id' => $ids['levels'][$row['key']],
                ...$row,
            ], $graph['levels']),
            'objectives' => array_map(fn (array $row): array => [
                'id' => $ids['objectives'][$row['key']],
                ...$row,
                'prerequisite_objective_ids' => array_map(
                    fn (string $key): string => $ids['objectives'][$key],
                    $row['prerequisite_keys'],
                ),
            ], $graph['objectives']),
            'modules' => array_map(fn (array $row): array => [
                'id' => $ids['modules'][$row['key']],
                ...$row,
                'level_id' => $ids['levels'][$row['level_key']],
            ], $graph['modules']),
            'lessons' => array_map(fn (array $row): array => [
                'id' => $ids['lessons'][$row['key']],
                ...$row,
                'module_id' => $ids['modules'][$row['module_key']],
                'objective_ids' => array_map(
                    fn (string $key): string => $ids['objectives'][$key],
                    $row['objective_keys'],
                ),
            ], $graph['lessons']),
            'items' => array_map(fn (array $row): array => [
                'id' => $ids['items'][$row['key']],
                ...$row,
                'lesson_id' => $ids['lessons'][$row['lesson_key']],
                'content_document_id' => $documentIds[$row['content_document_key']],
            ], $graph['items']),
            'delivery_bundles' => array_map(fn (array $row): array => [
                'id' => $ids['delivery_bundles'][$row['key']],
                ...$row,
                'instruction_content_document_id' => $documentIds[$row['instruction_document_key']],
                'item_ids' => array_map(
                    fn (string $key): string => $ids['items'][$key],
                    $row['item_keys'],
                ),
            ], $graph['delivery_bundles']),
        ];
    }

    /** @return array<string, string> */
    private function existingIds(
        string $table,
        string $organizationId,
        string $curriculumVersionId,
    ): array {
        $rows = DB::table($table)
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->get(['id', 'key']);
        $ids = [];

        foreach ($rows as $row) {
            $ids[(string) $row->key] = (string) $row->id;
        }

        return $ids;
    }

    /** @param array<string, mixed> $values */
    private function persistNode(
        string $table,
        string $id,
        string $organizationId,
        string $curriculumVersionId,
        array $values,
        CarbonInterface $occurredAt,
    ): void {
        $exists = DB::table($table)
            ->where('organization_id', $organizationId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->where('id', $id)
            ->exists();

        if ($exists) {
            DB::table($table)
                ->where('organization_id', $organizationId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->where('id', $id)
                ->update([...$values, 'updated_at' => $occurredAt]);

            return;
        }

        DB::table($table)->insert([
            'id' => $id,
            'organization_id' => $organizationId,
            'curriculum_version_id' => $curriculumVersionId,
            ...$values,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $graph
     */
    private function sort(array &$graph): void
    {
        foreach (['levels', 'objectives', 'delivery_bundles'] as $collection) {
            usort($graph[$collection], $this->sequenceSorter(...));
        }

        usort($graph['modules'], fn (array $left, array $right): int => (
            [$left['level_key'], $left['sequence'], $left['key']]
            <=> [$right['level_key'], $right['sequence'], $right['key']]
        ));
        usort($graph['lessons'], fn (array $left, array $right): int => (
            [$left['module_key'], $left['sequence'], $left['key']]
            <=> [$right['module_key'], $right['sequence'], $right['key']]
        ));
        usort($graph['items'], fn (array $left, array $right): int => (
            [$left['lesson_key'], $left['sequence'], $left['key']]
            <=> [$right['lesson_key'], $right['sequence'], $right['key']]
        ));
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function sequenceSorter(array $left, array $right): int
    {
        return [$left['sequence'], $left['key']]
            <=> [$right['sequence'], $right['key']];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, true>
     */
    private function uniqueKeys(array $rows, string $label): array
    {
        $keys = [];

        foreach ($rows as $row) {
            $key = (string) $row['key'];

            if (isset($keys[$key])) {
                $this->invalid("A {$label} key is duplicated.");
            }

            $keys[$key] = true;
        }

        return $keys;
    }

    /** @param list<array<string, mixed>> $rows */
    private function uniqueSequences(array $rows, string $label): void
    {
        $sequences = [];

        foreach ($rows as $row) {
            $sequence = (int) $row['sequence'];

            if (isset($sequences[$sequence])) {
                $this->invalid("A {$label} sequence is duplicated.");
            }

            $sequences[$sequence] = true;
        }
    }

    /** @param array<string, array<int, true>> $sequences */
    private function parentSequence(
        array &$sequences,
        string $parent,
        int $sequence,
        string $label,
    ): void {
        if (isset($sequences[$parent][$sequence])) {
            $this->invalid("A {$label} sequence is duplicated in its parent.");
        }

        $sequences[$parent][$sequence] = true;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function object(mixed $value, array $keys): array
    {
        if (! is_array($value) || array_is_list($value)) {
            $this->invalid('A draft graph node is invalid.');
        }

        $this->exactKeys($value, $keys);

        return $value;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $keys
     */
    private function exactKeys(array $value, array $keys): void
    {
        $actual = array_keys($value);
        sort($actual, SORT_STRING);
        sort($keys, SORT_STRING);

        if ($actual !== $keys) {
            $this->invalid('The draft graph contains unsupported fields.');
        }
    }

    /** @return list<string> */
    private function keyList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $this->invalid('A graph relationship list is invalid.');
        }

        $keys = [];

        foreach ($value as $item) {
            $key = $this->key($item);

            if (isset($keys[$key])) {
                $this->invalid('A graph relationship is duplicated.');
            }

            $keys[$key] = true;
        }

        $result = array_keys($keys);
        sort($result, SORT_STRING);

        return $result;
    }

    private function key(mixed $value): string
    {
        if (
            ! is_string($value)
            || strlen($value) > 100
            || preg_match('/\A[a-z0-9][a-z0-9._-]{0,99}\z/', $value) !== 1
        ) {
            $this->invalid('A draft graph key is invalid.');
        }

        return $value;
    }

    private function documentKey(mixed $value): string
    {
        if (
            ! is_string($value)
            || strlen($value) > 120
            || preg_match('/\A[a-z0-9][a-z0-9._-]{0,119}\z/', $value) !== 1
        ) {
            $this->invalid('A content document key is invalid.');
        }

        return $value;
    }

    private function outcomeCode(mixed $value): string
    {
        if (
            ! is_string($value)
            || preg_match('/\A[A-Z0-9][A-Z0-9_]{0,119}\z/', $value) !== 1
        ) {
            $this->invalid('An objective outcome code is invalid.');
        }

        return $value;
    }

    private function itemType(mixed $value): string
    {
        if (
            ! is_string($value)
            || strlen($value) > 48
            || preg_match('/\A[a-z][a-z0-9_]{0,47}\z/', $value) !== 1
        ) {
            $this->invalid('A learning item type is invalid.');
        }

        return $value;
    }

    private function sequence(mixed $value): int
    {
        if (! is_int($value) || $value < 1 || $value > 2_147_483_647) {
            $this->invalid('A draft graph sequence is invalid.');
        }

        return $value;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['graph' => [$message]]);
    }
}
