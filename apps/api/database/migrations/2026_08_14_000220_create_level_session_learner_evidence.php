<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_session_completions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('user_id');
            $table->ulid('membership_id');
            $table->unsignedSmallInteger('level_number');
            $table->string('part_id', 32);
            $table->ulid('blueprint_id')->nullable();
            $table->char('blueprint_content_hash', 64)->nullable();
            $table->string('completion_kind', 32);
            $table->json('result_json')->nullable();
            $table->string('idempotency_key', 120);
            $table->timestampsTz();
            $table->unique(
                ['organization_id', 'user_id', 'idempotency_key'],
                'level_session_completions_idempotency_unique',
            );
            $table->index(
                ['organization_id', 'user_id', 'level_number', 'part_id'],
                'level_session_completions_lookup_idx',
            );
        });

        Schema::create('level_session_check_submissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('user_id');
            $table->ulid('membership_id');
            $table->unsignedSmallInteger('level_number');
            $table->string('part_id', 32);
            $table->ulid('blueprint_id');
            $table->char('blueprint_content_hash', 64);
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('pass_threshold');
            $table->boolean('passed');
            $table->json('item_results_json');
            $table->string('idempotency_key', 120);
            $table->timestampsTz();
            $table->unique(
                ['organization_id', 'user_id', 'idempotency_key'],
                'level_session_check_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_session_check_submissions');
        Schema::dropIfExists('level_session_completions');
    }
};
