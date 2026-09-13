<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AdminMediaLibrarySchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const array TABLES = [
        'media_uploads',
        'media_upload_parts',
        'media_security_scans',
        'media_upload_events',
        'media_asset_lifecycles',
        'media_asset_events',
        'media_asset_metadata_revisions',
        'media_asset_metadata_heads',
        'media_asset_metadata_variants',
        'media_asset_metadata_variant_heads',
        'media_asset_metadata_reviews',
        'media_asset_replacements',
        'media_attachment_events',
        'media_object_deletion_requests',
        'publication_media_governance_components',
    ];

    public function test_media_library_migration_rolls_back_and_reapplies_additively(): void
    {
        $transcriptionMigration = require database_path(
            'migrations/2026_08_10_000170_create_media_transcription_commissioning.php',
        );
        $migration = require database_path(
            'migrations/2026_08_09_000150_create_admin_media_library.php',
        );

        if (
            ! $transcriptionMigration instanceof Migration
            || ! method_exists($transcriptionMigration, 'down')
            || ! method_exists($transcriptionMigration, 'up')
        ) {
            self::fail('The transcription commissioning migration must be executable.');
        }

        if (
            ! $migration instanceof Migration
            || ! method_exists($migration, 'down')
            || ! method_exists($migration, 'up')
        ) {
            self::fail('The Media Library migration must be executable.');
        }

        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), $table.' was not applied.');
        }

        $transcriptionMigration->down();
        $migration->down();

        foreach (self::TABLES as $table) {
            self::assertFalse(Schema::hasTable($table), $table.' was not rolled back.');
        }
        self::assertTrue(Schema::hasTable('media_assets'));
        self::assertTrue(Schema::hasTable('media_rights_revisions'));
        self::assertTrue(Schema::hasTable('curriculum_publications'));

        $migration->up();
        $transcriptionMigration->up();

        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), $table.' was not reapplied.');
        }
        self::assertTrue(
            Schema::hasColumn(
                'publication_media_governance_components',
                'media_rights_revision_id',
            ),
        );
        self::assertTrue(
            Schema::hasColumn(
                'publication_media_governance_components',
                'rights_evidence_status',
            ),
        );
        self::assertTrue(
            Schema::hasColumn(
                'media_attachment_events',
                'kannada_review_id',
            ),
        );
    }

    public function test_lesson_audio_constraint_migration_rolls_back_and_reapplies(): void
    {
        $migration = require database_path(
            'migrations/2026_08_10_000160_add_lesson_audio_media_kind.php',
        );

        if (
            ! $migration instanceof Migration
            || ! method_exists($migration, 'down')
            || ! method_exists($migration, 'up')
        ) {
            self::fail('The lesson-audio migration must be executable.');
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $migration->down();
            $migration->up();
            self::assertTrue(Schema::hasTable('media_uploads'));

            return;
        }

        foreach ($this->lessonAudioConstraintDefinitions() as $definition) {
            self::assertStringContainsString('lesson_audio', $definition);
        }

        $migration->down();

        foreach ($this->lessonAudioConstraintDefinitions() as $definition) {
            self::assertStringNotContainsString('lesson_audio', $definition);
        }

        $migration->up();

        foreach ($this->lessonAudioConstraintDefinitions() as $definition) {
            self::assertStringContainsString('lesson_audio', $definition);
        }
    }

    /** @return list<string> */
    private function lessonAudioConstraintDefinitions(): array
    {
        $names = [
            'media_uploads_values_check',
            'media_security_scans_values_check',
            'media_asset_lifecycles_values_check',
            'media_metadata_revisions_values_check',
            'media_metadata_variants_values_check',
            'media_attachment_events_values_check',
            'publication_media_governance_values_check',
        ];
        $rows = DB::table('pg_constraint')
            ->whereIn('conname', $names)
            ->orderBy('conname')
            ->get([
                'conname',
                DB::raw('pg_get_constraintdef(oid) AS definition'),
            ]);
        self::assertCount(count($names), $rows);

        return array_values($rows
            ->map(static fn (object $row): string => (string) $row->definition)
            ->all());
    }
}
