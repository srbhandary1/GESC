<?php

declare(strict_types=1);

use App\Http\Controllers\AuthenticationSessionController;
use App\Http\Controllers\CurrentIdentityController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InstitutionInviteController;
use App\Http\Controllers\LearnerAttemptController;
use App\Http\Controllers\LearnerHomeController;
use App\Http\Controllers\LearnerMediaController;
use App\Http\Controllers\LearnerMissionController;
use App\Http\Controllers\LearnerOfflineController;
use App\Http\Controllers\LearnerOnboardingController;
use App\Http\Controllers\MobileAuthenticationController;
use App\Http\Controllers\OtpAuthenticationController;
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
    });
});
