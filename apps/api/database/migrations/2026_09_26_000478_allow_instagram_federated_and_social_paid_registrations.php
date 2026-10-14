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
        Schema::table('paid_registration_intents', function (Blueprint $table): void {
            $table->string('federated_provider', 16)->nullable();
            $table->text('federated_subject_ciphertext')->nullable();
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE federated_identities DROP CONSTRAINT IF EXISTS federated_identities_provider_check');
        DB::statement(<<<'SQL'
ALTER TABLE federated_identities ADD CONSTRAINT federated_identities_provider_check CHECK (
    provider IN ('google', 'facebook', 'instagram')
)
SQL);
    }

    public function down(): void
    {
        Schema::table('paid_registration_intents', function (Blueprint $table): void {
            $table->dropColumn(['federated_provider', 'federated_subject_ciphertext']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE federated_identities DROP CONSTRAINT IF EXISTS federated_identities_provider_check');
        DB::statement(<<<'SQL'
ALTER TABLE federated_identities ADD CONSTRAINT federated_identities_provider_check CHECK (
    provider IN ('google', 'facebook')
)
SQL);
    }
};
