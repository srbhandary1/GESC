<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_erasure_voice_deletions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();
            $table->ulid('privacy_erasure_request_id');
            $table->ulid('voice_deletion_request_id');
            $table->timestampTz('created_at');

            $table->unique(
                [
                    'organization_id',
                    'privacy_erasure_request_id',
                    'voice_deletion_request_id',
                ],
                'privacy_erasure_voice_deletion_unique',
            );
            $table->index(
                ['organization_id', 'voice_deletion_request_id'],
                'privacy_erasure_voice_deletion_request_index',
            );
            $table->foreign(
                ['organization_id', 'privacy_erasure_request_id'],
                'privacy_erasure_voice_privacy_fk',
            )->references(['organization_id', 'id'])
                ->on('privacy_erasure_requests')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'voice_deletion_request_id'],
                'privacy_erasure_voice_deletion_fk',
            )->references(['organization_id', 'id'])
                ->on('voice_deletion_requests')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_erasure_voice_deletions');
    }
};
