<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_attempts', function (Blueprint $table): void {
            $table->index(
                ['organization_id', 'status', 'updated_at', 'id'],
                'learner_attempts_support_diagnostics_index',
            );
        });

        Schema::table('voice_recordings', function (Blueprint $table): void {
            $table->index(
                ['organization_id', 'status', 'updated_at', 'id'],
                'voice_recordings_support_diagnostics_index',
            );
        });

        Schema::table(
            'voice_deletion_requests',
            function (Blueprint $table): void {
                /*
                 * Status is deliberately last: the diagnostics query orders
                 * all open statuses as one timeline, so the leading tenant
                 * and timestamp columns remove its explicit sort.
                 */
                $table->index(
                    ['organization_id', 'requested_at', 'id', 'status'],
                    'voice_deletions_support_diagnostics_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::table('voice_deletion_requests', function (Blueprint $table): void {
            $table->dropIndex('voice_deletions_support_diagnostics_index');
        });

        Schema::table('voice_recordings', function (Blueprint $table): void {
            $table->dropIndex('voice_recordings_support_diagnostics_index');
        });

        Schema::table('learner_attempts', function (Blueprint $table): void {
            $table->dropIndex('learner_attempts_support_diagnostics_index');
        });
    }
};
