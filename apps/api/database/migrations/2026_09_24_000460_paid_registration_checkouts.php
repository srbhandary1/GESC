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
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subscription_checkouts ALTER COLUMN learner_membership_id DROP NOT NULL');
        } else {
            Schema::table('subscription_checkouts', function (Blueprint $table): void {
                $table->dropForeign(['learner_membership_id']);
            });
            Schema::table('subscription_checkouts', function (Blueprint $table): void {
                $table->foreignUlid('learner_membership_id')
                    ->nullable()
                    ->change();
            });
            Schema::table('subscription_checkouts', function (Blueprint $table): void {
                $table->foreign('learner_membership_id')
                    ->references('id')
                    ->on('memberships')
                    ->restrictOnDelete();
            });
        }

        Schema::create('paid_registration_intents', function (Blueprint $table): void {
            $table->ulid('checkout_id')->primary();
            $table->string('phone', 32);
            $table->string('password_hash', 255)->nullable();
            $table->boolean('existing_user');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('checkout_id')
                ->references('id')
                ->on('subscription_checkouts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_registration_intents');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subscription_checkouts ALTER COLUMN learner_membership_id SET NOT NULL');
        }
    }
};
