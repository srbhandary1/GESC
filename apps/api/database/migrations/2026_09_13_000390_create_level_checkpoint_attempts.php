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
        Schema::create('level_checkpoint_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUlid('membership_id')
                ->constrained('memberships')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('level_number');
            $table->ulid('blueprint_id')->nullable();
            $table->char('blueprint_content_hash', 64);
            $table->string('rule_key', 64);
            $table->char('rule_hash', 64);
            $table->unsignedSmallInteger('attempt_number');
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('pass_threshold');
            $table->boolean('passed');
            $table->json('item_results_json');
            $table->string('idempotency_key', 120);
            $table->timestampTz('created_at');
            $table->unique(
                ['organization_id', 'user_id', 'idempotency_key'],
                'level_checkpoint_attempts_idempotency_unique',
            );
            $table->index(
                ['organization_id', 'user_id', 'level_number'],
                'level_checkpoint_attempts_lookup_idx',
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE level_checkpoint_attempts ADD CONSTRAINT level_checkpoint_attempts_values_check CHECK (level_number BETWEEN 1 AND 17 AND attempt_number BETWEEN 1 AND 20 AND score BETWEEN 0 AND 100)',
            );
            DB::unprepared(<<<'SQL'
CREATE TRIGGER level_checkpoint_attempts_immutable
BEFORE UPDATE OR DELETE ON level_checkpoint_attempts
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS level_checkpoint_attempts_immutable ON level_checkpoint_attempts');
        }

        Schema::dropIfExists('level_checkpoint_attempts');
    }
};
