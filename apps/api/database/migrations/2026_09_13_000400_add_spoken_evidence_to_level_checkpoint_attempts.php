<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('level_checkpoint_attempts', function (Blueprint $table): void {
            $table->json('spoken_evidence_json')->nullable()->after('item_results_json');
        });
    }

    public function down(): void
    {
        Schema::table('level_checkpoint_attempts', function (Blueprint $table): void {
            $table->dropColumn('spoken_evidence_json');
        });
    }
};
