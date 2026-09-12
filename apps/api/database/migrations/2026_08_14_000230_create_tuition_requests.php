<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->ulid('user_id');
            $table->ulid('membership_id');
            $table->string('preferred_window', 120);
            $table->string('topic', 200);
            $table->string('notes', 500)->nullable();
            $table->string('status', 24);
            $table->string('idempotency_key', 120);
            $table->char('request_hash', 64);
            $table->string('acknowledge_idempotency_key', 120)->nullable();
            $table->ulid('acknowledged_by_membership_id')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampsTz();
            $table->unique(
                ['organization_id', 'user_id', 'idempotency_key'],
                'tuition_requests_idempotency_unique',
            );
            $table->index(
                ['organization_id', 'created_at'],
                'tuition_requests_org_created_idx',
            );
            $table->index(
                ['organization_id', 'membership_id'],
                'tuition_requests_org_membership_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_requests');
    }
};
