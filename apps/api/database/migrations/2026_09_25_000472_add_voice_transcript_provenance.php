<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_recordings', function (Blueprint $table): void {
            $table->string('transcript_source', 24)->default('translated_speech');
            $table->string('transcript_locale', 16)->default('unknown');
            $table->char('confirmed_transcript_sha256', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('voice_recordings', fn (Blueprint $table) => $table->dropColumn(['transcript_source', 'transcript_locale', 'confirmed_transcript_sha256']));
    }
};
