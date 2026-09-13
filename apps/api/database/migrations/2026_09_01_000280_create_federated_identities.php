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
        Schema::create('federated_identities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('provider', 16);
            $table->char('subject_digest', 64);
            $table->timestampTz('linked_at');
            $table->timestampsTz();

            $table->unique(['provider', 'subject_digest']);
            $table->unique(['user_id', 'provider']);
            $table->index(['user_id', 'linked_at']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
ALTER TABLE federated_identities ADD CONSTRAINT federated_identities_provider_check CHECK (
    provider IN ('google', 'facebook')
)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('federated_identities');
    }
};
