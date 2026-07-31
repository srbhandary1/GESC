<?php

declare(strict_types=1);

use App\Conversation\Voice\VoiceRetentionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('voice:purge-expired', function (): int {
    $result = app(
        VoiceRetentionService::class,
    )->purgeDue();
    $this->info(json_encode($result, JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Delete due transient voice audio and transcripts');

Schedule::command('voice:purge-expired')
    ->everyFiveMinutes()
    ->withoutOverlapping();
