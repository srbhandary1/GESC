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
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('scope_kind', 24);
            $table->foreignUlid('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->foreignUlid('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->ulid('actor_membership_id')->nullable();
            $table->foreignUlid('target_organization_id')
                ->nullable()
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('capability_key', 120)->nullable();
            $table->string('reason_key', 120)->nullable();
            $table->string('action', 120);
            $table->string('subject_type', 120);
            $table->ulid('subject_id');
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('occurred_at');

            $table->foreign(
                [
                    'organization_id',
                    'actor_user_id',
                    'actor_membership_id',
                ],
                'audit_events_actor_membership_identity_fk',
            )->references(['organization_id', 'user_id', 'id'])
                ->on('memberships')
                ->restrictOnDelete();
            $table->index(['scope_kind', 'organization_id', 'occurred_at']);
            $table->index([
                'scope_kind',
                'target_organization_id',
                'occurred_at',
            ]);
            $table->index(['organization_id', 'subject_type', 'subject_id']);
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('scope_kind', 24);
            $table->foreignUlid('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->string('event_type', 160);
            $table->string('aggregate_type', 120);
            $table->ulid('aggregate_id');
            $table->jsonb('payload');
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('available_at');
            $table->timestampTz('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error_code')->nullable();

            $table->index(['scope_kind', 'organization_id', 'available_at']);
            $table->index(['published_at', 'available_at']);
            $table->index(['organization_id', 'aggregate_type', 'aggregate_id']);
        });

        Schema::create('processed_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('outbox_event_id')
                ->constrained('outbox_events')
                ->restrictOnDelete();
            $table->string('consumer_key', 120);
            $table->timestampTz('processed_at');

            $table->unique(['consumer_key', 'outbox_event_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE audit_events ADD CONSTRAINT audit_events_scope_check CHECK ((scope_kind = 'platform' AND organization_id IS NULL AND actor_membership_id IS NULL AND capability_key IS NOT NULL AND reason_key IS NOT NULL) OR (scope_kind = 'organization' AND organization_id IS NOT NULL AND target_organization_id IS NULL AND capability_key IS NULL AND reason_key IS NULL))",
            );
            DB::statement(
                "ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_scope_check CHECK ((scope_kind = 'platform' AND organization_id IS NULL) OR (scope_kind = 'organization' AND organization_id IS NOT NULL))",
            );
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION global_english_reject_audit_mutation()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_events is append-only';
                END;
                $$ LANGUAGE plpgsql
                SQL);
            DB::statement(
                'CREATE TRIGGER audit_events_append_only BEFORE UPDATE OR DELETE ON audit_events FOR EACH ROW EXECUTE FUNCTION global_english_reject_audit_mutation()',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('audit_events');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'DROP FUNCTION IF EXISTS global_english_reject_audit_mutation()',
            );
        }
    }
};
