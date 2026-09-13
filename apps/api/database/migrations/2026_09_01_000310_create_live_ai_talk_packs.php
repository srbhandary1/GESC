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
        Schema::create('live_ai_talk_packs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('entitlement_id')->constrained('learner_entitlements')->restrictOnDelete();
            $table->foreignUlid('checkout_id')->nullable()->constrained('subscription_checkouts')->restrictOnDelete();
            $table->string('plan_code', 64);
            $table->unsignedInteger('seconds');
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');

            $table->unique('checkout_id');
            $table->index(['organization_id', 'entitlement_id']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE entitlement_plans DROP CONSTRAINT IF EXISTS entitlement_plans_billing_period_check');
        DB::statement(<<<'SQL'
ALTER TABLE entitlement_plans ADD CONSTRAINT entitlement_plans_billing_period_check CHECK (
    billing_period IN ('hours', 'calendar_month', 'talk_pack')
)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('live_ai_talk_packs');

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE entitlement_plans DROP CONSTRAINT IF EXISTS entitlement_plans_billing_period_check');
        DB::statement(<<<'SQL'
ALTER TABLE entitlement_plans ADD CONSTRAINT entitlement_plans_billing_period_check CHECK (
    billing_period IN ('hours', 'calendar_month')
) NOT VALID
SQL);
    }
};
