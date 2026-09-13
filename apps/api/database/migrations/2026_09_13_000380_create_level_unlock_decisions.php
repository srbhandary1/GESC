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
        Schema::create('level_unlock_decisions', function (Blueprint $table): void {
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
            $table->unsignedTinyInteger('prerequisite_level_number');
            $table->string('rule_key', 64);
            $table->char('rule_hash', 64);
            $table->string('reason_code', 64);
            $table->json('evidence_json');
            $table->string('idempotency_key', 120);
            $table->timestampTz('created_at');
            $table->unique(
                ['organization_id', 'user_id', 'idempotency_key'],
                'level_unlock_decisions_idempotency_unique',
            );
            $table->unique(
                ['organization_id', 'user_id', 'level_number', 'rule_key'],
                'level_unlock_decisions_level_rule_unique',
            );
            $table->index(
                ['organization_id', 'user_id', 'level_number'],
                'level_unlock_decisions_lookup_idx',
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE level_unlock_decisions ADD CONSTRAINT level_unlock_decisions_values_check CHECK (level_number BETWEEN 1 AND 17 AND prerequisite_level_number BETWEEN 1 AND 16 AND level_number = prerequisite_level_number + 1)',
            );
            DB::unprepared(<<<'SQL'
CREATE TRIGGER level_unlock_decisions_immutable
BEFORE UPDATE OR DELETE ON level_unlock_decisions
FOR EACH ROW EXECUTE FUNCTION conversation_reject_immutable_mutation();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS level_unlock_decisions_immutable ON level_unlock_decisions');
        }

        Schema::dropIfExists('level_unlock_decisions');
    }
};
