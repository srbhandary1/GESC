<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string DELIVERY_INDEX = 'outbox_events_delivery_state_idx';

    private const string DEAD_LETTER_CONSTRAINT =
        'outbox_events_dead_letter_state_check';

    public function up(): void
    {
        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->timestampTz('dead_lettered_at')
                ->nullable()
                ->after('published_at');
            $table->string('dead_letter_reason_code', 160)
                ->nullable()
                ->after('last_error_code');
            $table->index(
                [
                    'event_type',
                    'published_at',
                    'dead_lettered_at',
                    'available_at',
                ],
                self::DELIVERY_INDEX,
            );
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(sprintf(
                'ALTER TABLE outbox_events ADD CONSTRAINT %s CHECK ((dead_lettered_at IS NULL AND dead_letter_reason_code IS NULL) OR (dead_lettered_at IS NOT NULL AND dead_letter_reason_code IS NOT NULL AND published_at IS NULL))',
                self::DEAD_LETTER_CONSTRAINT,
            ));
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(sprintf(
                'ALTER TABLE outbox_events DROP CONSTRAINT IF EXISTS %s',
                self::DEAD_LETTER_CONSTRAINT,
            ));
        }

        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->dropIndex(self::DELIVERY_INDEX);
            $table->dropColumn([
                'dead_lettered_at',
                'dead_letter_reason_code',
            ]);
        });
    }
};
