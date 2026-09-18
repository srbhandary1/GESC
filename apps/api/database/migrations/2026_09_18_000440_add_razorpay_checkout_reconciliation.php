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
        Schema::table('subscription_checkouts', function (Blueprint $table): void {
            $table->string('provider_payment_reference', 64)->nullable()->after('provider_reference');
            $table->json('plan_snapshot')->nullable()->after('plan_id');
            $table->timestampTz('completed_at')->nullable()->after('entitlement_id');
            $table->timestampTz('failed_at')->nullable()->after('completed_at');
            $table->index(['provider', 'provider_reference']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('provider', 32);
            $table->string('provider_event_id', 128);
            $table->string('event_type', 64);
            $table->string('provider_order_reference', 64)->nullable();
            $table->string('provider_payment_reference', 64)->nullable();
            $table->char('payload_digest', 64);
            $table->string('status', 24);
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'provider_event_id']);
            $table->index(['provider', 'provider_order_reference']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
ALTER TABLE payment_webhook_events ADD CONSTRAINT payment_webhook_events_status_check CHECK (
    status IN ('received', 'processed', 'ignored', 'failed')
)
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::table('subscription_checkouts', function (Blueprint $table): void {
            $table->dropIndex(['provider', 'provider_reference']);
            $table->dropColumn([
                'provider_payment_reference',
                'plan_snapshot',
                'completed_at',
                'failed_at',
            ]);
        });
    }
};
