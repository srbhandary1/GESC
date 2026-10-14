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
        Schema::create('conversation_playback_meters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->ulid('learner_membership_id');
            $table->string('playback_key', 64);
            $table->char('grant_digest', 64);
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('played_ms')->default(0);
            $table->unsignedInteger('charged_seconds')->default(0);
            $table->timestampTz('started_at');
            $table->timestampTz('expires_at');
            $table->unique(['organization_id', 'learner_membership_id', 'playback_key'], 'playback_meter_key');
            $table->foreign(['organization_id', 'learner_membership_id'], 'playback_meter_membership')->references(['organization_id', 'id'])->on('memberships')->restrictOnDelete();
            $table->index('expires_at');
        });
        Schema::table('ai_cost_ledger', function (Blueprint $table): void {
            $table->string('input_unit_kind', 40)->default('unknown');
            $table->string('output_unit_kind', 40)->default('unknown');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE conversation_playback_meters ADD CONSTRAINT playback_meter_bounds CHECK (duration_ms BETWEEN 1 AND 120000 AND played_ms BETWEEN 0 AND duration_ms AND charged_seconds BETWEEN 0 AND 120 AND expires_at > started_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_playback_meters');
        Schema::table('ai_cost_ledger', function (Blueprint $table): void {
            $table->dropColumn(['input_unit_kind', 'output_unit_kind']);
        });
    }
};
