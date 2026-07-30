<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Curriculum\CanonicalJson;
use App\Curriculum\CurriculumGraphProjector;
use App\Curriculum\Exceptions\CurriculumOperationRejected;
use App\Models\Membership;
use Database\Seeders\FoundationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CurriculumFoundationSchemaTest extends TenancyIntegrationTestCase
{
    public function test_foundation_seed_is_a_single_unapproved_synthetic_draft(): void
    {
        $this->seed(FoundationSeeder::class);

        $this->assertSame(21, DB::table('permissions')->count());
        $this->assertSame(1, DB::table('curriculum_missions')->count());
        $this->assertSame(0, DB::table('localized_variants')->count());
        $this->assertSame(0, DB::table('content_reviews')->count());
        $this->assertSame(1, DB::table('curriculum_graph_revisions')->count());
        $this->assertSame(1, DB::table('curriculum_graph_heads')->count());
        $this->assertSame(0, DB::table('curriculum_graph_reviews')->count());
        $this->assertSame(0, DB::table('curriculum_publications')->count());
        $this->assertSame(0, DB::table('publication_graph_components')->count());
        $this->assertDatabaseHas('content_documents', [
            'key' => 'synthetic-introduction-instruction',
        ]);
        $this->assertDatabaseHas('content_revisions', [
            'revision_number' => 1,
            'content_type' => 'instruction',
            'learner_visible' => true,
            'localization_requirement' => 'kn_required',
            'pedagogically_authoritative' => true,
            'author_attribution' => 'codex_synthetic_draft_pending_human_review',
            'authored_by_membership_id' => null,
        ]);

        $graphRevision = DB::table('curriculum_graph_revisions')->sole();
        $graph = json_decode(
            (string) $graphRevision->graph,
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertIsArray($graph);
        $this->assertSame(
            CanonicalJson::sha256($graph),
            $graphRevision->graph_hash,
        );
        $this->assertSame(
            'codex_synthetic_draft_pending_human_review',
            $graphRevision->author_attribution,
        );
        $this->assertNull($graphRevision->authored_by_membership_id);
    }

    public function test_every_curriculum_foundation_table_has_non_nullable_ownership(): void
    {
        $tables = [
            'programs',
            'curriculum_versions',
            'learning_objectives',
            'curriculum_modules',
            'objective_prerequisites',
            'lessons',
            'lesson_objectives',
            'content_documents',
            'content_revisions',
            'content_revision_heads',
            'localized_variants',
            'learning_items',
            'curriculum_missions',
            'mission_items',
            'curriculum_graph_revisions',
            'curriculum_graph_heads',
            'authority_appointments',
            'authority_appointment_events',
            'content_reviews',
            'curriculum_graph_reviews',
            'media_assets',
            'media_rights_revisions',
            'content_revision_media_assets',
            'curriculum_publications',
            'publication_graph_components',
            'publication_content_components',
            'publication_media_components',
            'publication_events',
            'publication_heads',
            'publication_commands',
            'curriculum_share_grants',
            'curriculum_share_grant_events',
            'curriculum_share_grant_heads',
            'curriculum_clones',
        ];

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('table_name', $tables)
            ->where('column_name', 'organization_id')
            ->pluck('is_nullable', 'table_name')
            ->all();

        $this->assertCount(count($tables), $columns);

        foreach ($tables as $table) {
            $this->assertSame(
                'NO',
                $columns[$table] ?? null,
                sprintf('%s must have non-null organization ownership.', $table),
            );
        }

    }

    public function test_governed_classification_and_translator_are_immutable_revision_evidence(): void
    {
        $classificationColumns = [
            'content_type',
            'learner_visible',
            'localization_requirement',
            'pedagogically_authoritative',
        ];

        $this->assertSame(
            0,
            DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', 'content_documents')
                ->whereIn('column_name', $classificationColumns)
                ->count(),
        );

        $revisionColumns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'content_revisions')
            ->whereIn('column_name', $classificationColumns)
            ->pluck('is_nullable', 'column_name')
            ->all();

        $this->assertCount(count($classificationColumns), $revisionColumns);

        foreach ($classificationColumns as $column) {
            $this->assertSame('NO', $revisionColumns[$column] ?? null);
        }

        $this->assertSame(
            'NO',
            DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', 'localized_variants')
                ->where('column_name', 'translated_by_membership_id')
                ->value('is_nullable'),
        );
    }

    public function test_graph_projector_rejects_an_orphan_module(): void
    {
        $version = DB::table('curriculum_versions')
            ->where('key', 'draft-1')
            ->sole(['organization_id', 'id']);
        $now = now();

        DB::table('curriculum_modules')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $version->organization_id,
            'curriculum_version_id' => $version->id,
            'key' => 'orphan-module',
            'sequence' => 2,
            'estimated_minutes' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->expectException(CurriculumOperationRejected::class);

        app(CurriculumGraphProjector::class)->project(
            (string) $version->organization_id,
            (string) $version->id,
        );
    }

    public function test_graph_projector_rejects_a_prerequisite_cycle(): void
    {
        $version = DB::table('curriculum_versions')
            ->where('key', 'draft-1')
            ->sole(['organization_id', 'id']);
        $lesson = DB::table('lessons')
            ->where('curriculum_version_id', $version->id)
            ->sole(['id']);
        $firstObjective = DB::table('learning_objectives')
            ->where('curriculum_version_id', $version->id)
            ->sole(['id']);
        $secondObjectiveId = (string) Str::ulid();
        $now = now();

        DB::table('learning_objectives')->insert([
            'id' => $secondObjectiveId,
            'organization_id' => $version->organization_id,
            'curriculum_version_id' => $version->id,
            'key' => 'cyclic-objective',
            'outcome_code' => 'synthetic.cyclic',
            'sequence' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('lesson_objectives')->insert([
            'organization_id' => $version->organization_id,
            'curriculum_version_id' => $version->id,
            'lesson_id' => $lesson->id,
            'objective_id' => $secondObjectiveId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            [(string) $firstObjective->id, $secondObjectiveId],
            [$secondObjectiveId, (string) $firstObjective->id],
        ] as [$objectiveId, $prerequisiteId]) {
            DB::table('objective_prerequisites')->insert([
                'organization_id' => $version->organization_id,
                'curriculum_version_id' => $version->id,
                'objective_id' => $objectiveId,
                'prerequisite_objective_id' => $prerequisiteId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->expectException(CurriculumOperationRejected::class);

        app(CurriculumGraphProjector::class)->project(
            (string) $version->organization_id,
            (string) $version->id,
        );
    }

    public function test_postgresql_rejects_an_unpinned_active_enrollment(): void
    {
        $organization = $this->createOrganization('Pending Enrollment Tenant');
        $user = $this->createUser();
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
        );
        $cohort = $this->createCohort($context);

        try {
            DB::table('enrollments')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organization->getKey(),
                'learner_membership_id' => $membership->getKey(),
                'cohort_id' => $cohort->getKey(),
                'status' => 'active',
                'enrolled_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            self::fail('An active enrollment must have an immutable publication pin.');
        } catch (QueryException $exception) {
            self::assertSame('23514', (string) $exception->getCode());
            self::assertStringContainsString(
                'enrollments_status_pin_check',
                $exception->getMessage(),
            );
        }
    }

    public function test_postgresql_seals_a_version_graph_after_publication(): void
    {
        $organization = $this->createOrganization('Sealed Curriculum Tenant');
        $user = $this->createUser();
        [$membership] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $now = now();
        $programId = (string) Str::ulid();
        $versionId = (string) Str::ulid();

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organization->getKey(),
            'key' => 'sealed-program',
            'internal_name' => 'Sealed Program',
            'created_by_membership_id' => $membership->getKey(),
            'created_by_attribution' => 'test_fixture',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_versions')->insert([
            'id' => $versionId,
            'organization_id' => $organization->getKey(),
            'program_id' => $programId,
            'key' => 'v1',
            'created_by_membership_id' => $membership->getKey(),
            'created_by_attribution' => 'test_fixture',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('curriculum_publications')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'program_id' => $programId,
            'curriculum_version_id' => $versionId,
            'publication_number' => 1,
            'manifest_schema_version' => 1,
            'manifest' => '{}',
            'manifest_hash' => hash('sha256', '{}'),
            'created_by_membership_id' => $membership->getKey(),
            'created_at' => $now,
        ]);

        try {
            DB::table('learning_objectives')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $organization->getKey(),
                'curriculum_version_id' => $versionId,
                'key' => 'forged-late-objective',
                'outcome_code' => 'forged.late',
                'sequence' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            self::fail('A published curriculum version must reject new graph rows.');
        } catch (QueryException $exception) {
            self::assertSame('55000', (string) $exception->getCode());
            self::assertStringContainsString(
                'Published curriculum graph is immutable',
                $exception->getMessage(),
            );
        }
    }
}
