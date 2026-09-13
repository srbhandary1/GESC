<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replacePostgresChecks(includeLessonAudio: true);
    }

    public function down(): void
    {
        $this->replacePostgresChecks(includeLessonAudio: false);
    }

    private function replacePostgresChecks(bool $includeLessonAudio): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $assetKinds = $includeLessonAudio
            ? "'model_audio', 'lesson_audio', 'lesson_video'"
            : "'model_audio', 'lesson_video'";
        $audioKinds = $includeLessonAudio
            ? "'model_audio', 'lesson_audio'"
            : "'model_audio'";

        DB::transaction(function () use ($assetKinds, $audioKinds): void {
            DB::statement(<<<'SQL'
ALTER TABLE media_uploads
DROP CONSTRAINT media_uploads_values_check
SQL);
            DB::statement(<<<SQL
ALTER TABLE media_uploads
ADD CONSTRAINT media_uploads_values_check
CHECK (
    asset_kind IN ({$assetKinds})
    AND state IN ('uploading', 'finalized', 'scan_clean', 'accepted', 'rejected', 'expired', 'inspection_failed', 'scan_failed', 'promotion_failed')
    AND version > 0
    AND total_bytes > 0
    AND whole_object_hash ~ '^[0-9a-f]{64}$'
    AND part_size_bytes > 0
    AND expected_part_count > 0
    AND received_part_count <= expected_part_count
    AND accepted_object_key <> ''
    AND expires_at > created_at
    AND (decision_reason_key IS NULL OR decision_reason_key ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND ((state = 'accepted') = (accepted_asset_id IS NOT NULL))
    AND (
        ((decided_at IS NULL) = (decided_by_membership_id IS NULL))
        OR (state = 'expired' AND decided_at IS NOT NULL AND decided_by_membership_id IS NULL)
    )
)
SQL);

            DB::statement(<<<'SQL'
ALTER TABLE media_security_scans
DROP CONSTRAINT media_security_scans_values_check
SQL);
            DB::statement(<<<SQL
ALTER TABLE media_security_scans
ADD CONSTRAINT media_security_scans_values_check
CHECK (
    ((media_upload_id IS NOT NULL)::int + (media_asset_id IS NOT NULL)::int) = 1
    AND asset_kind IN ({$assetKinds})
    AND object_hash ~ '^[0-9a-f]{64}$'
    AND byte_size > 0
    AND inspection_outcome IN ('passed', 'failed', 'unavailable')
    AND malware_outcome IN ('clean', 'infected', 'error', 'unavailable')
    AND technical_metadata_hash ~ '^[0-9a-f]{64}$'
    AND inspected_at <= scanned_at
    AND scanned_at = created_at
)
SQL);

            DB::statement(<<<'SQL'
ALTER TABLE media_asset_lifecycles
DROP CONSTRAINT media_asset_lifecycles_values_check
SQL);
            DB::statement(<<<SQL
ALTER TABLE media_asset_lifecycles
ADD CONSTRAINT media_asset_lifecycles_values_check
CHECK (
    asset_kind IN ({$assetKinds})
    AND state IN ('active', 'retired')
    AND version > 0
    AND ((state = 'retired') = (retired_at IS NOT NULL))
    AND ((retired_at IS NULL) = (retired_by_membership_id IS NULL))
    AND (retirement_reason_key IS NULL OR retirement_reason_key ~ '^[a-z][a-z0-9_.-]{0,99}$')
    AND predecessor_media_asset_id IS DISTINCT FROM media_asset_id
)
SQL);

            DB::statement(<<<'SQL'
ALTER TABLE media_asset_metadata_revisions
DROP CONSTRAINT media_metadata_revisions_values_check
SQL);
            DB::statement(<<<SQL
ALTER TABLE media_asset_metadata_revisions
ADD CONSTRAINT media_metadata_revisions_values_check
CHECK (
    asset_kind IN ({$assetKinds})
    AND revision_number > 0
    AND content_hash ~ '^[0-9a-f]{64}$'
    AND operator_label <> ''
    AND transcript <> ''
    AND ((asset_kind IN ({$audioKinds}) AND text_alternative IS NULL) OR (asset_kind = 'lesson_video' AND text_alternative IS NOT NULL AND text_alternative <> ''))
    AND ((revision_number = 1 AND parent_revision_id IS NULL) OR (revision_number > 1 AND parent_revision_id IS NOT NULL))
    AND authored_at = created_at
)
SQL);

            DB::statement(<<<'SQL'
ALTER TABLE media_asset_metadata_variants
DROP CONSTRAINT media_metadata_variants_values_check
SQL);
            DB::statement(<<<SQL
ALTER TABLE media_asset_metadata_variants
ADD CONSTRAINT media_metadata_variants_values_check
CHECK (
    asset_kind IN ({$assetKinds})
    AND locale = 'kn-IN'
    AND revision_number > 0
    AND source_content_hash ~ '^[0-9a-f]{64}$'
    AND content_hash ~ '^[0-9a-f]{64}$'
    AND transcript <> ''
    AND ((asset_kind IN ({$audioKinds}) AND text_alternative IS NULL) OR (asset_kind = 'lesson_video' AND text_alternative IS NOT NULL AND text_alternative <> ''))
    AND ((revision_number = 1 AND parent_variant_id IS NULL) OR (revision_number > 1 AND parent_variant_id IS NOT NULL))
    AND translated_at = created_at
)
SQL);

            DB::statement(<<<'SQL'
ALTER TABLE media_attachment_events
DROP CONSTRAINT media_attachment_events_values_check
SQL);
            DB::statement(<<<SQL
ALTER TABLE media_attachment_events
ADD CONSTRAINT media_attachment_events_values_check
CHECK (
    usage_key IN ({$assetKinds})
    AND previous_workspace_version > 0
    AND successor_workspace_version = previous_workspace_version + 1
    AND attached_at = created_at
)
SQL);

            DB::statement(<<<'SQL'
ALTER TABLE publication_media_governance_components
DROP CONSTRAINT publication_media_governance_values_check
SQL);
            DB::statement(<<<SQL
ALTER TABLE publication_media_governance_components
ADD CONSTRAINT publication_media_governance_values_check
CHECK (
    usage_key IN ({$assetKinds})
    AND object_hash ~ '^[0-9a-f]{64}$'
    AND metadata_content_hash ~ '^[0-9a-f]{64}$'
    AND variant_content_hash ~ '^[0-9a-f]{64}$'
    AND rights_evidence_status = 'approved'
    AND rights_evidence_hash ~ '^[0-9a-f]{64}$'
)
SQL);
        });
    }
};
