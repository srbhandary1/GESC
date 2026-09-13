<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_lesson_drills', function (Blueprint $table): void {
            $table->json('fallback_turns')->nullable()->after('elicitation_goals');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_lesson_drills', function (Blueprint $table): void {
            $table->dropColumn('fallback_turns');
        });
    }
};
