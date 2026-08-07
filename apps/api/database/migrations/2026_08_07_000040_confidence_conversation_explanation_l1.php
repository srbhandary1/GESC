<?php

declare(strict_types=1);

use App\Conversation\Support\ConversationUiLocales;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_sessions', function (Blueprint $table): void {
            $table->string('ui_locale', 16)
                ->default('kn-IN')
                ->after('coaching_mode');
        });

        Schema::table('correction_suggestions', function (Blueprint $table): void {
            $table->string('explanation_l1_digest', 64)
                ->default(str_repeat('0', 64))
                ->after('explanation_kn_digest');
            $table->string('explanation_locale', 16)
                ->default('kn-IN')
                ->after('explanation_l1_digest');
        });

        DB::table('correction_suggestions')->update([
            'explanation_l1_digest' => DB::raw('explanation_kn_digest'),
            'explanation_locale' => 'kn-IN',
        ]);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $locales = "'".implode("','", ConversationUiLocales::all())."'";

            DB::statement(
                'ALTER TABLE conversation_sessions DROP CONSTRAINT IF EXISTS conversation_sessions_values_check',
            );
            DB::statement(
                "ALTER TABLE conversation_sessions ADD CONSTRAINT conversation_sessions_values_check CHECK (persona_definition_hash ~ '^[0-9a-f]{64}$' AND scenario_definition_hash ~ '^[0-9a-f]{64}$' AND mode = 'friend' AND coaching_mode IN ('gentle', 'tutor', 'fluency') AND ui_locale IN ({$locales}) AND status = 'active' AND ended_at IS NULL)",
            );

            DB::statement(
                'ALTER TABLE correction_suggestions DROP CONSTRAINT IF EXISTS correction_suggestions_values_check',
            );
            DB::statement(
                "ALTER TABLE correction_suggestions ADD CONSTRAINT correction_suggestions_values_check CHECK (coaching_mode IN ('gentle', 'tutor', 'fluency') AND error_category ~ '^[a-z0-9][a-z0-9._-]{0,63}$' AND correction_digest ~ '^[0-9a-f]{64}$' AND explanation_kn_digest ~ '^[0-9a-f]{64}$' AND explanation_l1_digest ~ '^[0-9a-f]{64}$' AND explanation_locale IN ({$locales}) AND safety_state IN ('ok', 'fallback') AND prompt_version_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')",
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE conversation_sessions DROP CONSTRAINT IF EXISTS conversation_sessions_values_check',
            );
            DB::statement(
                "ALTER TABLE conversation_sessions ADD CONSTRAINT conversation_sessions_values_check CHECK (persona_definition_hash ~ '^[0-9a-f]{64}$' AND scenario_definition_hash ~ '^[0-9a-f]{64}$' AND mode = 'friend' AND coaching_mode IN ('gentle', 'tutor', 'fluency') AND status = 'active' AND ended_at IS NULL)",
            );

            DB::statement(
                'ALTER TABLE correction_suggestions DROP CONSTRAINT IF EXISTS correction_suggestions_values_check',
            );
            DB::statement(
                "ALTER TABLE correction_suggestions ADD CONSTRAINT correction_suggestions_values_check CHECK (coaching_mode IN ('gentle', 'tutor', 'fluency') AND error_category ~ '^[a-z0-9][a-z0-9._-]{0,63}$' AND correction_digest ~ '^[0-9a-f]{64}$' AND explanation_kn_digest ~ '^[0-9a-f]{64}$' AND safety_state IN ('ok', 'fallback') AND prompt_version_id ~ '^[0-9A-HJKMNP-TV-Z]{26}$')",
            );
        }

        Schema::table('correction_suggestions', function (Blueprint $table): void {
            $table->dropColumn(['explanation_l1_digest', 'explanation_locale']);
        });

        Schema::table('conversation_sessions', function (Blueprint $table): void {
            $table->dropColumn('ui_locale');
        });
    }
};
