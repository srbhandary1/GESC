<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_export_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('target_membership_id');
            $table->ulid('requested_by_membership_id');
            $table->string('idempotency_key', 36);
            $table->string('status', 24);
            $table->jsonb('summary_json');
            $table->timestampTz('requested_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'idempotency_key'],
                'privacy_export_idempotency_unique',
            );
            $table->index(
                ['organization_id', 'target_membership_id'],
                'privacy_export_target_index',
            );
            $table->foreign(
                ['organization_id', 'target_membership_id'],
                'privacy_export_target_membership_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'requested_by_membership_id'],
                'privacy_export_actor_membership_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('privacy_erasure_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('target_membership_id');
            $table->ulid('requested_by_membership_id');
            $table->string('idempotency_key', 36);
            $table->string('status', 24);
            $table->unsignedInteger('voice_recording_count');
            $table->unsignedInteger('voice_deletion_completed_count');
            $table->timestampTz('requested_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'id']);
            $table->unique(
                ['organization_id', 'idempotency_key'],
                'privacy_erasure_idempotency_unique',
            );
            $table->index(
                ['organization_id', 'target_membership_id'],
                'privacy_erasure_target_index',
            );
            $table->foreign(
                ['organization_id', 'target_membership_id'],
                'privacy_erasure_target_membership_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'requested_by_membership_id'],
                'privacy_erasure_actor_membership_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE privacy_export_requests ADD CONSTRAINT privacy_export_status_check CHECK (status IN ('pending', 'completed', 'failed'))",
            );
            DB::statement(
                "ALTER TABLE privacy_erasure_requests ADD CONSTRAINT privacy_erasure_status_check CHECK (status IN ('pending', 'completed', 'partially_completed', 'restricted'))",
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_erasure_requests');
        Schema::dropIfExists('privacy_export_requests');
    }
};
