<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MediaTranscriptionCommissioningSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const array TABLES = [
        'media_transcription_campaigns',
        'media_transcription_campaign_assets',
        'media_transcription_provider_attempts',
        'media_transcription_events',
        'media_transcription_candidate_deletions',
        'media_transcription_reconciliations',
    ];

    public function test_commissioning_schema_is_additive_tenant_owned_and_content_free(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
            $this->assertTrue(Schema::hasColumn($table, 'organization_id'), $table);
        }

        $this->assertTrue(Schema::hasColumns('media_transcription_campaigns', [
            'program_id', 'approval_reference', 'manifest_sha256',
            'configuration_sha256', 'reserved_http_attempts',
            'reserved_uploaded_bytes', 'reserved_billable_milliseconds',
            'reserved_estimated_cost_microunits', 'state', 'version',
        ]));
        $this->assertTrue(Schema::hasColumns('media_transcription_campaign_assets', [
            'media_asset_id', 'selected_scan_id', 'media_rights_revision_id',
            'source_sha256', 'candidate_provider_attempt_id',
            'candidate_sha256', 'candidate_expires_at',
        ]));
        $this->assertTrue(Schema::hasColumns('media_transcription_events', [
            'provider_attempt_id', 'attempt_number', 'reserved_uploaded_bytes',
            'reserved_billable_milliseconds',
            'reserved_estimated_cost_microunits',
        ]));
        $this->assertTrue(Schema::hasColumn(
            'media_transcription_provider_attempts',
            'reservation_event_id',
        ));
        $this->assertTrue(Schema::hasColumn(
            'media_transcription_candidate_deletions',
            'provider_attempt_id',
        ));

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'transcript'), $table);
            $this->assertFalse(Schema::hasColumn($table, 'candidate_object_key'), $table);
            $this->assertFalse(Schema::hasColumn($table, 'provider_response'), $table);
        }
    }

    public function test_commissioning_migration_rolls_back_and_reapplies_additively(): void
    {
        $migration = require database_path(
            'migrations/2026_08_10_000170_create_media_transcription_commissioning.php',
        );
        if (
            ! $migration instanceof Migration
            || ! method_exists($migration, 'down')
            || ! method_exists($migration, 'up')
        ) {
            self::fail('The media transcription migration must be executable.');
        }

        $migration->down();
        foreach (self::TABLES as $table) {
            self::assertFalse(Schema::hasTable($table), $table.' was not rolled back.');
        }
        self::assertTrue(Schema::hasTable('media_assets'));
        self::assertTrue(Schema::hasTable('media_rights_revisions'));
        self::assertTrue(Schema::hasTable('programs'));

        $migration->up();
        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), $table.' was not reapplied.');
        }
    }
}
