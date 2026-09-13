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
        Schema::create('conversation_turn_speech_artifacts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('conversation_turn_id');
            $table->ulid('learner_membership_id');
            $table->string('kind', 16);
            $table->string('mime_type', 32);
            $table->unsignedInteger('byte_size');
            $table->char('content_sha256', 64);
            $table->string('storage_path', 255);
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');

            $table->unique(
                ['organization_id', 'conversation_turn_id', 'kind'],
                'conversation_turn_speech_artifacts_kind_unique',
            );
            $table->unique(['organization_id', 'id']);
            $table->index(
                ['organization_id', 'expires_at'],
                'conversation_turn_speech_artifacts_retention_index',
            );
            $table->foreign(
                ['organization_id', 'conversation_turn_id'],
                'conversation_turn_speech_artifacts_turn_fk',
            )->references(['organization_id', 'id'])
                ->on('conversation_turns')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE conversation_turn_speech_artifacts ADD CONSTRAINT conversation_turn_speech_artifacts_values_check CHECK (kind IN ('model_en', 'guidance_l1') AND mime_type IN ('audio/wav') AND byte_size > 0 AND content_sha256 ~ '^[0-9a-f]{64}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_turn_speech_artifacts');
    }
};
