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
        Schema::create('organization_tax_settings', function (Blueprint $table): void {
            $table->ulid('organization_id')->primary();
            $table->boolean('gst_registered')->default(true);
            $table->string('gstin', 15);
            $table->unsignedInteger('gst_rate_bps');
            $table->boolean('gstin_is_placeholder')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignUlid('updated_by_membership_id')->nullable()->constrained('memberships')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });

        Schema::create('entitlement_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name', 120);
            $table->unsignedInteger('amount_ex_gst_paise');
            $table->string('billing_period', 32);
            $table->unsignedInteger('duration_value');
            $table->boolean('allows_roleplay');
            $table->unsignedTinyInteger('max_level_number')->nullable();
            $table->unsignedInteger('live_ai_cap_seconds')->nullable();
            $table->boolean('is_demo');
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('learner_entitlements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('learner_membership_id')->constrained('memberships')->restrictOnDelete();
            $table->foreignUlid('plan_id')->constrained('entitlement_plans')->restrictOnDelete();
            $table->json('plan_snapshot');
            $table->string('status', 16);
            $table->string('source', 32);
            $table->timestampTz('starts_at');
            $table->timestampTz('first_sign_in_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->unsignedInteger('live_ai_seconds_used')->default(0);
            $table->timestampsTz();

            $table->index(['organization_id', 'learner_membership_id', 'status']);
        });

        Schema::create('subscription_checkouts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('learner_membership_id')->constrained('memberships')->restrictOnDelete();
            $table->foreignUlid('plan_id')->constrained('entitlement_plans')->restrictOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('amount_ex_gst_paise');
            $table->unsignedInteger('gst_paise');
            $table->unsignedInteger('gst_rate_bps');
            $table->string('gstin', 15);
            $table->boolean('gstin_is_placeholder');
            $table->string('provider', 32);
            $table->string('provider_reference', 64);
            $table->string('idempotency_key', 64);
            $table->foreignUlid('entitlement_id')->nullable()->constrained('learner_entitlements')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'idempotency_key']);
        });

        Schema::create('entitlement_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('entitlement_id')->constrained('learner_entitlements')->restrictOnDelete();
            $table->string('event_type', 64);
            $table->foreignUlid('actor_membership_id')->nullable()->constrained('memberships')->restrictOnDelete();
            $table->json('payload');
            $table->timestampTz('occurred_at');
        });

        Schema::create('live_ai_meter_charges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUlid('entitlement_id')->constrained('learner_entitlements')->restrictOnDelete();
            $table->string('charge_key', 64);
            $table->unsignedInteger('seconds');
            $table->timestampTz('charged_at');

            $table->unique(['entitlement_id', 'charge_key']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
ALTER TABLE entitlement_plans ADD CONSTRAINT entitlement_plans_billing_period_check CHECK (
    billing_period IN ('hours', 'calendar_month')
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE entitlement_plans ADD CONSTRAINT entitlement_plans_status_check CHECK (
    status IN ('active', 'retired')
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE learner_entitlements ADD CONSTRAINT learner_entitlements_status_check CHECK (
    status IN ('active', 'expired', 'revoked')
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE learner_entitlements ADD CONSTRAINT learner_entitlements_source_check CHECK (
    source IN ('auto_demo', 'operator_grant', 'checkout')
)
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE subscription_checkouts ADD CONSTRAINT subscription_checkouts_status_check CHECK (
    status IN ('pending', 'completed', 'failed')
)
SQL);
        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX learner_entitlements_one_active
ON learner_entitlements (organization_id, learner_membership_id)
WHERE status = 'active'
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('live_ai_meter_charges');
        Schema::dropIfExists('entitlement_events');
        Schema::dropIfExists('subscription_checkouts');
        Schema::dropIfExists('learner_entitlements');
        Schema::dropIfExists('entitlement_plans');
        Schema::dropIfExists('organization_tax_settings');
    }
};
