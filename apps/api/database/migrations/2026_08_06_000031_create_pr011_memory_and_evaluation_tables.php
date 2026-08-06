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
        Schema::create('learner_memory_facts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('learner_membership_id');
            $table->string('purpose', 64);
            $table->string('source', 64);
            $table->string('status', 24);
            $table->string('fact_text', 200);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
            $table->timestampTz('deleted_at')->nullable();
            $table->unique(['organization_id', 'id']);
            $table->index(
                ['organization_id', 'learner_membership_id', 'status'],
                'learner_memory_facts_learner_status_index',
            );
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'learner_membership_id'],
                'learner_memory_facts_membership_fk',
            )->references(['organization_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
        });

        Schema::create('memory_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('learner_membership_id');
            $table->ulid('memory_fact_id');
            $table->ulid('actor_user_id');
            $table->string('event_code', 32);
            $table->string('before_digest', 64)->nullable();
            $table->string('after_digest', 64)->nullable();
            $table->timestampTz('recorded_at');
            $table->index(
                ['organization_id', 'learner_membership_id', 'recorded_at'],
                'memory_events_learner_index',
            );
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'memory_fact_id'],
                'memory_events_fact_fk',
            )->references(['organization_id', 'id'])
                ->on('learner_memory_facts')
                ->restrictOnDelete();
        });

        Schema::create('evaluation_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('suite_key', 100);
            $table->string('suite_version', 32);
            $table->string('suite_hash', 64);
            $table->string('status', 32);
            $table->unsignedInteger('case_count');
            $table->unsignedInteger('passed_count');
            $table->unsignedInteger('failed_count');
            $table->unsignedBigInteger('estimated_cost_microunits');
            $table->string('report_digest', 64);
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE learner_memory_facts ADD CONSTRAINT learner_memory_facts_values_check CHECK (purpose = 'conversation_context' AND source = 'learner_explicit' AND status IN ('active', 'deleted') AND char_length(fact_text) BETWEEN 1 AND 200 AND ((status = 'active' AND deleted_at IS NULL) OR (status = 'deleted' AND deleted_at IS NOT NULL)))",
            );
            DB::statement(
                "ALTER TABLE memory_events ADD CONSTRAINT memory_events_values_check CHECK (event_code IN ('created', 'updated', 'deleted') AND (before_digest IS NULL OR before_digest ~ '^[0-9a-f]{64}$') AND (after_digest IS NULL OR after_digest ~ '^[0-9a-f]{64}$'))",
            );
            DB::statement(
                "ALTER TABLE evaluation_runs ADD CONSTRAINT evaluation_runs_values_check CHECK (suite_key ~ '^[a-z0-9][a-z0-9._-]{0,99}$' AND suite_hash ~ '^[0-9a-f]{64}$' AND status IN ('completed', 'failed') AND case_count >= 0 AND passed_count >= 0 AND failed_count >= 0 AND estimated_cost_microunits = 0 AND report_digest ~ '^[0-9a-f]{64}$')",
            );
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION memory_events_reject_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'Memory events are immutable'
        USING ERRCODE = '55000';
END;
$$;

CREATE TRIGGER memory_events_immutable
BEFORE UPDATE OR DELETE ON memory_events
FOR EACH ROW
EXECUTE FUNCTION memory_events_reject_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS memory_events_immutable ON memory_events');
            DB::unprepared('DROP FUNCTION IF EXISTS memory_events_reject_mutation()');
        }

        Schema::dropIfExists('evaluation_runs');
        Schema::dropIfExists('memory_events');
        Schema::dropIfExists('learner_memory_facts');
    }
};
