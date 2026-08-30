<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_practice_blueprints', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('program_id');
            $table->ulid('curriculum_version_id');
            $table->unsignedInteger('revision_number');
            $table->string('concept_key', 120);
            $table->string('state', 24)->default('draft');
            $table->json('canonical_json');
            $table->char('content_hash', 64);
            $table->timestampsTz();
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'program_id', 'concept_key', 'revision_number'], 'lesson_practice_blueprints_revision_unique');
            $table->index(['organization_id', 'program_id', 'state']);
        });

        Schema::create('lesson_practice_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('blueprint_id');
            $table->string('review_kind', 24);
            $table->char('blueprint_content_hash', 64);
            $table->string('decision', 24);
            $table->ulid('reviewer_membership_id');
            $table->timestampsTz();
            $table->unique(['organization_id', 'blueprint_id', 'review_kind', 'blueprint_content_hash'], 'lesson_practice_reviews_exact_unique');
            $table->index(['organization_id', 'blueprint_id', 'review_kind', 'decision']);
        });

        Schema::create('lesson_practice_media', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('blueprint_id');
            $table->unsignedSmallInteger('part_order');
            $table->string('part_id', 32);
            $table->ulid('media_asset_id');
            $table->ulid('metadata_revision_id');
            $table->char('metadata_content_hash', 64);
            $table->ulid('media_rights_revision_id');
            $table->timestampsTz();
            $table->unique(['organization_id', 'blueprint_id', 'part_order'], 'lesson_practice_media_order_unique');
            $table->unique(['organization_id', 'blueprint_id', 'part_id'], 'lesson_practice_media_part_unique');
            $table->index(['organization_id', 'media_asset_id']);
        });

        Schema::create('lesson_practice_attachment_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('blueprint_id');
            $table->ulid('actor_membership_id');
            $table->string('idempotency_key', 120);
            $table->char('request_hash', 64);
            $table->timestampsTz();
            $table->unique(['organization_id', 'idempotency_key'], 'lesson_practice_attachment_idempotency_unique');
            $table->index(['organization_id', 'blueprint_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_practice_attachment_events');
        Schema::dropIfExists('lesson_practice_media');
        Schema::dropIfExists('lesson_practice_reviews');
        Schema::dropIfExists('lesson_practice_blueprints');
    }
};
