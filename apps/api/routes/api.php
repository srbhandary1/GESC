<?php

declare(strict_types=1);

use App\Http\Controllers\AuthenticationSessionController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CurrentIdentityController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InstitutionInviteController;
use App\Http\Controllers\LearnerAttemptController;
use App\Http\Controllers\LearnerHomeController;
use App\Http\Controllers\LearnerLevelSessionController;
use App\Http\Controllers\LearnerMediaController;
use App\Http\Controllers\LearnerMemoryController;
use App\Http\Controllers\LearnerMissionController;
use App\Http\Controllers\LearnerOfflineController;
use App\Http\Controllers\LearnerOnboardingController;
use App\Http\Controllers\MobileAuthenticationController;
use App\Http\Controllers\OperatorContentController;
use App\Http\Controllers\OperatorLessonPracticeController;
use App\Http\Controllers\OtpAuthenticationController;
use App\Http\Controllers\VoiceController;
use App\Http\Middleware\RequireStatefulBrowserRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health/live', [HealthController::class, 'live'])
        ->name('api.v1.health.live');
    Route::get('/health/ready', [HealthController::class, 'ready'])
        ->name('api.v1.health.ready');

    Route::post('/auth/otp/requests', [OtpAuthenticationController::class, 'requestChallenge'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:otp-requests'])
        ->name('api.v1.auth.otp.requests.store');
    Route::post('/auth/otp/verifications', [OtpAuthenticationController::class, 'verifyChallenge'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:otp-verifications'])
        ->name('api.v1.auth.otp.verifications.store');

    Route::middleware(['auth:web', 'auth.session'])->group(function (): void {
        Route::post('/auth/logout', [AuthenticationSessionController::class, 'logout'])
            ->name('api.v1.auth.logout');
        Route::get('/auth/sessions', [AuthenticationSessionController::class, 'index'])
            ->name('api.v1.auth.sessions.index');
        Route::delete('/auth/sessions', [AuthenticationSessionController::class, 'destroyAll'])
            ->name('api.v1.auth.sessions.destroy-all');
        Route::delete('/auth/sessions/{sessionId}', [AuthenticationSessionController::class, 'destroy'])
            ->whereUlid('sessionId')
            ->name('api.v1.auth.sessions.destroy');
    });

    Route::post('/auth/mobile/revoke', [MobileAuthenticationController::class, 'revoke'])
        ->middleware(['auth:api', 'scope:mobile:api'])
        ->name('api.v1.auth.mobile.revoke');

    Route::middleware('auth.first-party')->group(function (): void {
        Route::get('/me', [CurrentIdentityController::class, 'show'])
            ->name('api.v1.me.show');
        Route::put('/me/organization-context', [CurrentIdentityController::class, 'select'])
            ->name('api.v1.me.organization-context.update');
        Route::post('/institution-invites/accept', [InstitutionInviteController::class, 'accept'])
            ->name('api.v1.institution-invites.accept');
    });

    Route::middleware([
        'auth.first-party',
        'organization.context',
    ])->group(function (): void {
        Route::get('/home', [LearnerHomeController::class, 'show'])
            ->name('api.v1.home.show');
        Route::put(
            '/me/onboarding',
            [LearnerOnboardingController::class, 'update'],
        )->name('api.v1.me.onboarding.update');
        Route::get(
            '/me/ai-memory',
            [LearnerMemoryController::class, 'index'],
        )->name('api.v1.me.ai-memory.index');
        Route::post(
            '/me/ai-memory',
            [LearnerMemoryController::class, 'store'],
        )->name('api.v1.me.ai-memory.store');
        Route::patch(
            '/me/ai-memory/{fact}',
            [LearnerMemoryController::class, 'update'],
        )->whereUlid('fact')
            ->name('api.v1.me.ai-memory.update');
        Route::delete(
            '/me/ai-memory/{fact}',
            [LearnerMemoryController::class, 'destroy'],
        )->whereUlid('fact')
            ->name('api.v1.me.ai-memory.destroy');
        Route::get(
            '/missions/{mission}',
            [LearnerMissionController::class, 'show'],
        )->whereUlid('mission')
            ->name('api.v1.missions.show');
        Route::get(
            '/missions/{mission}/offline-bundle',
            [LearnerOfflineController::class, 'bundle'],
        )->whereUlid('mission')
            ->name('api.v1.missions.offline-bundle.show');
        Route::post(
            '/offline-completions',
            [LearnerOfflineController::class, 'complete'],
        )->name('api.v1.offline-completions.store');
        Route::post(
            '/missions/{mission}/attempts',
            [LearnerAttemptController::class, 'start'],
        )->whereUlid('mission')
            ->name('api.v1.missions.attempts.store');
        Route::post(
            '/attempts/{attempt}/responses',
            [LearnerAttemptController::class, 'respond'],
        )->whereUlid('attempt')
            ->name('api.v1.attempts.responses.store');
        Route::post(
            '/attempts/{attempt}/submit',
            [LearnerAttemptController::class, 'submit'],
        )->whereUlid('attempt')
            ->name('api.v1.attempts.submit');
        Route::get(
            '/progress',
            [LearnerAttemptController::class, 'progress'],
        )->name('api.v1.progress.show');
        Route::get(
            '/media-assets/{asset}/content',
            [LearnerMediaController::class, 'show'],
        )->whereUlid('asset')
            ->name('api.v1.media-assets.content.show');
        Route::get(
            '/me/levels',
            [LearnerLevelSessionController::class, 'index'],
        )->name('api.v1.me.levels.index');
        Route::get(
            '/me/levels/{levelNumber}/sessions',
            [LearnerLevelSessionController::class, 'sessions'],
        )->whereNumber('levelNumber')
            ->name('api.v1.me.levels.sessions.index');
        Route::get(
            '/me/levels/{levelNumber}/sessions/{partId}',
            [LearnerLevelSessionController::class, 'show'],
        )->whereNumber('levelNumber')
            ->whereIn('partId', ['introduction', 'practice', 'conversation', 'interaction'])
            ->name('api.v1.me.levels.sessions.show');
        Route::get(
            '/me/levels/{levelNumber}/sessions/{partId}/audio',
            [LearnerLevelSessionController::class, 'audio'],
        )->whereNumber('levelNumber')
            ->whereIn('partId', ['introduction', 'practice', 'conversation', 'interaction'])
            ->name('api.v1.me.levels.sessions.audio.show');
        Route::post(
            '/me/levels/{levelNumber}/sessions/{partId}/completions',
            [LearnerLevelSessionController::class, 'complete'],
        )->whereNumber('levelNumber')
            ->whereIn('partId', ['introduction', 'practice', 'conversation', 'interaction'])
            ->name('api.v1.me.levels.sessions.completions.store');
        Route::post(
            '/me/levels/{levelNumber}/sessions/{partId}/check-submissions',
            [LearnerLevelSessionController::class, 'submitCheck'],
        )->whereNumber('levelNumber')
            ->whereIn('partId', ['introduction', 'practice', 'conversation', 'interaction'])
            ->name('api.v1.me.levels.sessions.check-submissions.store');
        Route::post(
            '/me/levels/{levelNumber}/sessions/{partId}/conversation-sessions',
            [LearnerLevelSessionController::class, 'startConversation'],
        )->whereNumber('levelNumber')
            ->whereIn('partId', ['introduction', 'practice', 'conversation', 'interaction'])
            ->middleware('throttle:conversation-prototype')
            ->name('api.v1.me.levels.sessions.conversation-sessions.store');
        Route::post(
            '/conversation-sessions',
            [ConversationController::class, 'start'],
        )->middleware('throttle:conversation-prototype')
            ->name('api.v1.conversation-sessions.store');
        Route::post(
            '/conversation-sessions/{session}/stages',
            [ConversationController::class, 'advanceStage'],
        )->whereUlid('session')
            ->middleware('throttle:conversation-prototype')
            ->name('api.v1.conversation-sessions.stages.store');
        Route::post(
            '/conversation-sessions/{session}/turns',
            [ConversationController::class, 'turn'],
        )->whereUlid('session')
            ->middleware('throttle:conversation-prototype')
            ->name('api.v1.conversation-sessions.turns.store');
        Route::get(
            '/conversation-turns/{turn}/events',
            [ConversationController::class, 'events'],
        )->whereUlid('turn')
            ->name('api.v1.conversation-turns.events.index');
        Route::get('/voice-consents', [VoiceController::class, 'consents'])
            ->name('api.v1.voice-consents.index');
        Route::post('/voice-consents', [VoiceController::class, 'grant'])
            ->middleware('throttle:voice-recording')
            ->name('api.v1.voice-consents.store');
        Route::delete(
            '/voice-consents/{purpose}',
            [VoiceController::class, 'withdraw'],
        )->whereIn('purpose', [
            'voice_upload_and_transcription',
            'conversation_provider_processing',
            'transcript_history',
        ])->middleware('throttle:voice-recording')
            ->name('api.v1.voice-consents.destroy');
        Route::post(
            '/conversation-sessions/{session}/recordings',
            [VoiceController::class, 'upload'],
        )->whereUlid('session')
            ->middleware('throttle:voice-recording')
            ->name('api.v1.conversation-sessions.recordings.store');
        Route::get(
            '/voice-recordings/{recording}',
            [VoiceController::class, 'show'],
        )->whereUlid('recording')
            ->name('api.v1.voice-recordings.show');
        Route::post(
            '/voice-recordings/{recording}/transcript-review',
            [VoiceController::class, 'review'],
        )->whereUlid('recording')
            ->middleware('throttle:voice-recording')
            ->name('api.v1.voice-recordings.transcript-review.store');
        Route::delete(
            '/voice-recordings/{recording}',
            [VoiceController::class, 'delete'],
        )->whereUlid('recording')
            ->middleware('throttle:voice-recording')
            ->name('api.v1.voice-recordings.destroy');
        Route::get(
            '/voice-recordings/{recording}/deletion',
            [VoiceController::class, 'deletion'],
        )->whereUlid('recording')
            ->name('api.v1.voice-recordings.deletion.show');

        Route::post(
            '/operator/lesson-practice/blueprints',
            [OperatorLessonPracticeController::class, 'store'],
        )->name('api.v1.operator.lesson-practice.blueprints.store');
        Route::get(
            '/operator/lesson-practice/blueprints/{blueprint}',
            [OperatorLessonPracticeController::class, 'show'],
        )->whereUlid('blueprint')
            ->name('api.v1.operator.lesson-practice.blueprints.show');
        Route::post(
            '/operator/lesson-practice/blueprints/{blueprint}/reviews',
            [OperatorLessonPracticeController::class, 'storeReview'],
        )->whereUlid('blueprint')
            ->name('api.v1.operator.lesson-practice.blueprints.reviews.store');
        Route::post(
            '/operator/lesson-practice/blueprints/{blueprint}/attachments',
            [OperatorLessonPracticeController::class, 'storeAttachments'],
        )->whereUlid('blueprint')
            ->name('api.v1.operator.lesson-practice.blueprints.attachments.store');
        Route::get(
            '/operator/content/drafts',
            [OperatorContentController::class, 'drafts'],
        )->name('api.v1.operator.content.drafts.index');
        Route::get(
            '/operator/content/publications',
            [OperatorContentController::class, 'publicationStatus'],
        )->name('api.v1.operator.content.publications.index');
        Route::post(
            '/operator/content/reviews/english',
            [OperatorContentController::class, 'reviewEnglish'],
        )->name('api.v1.operator.content.reviews.english.store');
        Route::post(
            '/operator/content/reviews/kannada',
            [OperatorContentController::class, 'reviewKannada'],
        )->name('api.v1.operator.content.reviews.kannada.store');
        Route::post(
            '/operator/content/publications',
            [OperatorContentController::class, 'publish'],
        )->name('api.v1.operator.content.publications.store');
        Route::post(
            '/operator/content/rollbacks',
            [OperatorContentController::class, 'rollback'],
        )->name('api.v1.operator.content.rollbacks.store');
        Route::get(
            '/operator/audit-events',
            [OperatorContentController::class, 'auditEvents'],
        )->name('api.v1.operator.audit-events.index');
        Route::get(
            '/operator/pilot-analytics',
            [OperatorContentController::class, 'pilotAnalytics'],
        )->name('api.v1.operator.pilot-analytics.show');
    });
});
