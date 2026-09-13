<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AdminContentWorkspaceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_workspace_migration_rolls_back_and_reapplies_additively(): void
    {
        $transcriptionMigration = require database_path(
            'migrations/2026_08_10_000170_create_media_transcription_commissioning.php',
        );
        $mediaMigration = require database_path(
            'migrations/2026_08_09_000150_create_admin_media_library.php',
        );
        $migration = require database_path(
            'migrations/2026_08_09_000140_create_admin_content_workspace.php',
        );

        if (
            ! $transcriptionMigration instanceof Migration
            || ! method_exists($transcriptionMigration, 'down')
            || ! method_exists($transcriptionMigration, 'up')
        ) {
            self::fail('The transcription commissioning migration must be executable.');
        }

        if (
            ! $mediaMigration instanceof Migration
            || ! method_exists($mediaMigration, 'down')
            || ! method_exists($mediaMigration, 'up')
        ) {
            self::fail('The media library migration must be executable.');
        }

        if (
            ! $migration instanceof Migration
            || ! method_exists($migration, 'down')
            || ! method_exists($migration, 'up')
        ) {
            self::fail('The content workspace migration must be executable.');
        }

        $transcriptionMigration->down();
        $mediaMigration->down();
        $migration->down();

        self::assertFalse(Schema::hasTable('curriculum_levels'));
        self::assertFalse(Schema::hasTable('curriculum_workspaces'));
        self::assertFalse(Schema::hasTable('curriculum_draft_graph_revisions'));
        self::assertFalse(Schema::hasTable('curriculum_workspace_events'));
        self::assertFalse(Schema::hasTable('content_workspace_commands'));
        self::assertFalse(Schema::hasTable('program_descriptor_events'));
        self::assertFalse(Schema::hasColumn('programs', 'descriptor_version'));
        self::assertFalse(Schema::hasColumn('curriculum_modules', 'curriculum_level_id'));
        self::assertTrue(Schema::hasTable('curriculum_graph_revisions'));
        self::assertTrue(Schema::hasTable('curriculum_publications'));

        $migration->up();
        $mediaMigration->up();
        $transcriptionMigration->up();

        self::assertTrue(Schema::hasTable('curriculum_levels'));
        self::assertTrue(Schema::hasTable('curriculum_workspaces'));
        self::assertTrue(Schema::hasTable('curriculum_draft_graph_revisions'));
        self::assertTrue(Schema::hasTable('curriculum_workspace_events'));
        self::assertTrue(Schema::hasTable('content_workspace_commands'));
        self::assertTrue(Schema::hasTable('program_descriptor_events'));
        self::assertTrue(Schema::hasColumn('programs', 'descriptor_version'));
        self::assertTrue(Schema::hasColumn('curriculum_modules', 'curriculum_level_id'));
        self::assertTrue(Schema::hasTable('curriculum_graph_revisions'));
        self::assertTrue(Schema::hasTable('curriculum_publications'));
    }
}
