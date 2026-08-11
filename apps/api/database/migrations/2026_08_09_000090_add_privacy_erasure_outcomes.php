<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('privacy_erasure_requests', function (Blueprint $table): void {
            $table->jsonb('outcome_json')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('privacy_erasure_requests', function (Blueprint $table): void {
            $table->dropColumn('outcome_json');
        });
    }
};
