<?php

declare(strict_types=1);

use App\Http\Controllers\AccountRecoveryManagementController;
use App\Http\Controllers\AuthenticationSessionController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationPlaybackController;
use App\Http\Controllers\CurrentIdentityController;
use App\Http\Controllers\FederatedAuthenticationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InstitutionInviteController;
use App\Http\Controllers\LearnerAttemptController;
use App\Http\Controllers\LearnerHomeController;
use App\Http\Controllers\LearnerLevelSessionController;
use App\Http\Controllers\LearnerLevelVideoController;
use App\Http\Controllers\LearnerMediaController;
use App\Http\Controllers\LearnerMemoryController;
use App\Http\Controllers\LearnerMissionController;
use App\Http\Controllers\LearnerOfflineController;
use App\Http\Controllers\LearnerOnboardingController;
use App\Http\Controllers\LearnerProgressDashboardController;
use App\Http\Controllers\LearnerSubscriptionController;
use App\Http\Controllers\LearnerTuitionRequestController;
use App\Http\Controllers\LocalPasswordEstablishmentController;
use App\Http\Controllers\MobileAuthenticationController;
use App\Http\Controllers\MobilePaidRegistrationController;
use App\Http\Controllers\MobilePasswordAuthenticationController;
use App\Http\Controllers\OperatorContentController;
use App\Http\Controllers\OperatorContentWorkspaceController;
use App\Http\Controllers\OperatorEntitlementAdministrationController;
use App\Http\Controllers\OperatorLearnerAdministrationController;
use App\Http\Controllers\OperatorLessonPracticeController;
use App\Http\Controllers\OperatorMediaLibraryController;
use App\Http\Controllers\OperatorMediaTranscriptionController;
use App\Http\Controllers\OperatorOrganizationAdministrationController;
use App\Http\Controllers\OperatorPrivacyController;
use App\Http\Controllers\OperatorShellController;
use App\Http\Controllers\OperatorSpeechEconomicsController;
use App\Http\Controllers\OperatorTuitionRequestController;
use App\Http\Controllers\OtpAuthenticationController;
use App\Http\Controllers\PaidRegistrationController;
use App\Http\Controllers\PasswordAuthenticationController;
use App\Http\Controllers\PrivilegedMfaController;
use App\Http\Controllers\RazorpayWebhookController;
use App\Http\Controllers\VoiceController;
use App\Http\Middleware\RequireStatefulBrowserRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health/live', [HealthController::class, 'live'])
        ->name('api.v1.health.live');
    Route::get('/health/ready', [HealthController::class, 'ready'])
        ->name('api.v1.health.ready');
    Route::post('/payments/razorpay/webhook', RazorpayWebhookController::class)
        ->middleware('throttle:payment-webhooks')
        ->name('api.v1.payments.razorpay.webhook');

    Route::post('/auth/otp/requests', [OtpAuthenticationController::class, 'requestChallenge'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:otp-requests'])
        ->name('api.v1.auth.otp.requests.store');
    Route::post('/auth/otp/verifications', [OtpAuthenticationController::class, 'verifyChallenge'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:otp-verifications'])
        ->name('api.v1.auth.otp.verifications.store');
    Route::get('/direct/subscription-plans', [PaidRegistrationController::class, 'plans'])
        ->middleware('throttle:password-sign-ins')
        ->name('api.v1.direct.subscription-plans.index');
    Route::post('/direct/paid-registrations', [PaidRegistrationController::class, 'store'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:password-sign-ins'])
        ->name('api.v1.direct.paid-registrations.store');
    Route::post('/direct/paid-registrations/{checkout}/confirmations', [PaidRegistrationController::class, 'confirm'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:password-sign-ins'])
        ->name('api.v1.direct.paid-registrations.confirm');
    Route::post('/auth/password/sign-ins', [PasswordAuthenticationController::class, 'signIn'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:password-sign-ins'])
        ->name('api.v1.auth.password.sign-ins.store');
    Route::post('/auth/password/invitation-enrollments', [PasswordAuthenticationController::class, 'enrollWithInvitation'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:password-recoveries'])
        ->name('api.v1.auth.password.invitation-enrollments.store');
    Route::get('/auth/password/mode', [PasswordAuthenticationController::class, 'mode'])
        ->middleware(RequireStatefulBrowserRequest::class)
        ->name('api.v1.auth.password.mode');
    Route::post('/auth/password/recovery-requests', [PasswordAuthenticationController::class, 'requestRecovery'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:password-recovery-requests'])
        ->name('api.v1.auth.password.recovery-requests.store');
    Route::post('/auth/mobile/password/sign-ins', [MobilePasswordAuthenticationController::class, 'signIn'])
        ->middleware('throttle:password-sign-ins')
        ->name('api.v1.auth.mobile.password.sign-ins.store');
    Route::post('/auth/mobile/direct/paid-registrations', [MobilePaidRegistrationController::class, 'store'])
        ->middleware('throttle:password-sign-ins')
        ->name('api.v1.auth.mobile.direct.paid-registrations.store');
    Route::post('/auth/mobile/direct/paid-registrations/{checkout}/confirmations', [MobilePaidRegistrationController::class, 'confirm'])
        ->middleware('throttle:password-sign-ins')
        ->name('api.v1.auth.mobile.direct.paid-registrations.confirm');
    Route::post('/auth/mobile/password/invitation-enrollments', [PasswordAuthenticationController::class, 'enrollWithInvitation'])
        ->middleware('throttle:password-recoveries')
        ->name('api.v1.auth.mobile.password.invitation-enrollments.store');
    Route::get('/auth/mobile/password/mode', [MobilePasswordAuthenticationController::class, 'mode'])
        ->name('api.v1.auth.mobile.password.mode');
    Route::post('/auth/mobile/password/recovery-requests', [PasswordAuthenticationController::class, 'requestRecovery'])
        ->middleware('throttle:password-recovery-requests')
        ->name('api.v1.auth.mobile.password.recovery-requests.store');
    Route::get('/auth/social/providers', [FederatedAuthenticationController::class, 'providers'])
        ->name('api.v1.auth.social.providers');
    Route::get('/auth/mobile/social/providers', [FederatedAuthenticationController::class, 'providers'])
        ->name('api.v1.auth.mobile.social.providers');
    Route::get('/auth/mobile/social/{provider}/authorization', [FederatedAuthenticationController::class, 'mobileAuthorization'])
        ->middleware('throttle:password-sign-ins')
        ->whereIn('provider', ['google', 'facebook'])
        ->name('api.v1.auth.mobile.social.authorization');
    Route::post('/auth/mobile/social/sign-ins', [FederatedAuthenticationController::class, 'mobileSignIn'])
        ->middleware('throttle:password-sign-ins')
        ->name('api.v1.auth.mobile.social.sign-ins.store');
    Route::post('/auth/password/recoveries', [PasswordAuthenticationController::class, 'recover'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:password-recoveries'])
        ->name('api.v1.auth.password.recoveries.store');
    Route::post('/auth/password/setups', [PasswordAuthenticationController::class, 'completeSetup'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:password-recoveries'])
        ->name('api.v1.auth.password.setups.store');
    Route::get('/local/password-establishment', [LocalPasswordEstablishmentController::class, 'pending'])
        ->middleware(RequireStatefulBrowserRequest::class)
        ->name('api.v1.local.password-establishment.show');
    Route::post('/local/password-establishment', [LocalPasswordEstablishmentController::class, 'establish'])
        ->middleware([RequireStatefulBrowserRequest::class, 'throttle:password-recoveries'])
        ->name('api.v1.local.password-establishment.store');

    Route::middleware(['auth:web', 'auth.session'])->group(function (): void {
        Route::get('/auth/password/status', [PasswordAuthenticationController::class, 'status'])
            ->name('api.v1.auth.password.status');
        Route::post('/auth/password/establishments', [PasswordAuthenticationController::class, 'establish'])
            ->middleware('throttle:password-confirmations')
            ->name('api.v1.auth.password.establishments.store');
        Route::post('/auth/password/confirmations', [PasswordAuthenticationController::class, 'confirm'])
            ->middleware('throttle:password-confirmations')
            ->name('api.v1.auth.password.confirmations.store');
        Route::put('/auth/password', [PasswordAuthenticationController::class, 'changePassword'])
            ->middleware('throttle:password-confirmations')
            ->name('api.v1.auth.password.update');
        Route::put('/auth/username', [PasswordAuthenticationController::class, 'changeUsername'])
            ->middleware('throttle:password-confirmations')
            ->name('api.v1.auth.username.update');
        Route::get('/auth/social/links', [FederatedAuthenticationController::class, 'links'])
            ->name('api.v1.auth.social.links.index');
        Route::delete('/auth/social/{provider}/links', [FederatedAuthenticationController::class, 'unlink'])
            ->whereIn('provider', ['google', 'facebook', 'instagram'])
            ->name('api.v1.auth.social.links.destroy');
        Route::post('/auth/logout', [AuthenticationSessionController::class, 'logout'])
            ->name('api.v1.auth.logout');
        Route::get('/auth/sessions', [AuthenticationSessionController::class, 'index'])
            ->name('api.v1.auth.sessions.index');
        Route::delete('/auth/sessions', [AuthenticationSessionController::class, 'destroyAll'])
            ->name('api.v1.auth.sessions.destroy-all');
        Route::delete('/auth/sessions/{sessionId}', [AuthenticationSessionController::class, 'destroy'])
            ->whereUlid('sessionId')
            ->name('api.v1.auth.sessions.destroy');
        Route::get('/auth/mfa', [PrivilegedMfaController::class, 'show'])
            ->name('api.v1.auth.mfa.show');
        Route::post(
            '/auth/mfa/enrollments',
            [PrivilegedMfaController::class, 'beginEnrollment'],
        )->middleware('throttle:mfa-enrollment')
            ->name('api.v1.auth.mfa.enrollments.store');
        Route::post(
            '/auth/mfa/enrollments/{factorId}/confirm',
            [PrivilegedMfaController::class, 'confirmEnrollment'],
        )->whereUlid('factorId')
            ->middleware('throttle:mfa-verification')
            ->name('api.v1.auth.mfa.enrollments.confirm');
        Route::post(
            '/auth/mfa/challenges',
            [PrivilegedMfaController::class, 'issueChallenge'],
        )->middleware('throttle:mfa-challenge')
            ->name('api.v1.auth.mfa.challenges.store');
        Route::post(
            '/auth/mfa/challenges/{challengeId}/verify',
            [PrivilegedMfaController::class, 'verifyChallenge'],
        )->whereUlid('challengeId')
            ->middleware('throttle:mfa-verification')
            ->name('api.v1.auth.mfa.challenges.verify');
        Route::post(
            '/auth/mfa/recovery-codes/regenerate',
            [PrivilegedMfaController::class, 'regenerateRecoveryCodes'],
        )->middleware(['auth.password-confirmed', 'throttle:mfa-management'])
            ->name('api.v1.auth.mfa.recovery-codes.regenerate');
        Route::delete(
            '/auth/mfa/factor',
            [PrivilegedMfaController::class, 'revokeFactor'],
        )->middleware(['auth.password-confirmed', 'throttle:mfa-management'])
            ->name('api.v1.auth.mfa.factor.destroy');
    });

    Route::prefix('platform')->middleware(['auth:web', 'auth.session', 'auth.password-confirmed'])
        ->group(function (): void {
            Route::post('/account-recovery/setups', [AccountRecoveryManagementController::class, 'requestSetup'])
                ->middleware('throttle:account-recovery-management')
                ->name('api.v1.platform.account-recovery.setups.store');
            Route::put('/account-recovery/users/{userId}/contact', [AccountRecoveryManagementController::class, 'replaceContact'])
                ->whereUlid('userId')
                ->middleware('throttle:account-recovery-management')
                ->name('api.v1.platform.account-recovery.users.contact.update');
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
        Route::post(
            '/auth/mobile/social/{provider}/link-starts',
            [FederatedAuthenticationController::class, 'mobileLinkStart'],
        )
            ->middleware('throttle:password-sign-ins')
            ->whereIn('provider', ['google', 'facebook', 'instagram'])
            ->name('api.v1.auth.mobile.social.link-starts.store');
        Route::get('/auth/mobile/social/links', [FederatedAuthenticationController::class, 'links'])
            ->name('api.v1.auth.mobile.social.links.index');
        Route::delete(
            '/auth/mobile/social/{provider}/links',
            [FederatedAuthenticationController::class, 'unlink'],
        )
            ->whereIn('provider', ['google', 'facebook', 'instagram'])
            ->name('api.v1.auth.mobile.social.links.destroy');
    });

    Route::middleware([
        'auth.first-party',
        'organization.context',
    ])->group(function (): void {
        Route::post('/conversation-playback-progress', ConversationPlaybackController::class)->name('api.v1.conversation-playback-progress.store');
        Route::get('/home', [LearnerHomeController::class, 'show'])
            ->name('api.v1.home.show');
        Route::get(
            '/me/progress-dashboard',
            [LearnerProgressDashboardController::class, 'show'],
        )->name('api.v1.me.progress-dashboard.show');
        Route::get(
            '/me/entitlement',
            [LearnerSubscriptionController::class, 'show'],
        )->name('api.v1.me.entitlement.show');
        Route::get(
            '/me/subscription-plans',
            [LearnerSubscriptionController::class, 'plans'],
        )->name('api.v1.me.subscription-plans.index');
        Route::post(
            '/me/subscription-checkouts',
            [LearnerSubscriptionController::class, 'checkout'],
        )->name('api.v1.me.subscription-checkouts.store');
        Route::get(
            '/me/subscription-checkouts/{checkout}',
            [LearnerSubscriptionController::class, 'reconcile'],
        )->whereUlid('checkout')
            ->name('api.v1.me.subscription-checkouts.show');
        Route::post(
            '/me/subscription-checkouts/{checkout}/confirm',
            [LearnerSubscriptionController::class, 'confirm'],
        )->whereUlid('checkout')
            ->name('api.v1.me.subscription-checkouts.confirm');
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
            '/me/tuition-requests',
            [LearnerTuitionRequestController::class, 'index'],
        )->name('api.v1.me.tuition-requests.index');
        Route::post(
            '/me/tuition-requests',
            [LearnerTuitionRequestController::class, 'store'],
        )->name('api.v1.me.tuition-requests.store');
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
        Route::get(
            '/me/levels/{levelNumber}/offline-bundle',
            [LearnerOfflineController::class, 'levelBundle'],
        )->whereNumber('levelNumber')
            ->name('api.v1.me.levels.offline-bundle.show');
        Route::post(
            '/offline-level-checkpoint-submissions',
            [LearnerOfflineController::class, 'completeLevelCheckpoint'],
        )->name('api.v1.offline-level-checkpoint-submissions.store');
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
            '/me/level-videos',
            [LearnerLevelVideoController::class, 'index'],
        )->name('api.v1.me.level-videos.index');
        Route::get(
            '/me/levels',
            [LearnerLevelSessionController::class, 'index'],
        )->name('api.v1.me.levels.index');
        Route::get(
            '/me/levels/{levelNumber}/checkpoint',
            [LearnerLevelSessionController::class, 'checkpoint'],
        )->whereNumber('levelNumber')
            ->name('api.v1.me.levels.checkpoint.show');
        Route::post(
            '/me/levels/{levelNumber}/checkpoint-submissions',
            [LearnerLevelSessionController::class, 'submitCheckpoint'],
        )->whereNumber('levelNumber')
            ->name('api.v1.me.levels.checkpoint-submissions.store');
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
        Route::get(
            '/media-assets/{asset}/lesson-video',
            [LearnerLevelVideoController::class, 'show'],
        )->whereUlid('asset')
            ->name('api.v1.media-assets.lesson-video.show');
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
        Route::get('/conversation-sessions/{session}', [ConversationController::class, 'show'])
            ->whereUlid('session')->name('api.v1.conversation-sessions.show');
        Route::post('/conversation-sessions/{session}/exit', [ConversationController::class, 'exit'])
            ->whereUlid('session')->middleware('throttle:conversation-prototype')->name('api.v1.conversation-sessions.exit');
        Route::post(
            '/conversation-sessions/{session}/turns',
            [ConversationController::class, 'turn'],
        )->whereUlid('session')
            ->middleware('throttle:conversation-prototype')
            ->name('api.v1.conversation-sessions.turns.store');
        Route::post(
            '/conversation-sessions/{session}/speech',
            [ConversationController::class, 'speak'],
        )->whereUlid('session')
            ->middleware('throttle:conversation-prototype')
            ->name('api.v1.conversation-sessions.speech.store');
        Route::post(
            '/conversation-sessions/{session}/realtime-stt-tickets',
            [ConversationController::class, 'issueRealtimeTicket'],
        )->whereUlid('session')
            ->middleware('throttle:conversation-prototype')
            ->name('api.v1.conversation-sessions.realtime-stt-tickets.store');
        Route::get(
            '/conversation-sessions/{session}/realtime-stt',
            [ConversationController::class, 'realtimeSocket'],
        )->whereUlid('session')
            ->name('api.v1.conversation-sessions.realtime-stt.show');
        Route::get(
            '/conversation-turns/{turn}/events',
            [ConversationController::class, 'events'],
        )->whereUlid('turn')
            ->name('api.v1.conversation-turns.events.index');
        Route::get(
            '/conversation-turns/{turn}/speech/{kind}',
            [ConversationController::class, 'speechAudio'],
        )->whereUlid('turn')
            ->whereIn('kind', ['model_en', 'guidance_l1'])
            ->name('api.v1.conversation-turns.speech.show');
        Route::post(
            '/conversation-turns/{turn}/speech-latency-samples',
            [ConversationController::class, 'recordLatency'],
        )->whereUlid('turn')
            ->middleware('throttle:conversation-prototype')
            ->name('api.v1.conversation-turns.speech-latency-samples.store');
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

    });

    Route::middleware([
        'auth.first-party',
        'auth.web-only',
        'organization.context',
    ])->group(function (): void {
        Route::get(
            '/operator/overview',
            [OperatorShellController::class, 'overview'],
        )->name('api.v1.operator.overview.show');
        Route::get(
            '/operator/entitlement-workspace',
            [OperatorEntitlementAdministrationController::class, 'workspace'],
        )->name('api.v1.operator.entitlement-workspace.show');
        Route::post(
            '/operator/tax-settings/updates',
            [OperatorEntitlementAdministrationController::class, 'updateTax'],
        )->name('api.v1.operator.tax-settings.updates.store');
        Route::post(
            '/operator/entitlement-plans/{plan}/updates',
            [OperatorEntitlementAdministrationController::class, 'updatePlan'],
        )->whereUlid('plan')
            ->name('api.v1.operator.entitlement-plans.updates.store');
        Route::get(
            '/operator/learners/{learner}/entitlements',
            [OperatorEntitlementAdministrationController::class, 'learner'],
        )->name('api.v1.operator.learners.entitlements.show');
        Route::post(
            '/operator/learners/{learner}/entitlement-grants',
            [OperatorEntitlementAdministrationController::class, 'grant'],
        )->name('api.v1.operator.learners.entitlement-grants.store');
        Route::post(
            '/operator/courtesy-premium-grants',
            [OperatorEntitlementAdministrationController::class, 'grantCourtesyPremium'],
        )->name('api.v1.operator.courtesy-premium-grants.store');
        Route::post(
            '/operator/learners/{learner}/signin-throttle-clears',
            [OperatorEntitlementAdministrationController::class, 'clearThrottle'],
        )->name('api.v1.operator.learners.signin-throttle-clears.store');
        Route::post(
            '/operator/entitlements/{entitlement}/demo-clock-restarts',
            [OperatorEntitlementAdministrationController::class, 'restartClock'],
        )->whereUlid('entitlement')
            ->name('api.v1.operator.entitlements.demo-clock-restarts.store');
        Route::post(
            '/operator/entitlements/{entitlement}/demo-meter-restarts',
            [OperatorEntitlementAdministrationController::class, 'restartMeter'],
        )->whereUlid('entitlement')
            ->name('api.v1.operator.entitlements.demo-meter-restarts.store');
        Route::post(
            '/operator/entitlements/{entitlement}/revocations',
            [OperatorEntitlementAdministrationController::class, 'revoke'],
        )->whereUlid('entitlement')
            ->name('api.v1.operator.entitlements.revocations.store');
        Route::get(
            '/operator/search',
            [OperatorShellController::class, 'search'],
        )->name('api.v1.operator.search.index');
        Route::get(
            '/operator/learners',
            [OperatorLearnerAdministrationController::class, 'learners'],
        )->name('api.v1.operator.learners.index');
        Route::get(
            '/operator/tuition-requests',
            [OperatorTuitionRequestController::class, 'index'],
        )->name('api.v1.operator.tuition-requests.index');
        Route::post(
            '/operator/tuition-requests/{tuitionRequest}/acknowledgements',
            [OperatorTuitionRequestController::class, 'acknowledge'],
        )->whereUlid('tuitionRequest')
            ->name('api.v1.operator.tuition-requests.acknowledgements.store');
        Route::get(
            '/operator/learners/{learner}',
            [OperatorLearnerAdministrationController::class, 'learner'],
        )->whereUlid('learner')
            ->name('api.v1.operator.learners.show');
        Route::get(
            '/operator/learners/{learner}/enrollments',
            [OperatorLearnerAdministrationController::class, 'enrollments'],
        )->whereUlid('learner')
            ->name('api.v1.operator.learners.enrollments.index');
        Route::post(
            '/operator/learners/{learner}/enrollments',
            [OperatorLearnerAdministrationController::class, 'createEnrollment'],
        )->whereUlid('learner')
            ->name('api.v1.operator.learners.enrollments.store');
        Route::post(
            '/operator/learners/{learner}/goal-corrections',
            [OperatorLearnerAdministrationController::class, 'correctGoal'],
        )->whereUlid('learner')
            ->name('api.v1.operator.learners.goal-corrections.store');
        Route::post(
            '/operator/learners/{learner}/suspensions',
            [OperatorLearnerAdministrationController::class, 'suspendLearner'],
        )->whereUlid('learner')
            ->name('api.v1.operator.learners.suspensions.store');
        Route::post(
            '/operator/learners/{learner}/reactivations',
            [OperatorLearnerAdministrationController::class, 'reactivateLearner'],
        )->whereUlid('learner')
            ->name('api.v1.operator.learners.reactivations.store');
        Route::get(
            '/operator/learner-invitations',
            [OperatorLearnerAdministrationController::class, 'invitations'],
        )->name('api.v1.operator.learner-invitations.index');
        Route::post(
            '/operator/learner-invitations',
            [OperatorLearnerAdministrationController::class, 'issueInvitation'],
        )
            ->name('api.v1.operator.learner-invitations.store');
        Route::post(
            '/operator/learner-invitations/{invitation}/revocations',
            [OperatorLearnerAdministrationController::class, 'revokeInvitation'],
        )->whereUlid('invitation')
            ->name('api.v1.operator.learner-invitations.revocations.store');
        Route::post(
            '/operator/enrollments/{enrollment}/cohort-changes',
            [OperatorLearnerAdministrationController::class, 'changeEnrollmentCohort'],
        )->whereUlid('enrollment')
            ->name('api.v1.operator.enrollments.cohort-changes.store');
        Route::post(
            '/operator/enrollments/{enrollment}/curriculum-activations',
            [OperatorLearnerAdministrationController::class, 'activateEnrollment'],
        )->whereUlid('enrollment')
            ->name('api.v1.operator.enrollments.curriculum-activations.store');
        Route::post(
            '/operator/enrollments/{enrollment}/withdrawals',
            [OperatorLearnerAdministrationController::class, 'withdrawEnrollment'],
        )->whereUlid('enrollment')
            ->name('api.v1.operator.enrollments.withdrawals.store');
        Route::get(
            '/operator/organization',
            [OperatorOrganizationAdministrationController::class, 'organization'],
        )->name('api.v1.operator.organization.show');
        Route::get(
            '/operator/branches',
            [OperatorOrganizationAdministrationController::class, 'branches'],
        )->name('api.v1.operator.branches.index');
        Route::post(
            '/operator/branches',
            [OperatorOrganizationAdministrationController::class, 'createBranch'],
        )
            ->name('api.v1.operator.branches.store');
        Route::post(
            '/operator/branches/{branch}/updates',
            [OperatorOrganizationAdministrationController::class, 'updateBranch'],
        )->whereUlid('branch')
            ->name('api.v1.operator.branches.updates.store');
        Route::post(
            '/operator/branches/{branch}/archivals',
            [OperatorOrganizationAdministrationController::class, 'archiveBranch'],
        )->whereUlid('branch')
            ->name('api.v1.operator.branches.archivals.store');
        Route::get(
            '/operator/cohorts',
            [OperatorOrganizationAdministrationController::class, 'cohorts'],
        )->name('api.v1.operator.cohorts.index');
        Route::post(
            '/operator/cohorts',
            [OperatorOrganizationAdministrationController::class, 'createCohort'],
        )
            ->name('api.v1.operator.cohorts.store');
        Route::post(
            '/operator/cohorts/{cohort}/updates',
            [OperatorOrganizationAdministrationController::class, 'updateCohort'],
        )->whereUlid('cohort')
            ->name('api.v1.operator.cohorts.updates.store');
        Route::post(
            '/operator/cohorts/{cohort}/archivals',
            [OperatorOrganizationAdministrationController::class, 'archiveCohort'],
        )->whereUlid('cohort')
            ->name('api.v1.operator.cohorts.archivals.store');
        Route::get(
            '/operator/memberships',
            [OperatorOrganizationAdministrationController::class, 'memberships'],
        )->name('api.v1.operator.memberships.index');
        Route::post(
            '/operator/memberships',
            [OperatorOrganizationAdministrationController::class, 'createMembership'],
        )->name('api.v1.operator.memberships.store');
        Route::post(
            '/operator/memberships/{membership}/role-assignments',
            [OperatorOrganizationAdministrationController::class, 'assignMembershipRole'],
        )->whereUlid('membership')->middleware('auth.password-confirmed')
            ->name('api.v1.operator.memberships.role-assignments.store');
        Route::post(
            '/operator/memberships/{membership}/role-revocations',
            [OperatorOrganizationAdministrationController::class, 'revokeMembershipRole'],
        )->whereUlid('membership')->middleware('auth.password-confirmed')
            ->name('api.v1.operator.memberships.role-revocations.store');
        Route::post(
            '/operator/memberships/{membership}/deactivations',
            [OperatorOrganizationAdministrationController::class, 'deactivateMembership'],
        )->whereUlid('membership')->middleware('auth.password-confirmed')
            ->name('api.v1.operator.memberships.deactivations.store');
        Route::post(
            '/operator/memberships/{membership}/reactivations',
            [OperatorOrganizationAdministrationController::class, 'reactivateMembership'],
        )->whereUlid('membership')->middleware('auth.password-confirmed')
            ->name('api.v1.operator.memberships.reactivations.store');
        Route::get(
            '/operator/institution-invites',
            [OperatorOrganizationAdministrationController::class, 'invitations'],
        )->name('api.v1.operator.institution-invites.index');
        Route::post(
            '/operator/institution-invites',
            [OperatorOrganizationAdministrationController::class, 'issueInvitation'],
        )->middleware('auth.password-confirmed')
            ->name('api.v1.operator.institution-invites.store');
        Route::post(
            '/operator/institution-invites/{invitation}/revocations',
            [OperatorOrganizationAdministrationController::class, 'revokeInvitation'],
        )->whereUlid('invitation')->middleware('auth.password-confirmed')
            ->name('api.v1.operator.institution-invites.revocations.store');
        Route::get(
            '/operator/roles',
            [OperatorOrganizationAdministrationController::class, 'roles'],
        )->name('api.v1.operator.roles.index');
        Route::post(
            '/operator/roles',
            [OperatorOrganizationAdministrationController::class, 'createRole'],
        )->middleware('auth.password-confirmed')
            ->name('api.v1.operator.roles.store');
        Route::post(
            '/operator/roles/{role}/updates',
            [OperatorOrganizationAdministrationController::class, 'updateRole'],
        )->whereUlid('role')->middleware('auth.password-confirmed')
            ->name('api.v1.operator.roles.updates.store');
        Route::post(
            '/operator/roles/{role}/retirements',
            [OperatorOrganizationAdministrationController::class, 'retireRole'],
        )->whereUlid('role')->middleware('auth.password-confirmed')
            ->name('api.v1.operator.roles.retirements.store');
        Route::get(
            '/operator/trainer-profiles',
            [OperatorOrganizationAdministrationController::class, 'trainers'],
        )->name('api.v1.operator.trainer-profiles.index');
        Route::post(
            '/operator/trainer-profiles/{membership}/cohort-assignments',
            [OperatorOrganizationAdministrationController::class, 'assignTrainerCohort'],
        )->whereUlid('membership')
            ->name('api.v1.operator.trainer-profiles.cohort-assignments.store');
        Route::post(
            '/operator/trainer-profiles/{membership}/cohort-revocations',
            [OperatorOrganizationAdministrationController::class, 'revokeTrainerCohort'],
        )->whereUlid('membership')
            ->name('api.v1.operator.trainer-profiles.cohort-revocations.store');
        Route::get(
            '/operator/trainer-profiles/{membership}/authority-appointments',
            [OperatorOrganizationAdministrationController::class, 'appointments'],
        )->whereUlid('membership')
            ->name('api.v1.operator.trainer-profiles.authority-appointments.index');
        Route::post(
            '/operator/trainer-profiles/{membership}/authority-appointments',
            [OperatorOrganizationAdministrationController::class, 'createAppointment'],
        )->whereUlid('membership')
            ->name('api.v1.operator.trainer-profiles.authority-appointments.store');
        Route::get(
            '/operator/trainer-profiles/{membership}/authority-appointments/{appointment}',
            [OperatorOrganizationAdministrationController::class, 'appointment'],
        )->whereUlid('membership')->whereUlid('appointment')
            ->name('api.v1.operator.trainer-profiles.authority-appointments.show');
        Route::post(
            '/operator/trainer-profiles/{membership}/authority-appointments/{appointment}/acceptances',
            [OperatorOrganizationAdministrationController::class, 'acceptAppointment'],
        )->whereUlid('membership')->whereUlid('appointment')
            ->name('api.v1.operator.trainer-profiles.authority-appointments.acceptances.store');
        Route::post(
            '/operator/trainer-profiles/{membership}/authority-appointments/{appointment}/surrenders',
            [OperatorOrganizationAdministrationController::class, 'surrenderAppointment'],
        )->whereUlid('membership')->whereUlid('appointment')
            ->name('api.v1.operator.trainer-profiles.authority-appointments.surrenders.store');
        Route::post(
            '/operator/trainer-profiles/{membership}/authority-appointments/{appointment}/revocations',
            [OperatorOrganizationAdministrationController::class, 'revokeAppointment'],
        )->whereUlid('membership')->whereUlid('appointment')
            ->name('api.v1.operator.trainer-profiles.authority-appointments.revocations.store');
        Route::get(
            '/operator/membership-sessions/{membership}',
            [OperatorOrganizationAdministrationController::class, 'membershipSessions'],
        )->whereUlid('membership')
            ->name('api.v1.operator.membership-sessions.show');
        Route::post(
            '/operator/membership-sessions/{membership}/revocations',
            [OperatorOrganizationAdministrationController::class, 'revokeMembershipSessions'],
        )->whereUlid('membership')
            ->name('api.v1.operator.membership-sessions.revocations.store');
        Route::get(
            '/operator/content/drafts',
            [OperatorContentController::class, 'drafts'],
        )->name('api.v1.operator.content.drafts.index');
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
            '/operator/content/workspaces',
            [OperatorContentWorkspaceController::class, 'workspaces'],
        )->name('api.v1.operator.content.workspaces.index');
        Route::get(
            '/operator/content/workspaces/{curriculumVersion}',
            [OperatorContentWorkspaceController::class, 'workspace'],
        )->whereUlid('curriculumVersion')
            ->name('api.v1.operator.content.workspaces.show');
        Route::get(
            '/operator/content/workspaces/{curriculumVersion}/preview',
            [OperatorContentWorkspaceController::class, 'preview'],
        )->whereUlid('curriculumVersion')
            ->name('api.v1.operator.content.workspaces.preview.show');
        Route::get(
            '/operator/content/workspaces/{curriculumVersion}/history',
            [OperatorContentWorkspaceController::class, 'history'],
        )->whereUlid('curriculumVersion')
            ->name('api.v1.operator.content.workspaces.history.index');
        Route::get(
            '/operator/content/review-queues',
            [OperatorContentWorkspaceController::class, 'reviewQueue'],
        )->name('api.v1.operator.content.review-queues.index');
        Route::post(
            '/operator/content/programs',
            [OperatorContentWorkspaceController::class, 'createProgram'],
        )
            ->name('api.v1.operator.content.programs.store');
        Route::post(
            '/operator/content/programs/{program}/descriptor-corrections',
            [OperatorContentWorkspaceController::class, 'correctDescriptor'],
        )->whereUlid('program')
            ->name('api.v1.operator.content.programs.descriptor-corrections.store');
        Route::post(
            '/operator/content/versions/{curriculumVersion}/successors',
            [OperatorContentWorkspaceController::class, 'createSuccessor'],
        )->whereUlid('curriculumVersion')
            ->name('api.v1.operator.content.versions.successors.store');
        Route::post(
            '/operator/content/workspaces/{curriculumVersion}/graph-revisions',
            [OperatorContentWorkspaceController::class, 'saveGraph'],
        )->whereUlid('curriculumVersion')
            ->name('api.v1.operator.content.workspaces.graph-revisions.store');
        Route::post(
            '/operator/content/workspaces/{curriculumVersion}/review-submissions',
            [OperatorContentWorkspaceController::class, 'submitReview'],
        )->whereUlid('curriculumVersion')
            ->name('api.v1.operator.content.workspaces.review-submissions.store');
        Route::post(
            '/operator/content/workspaces/{curriculumVersion}/abandonments',
            [OperatorContentWorkspaceController::class, 'abandon'],
        )->whereUlid('curriculumVersion')
            ->name('api.v1.operator.content.workspaces.abandonments.store');
        Route::post(
            '/operator/content/workspaces/{curriculumVersion}/documents',
            [OperatorContentWorkspaceController::class, 'createDocument'],
        )->whereUlid('curriculumVersion')
            ->name('api.v1.operator.content.workspaces.documents.store');
        Route::post(
            '/operator/content/documents/{document}/english-revisions',
            [OperatorContentWorkspaceController::class, 'appendEnglish'],
        )->whereUlid('document')
            ->name('api.v1.operator.content.documents.english-revisions.store');
        Route::post(
            '/operator/content/revisions/{revision}/kannada-variants',
            [OperatorContentWorkspaceController::class, 'appendKannada'],
        )->whereUlid('revision')
            ->name('api.v1.operator.content.revisions.kannada-variants.store');
        Route::get(
            '/operator/content/publications',
            [OperatorContentController::class, 'publicationStatus'],
        )->name('api.v1.operator.content.publications.index');
        Route::post(
            '/operator/content/reviews/structure',
            [OperatorContentWorkspaceController::class, 'reviewStructure'],
        )
            ->name('api.v1.operator.content.reviews.structure.store');
        Route::post(
            '/operator/content/reviews/english',
            [OperatorContentController::class, 'reviewEnglish'],
        )
            ->name('api.v1.operator.content.reviews.english.store');
        Route::post(
            '/operator/content/reviews/kannada',
            [OperatorContentController::class, 'reviewKannada'],
        )
            ->name('api.v1.operator.content.reviews.kannada.store');
        Route::post(
            '/operator/content/reviews/{review}/withdrawals',
            [OperatorContentWorkspaceController::class, 'withdrawReview'],
        )->whereUlid('review')
            ->name('api.v1.operator.content.reviews.withdrawals.store');
        Route::post(
            '/operator/content/publications',
            [OperatorContentController::class, 'publish'],
        )
            ->name('api.v1.operator.content.publications.store');
        Route::post(
            '/operator/content/publication-schedules',
            [OperatorContentWorkspaceController::class, 'schedule'],
        )
            ->name('api.v1.operator.content.publication-schedules.store');
        Route::post(
            '/operator/content/publication-activations',
            [OperatorContentWorkspaceController::class, 'activate'],
        )
            ->name('api.v1.operator.content.publication-activations.store');
        Route::post(
            '/operator/content/publication-schedule-cancellations',
            [OperatorContentWorkspaceController::class, 'cancelSchedule'],
        )
            ->name('api.v1.operator.content.publication-schedule-cancellations.store');
        Route::post(
            '/operator/content/publication-retirements',
            [OperatorContentWorkspaceController::class, 'retire'],
        )
            ->name('api.v1.operator.content.publication-retirements.store');
        Route::post(
            '/operator/content/rollbacks',
            [OperatorContentController::class, 'rollback'],
        )
            ->name('api.v1.operator.content.rollbacks.store');
        Route::get(
            '/operator/media/manifest',
            [OperatorMediaLibraryController::class, 'manifest'],
        )->name('api.v1.operator.media.manifest.show');
        Route::get(
            '/operator/media/assets',
            [OperatorMediaLibraryController::class, 'assets'],
        )->name('api.v1.operator.media.assets.index');
        Route::get(
            '/operator/media/assets/{asset}',
            [OperatorMediaLibraryController::class, 'asset'],
        )->whereUlid('asset')
            ->name('api.v1.operator.media.assets.show');
        Route::get(
            '/operator/media/assets/{asset}/preview',
            [OperatorMediaLibraryController::class, 'assetPreview'],
        )->whereUlid('asset')
            ->name('api.v1.operator.media.assets.preview.show');
        Route::get(
            '/operator/media/uploads',
            [OperatorMediaLibraryController::class, 'uploads'],
        )->name('api.v1.operator.media.uploads.index');
        Route::get(
            '/operator/media/uploads/{upload}',
            [OperatorMediaLibraryController::class, 'upload'],
        )->whereUlid('upload')
            ->name('api.v1.operator.media.uploads.show');
        Route::get(
            '/operator/media/uploads/{upload}/preview',
            [OperatorMediaLibraryController::class, 'uploadPreview'],
        )->whereUlid('upload')
            ->name('api.v1.operator.media.uploads.preview.show');
        Route::post(
            '/operator/media/uploads',
            [OperatorMediaLibraryController::class, 'startUpload'],
        )
            ->name('api.v1.operator.media.uploads.store');
        Route::post(
            '/operator/media/uploads/{upload}/parts',
            [OperatorMediaLibraryController::class, 'storePart'],
        )->whereUlid('upload')
            ->name('api.v1.operator.media.upload-parts.store');
        Route::post(
            '/operator/media/uploads/{upload}/finalizations',
            [OperatorMediaLibraryController::class, 'finalizeUpload'],
        )->whereUlid('upload')
            ->name('api.v1.operator.media.upload-finalizations.store');
        Route::post(
            '/operator/media/uploads/{upload}/acceptances',
            [OperatorMediaLibraryController::class, 'acceptUpload'],
        )->whereUlid('upload')
            ->name('api.v1.operator.media.upload-acceptances.store');
        Route::post(
            '/operator/media/uploads/{upload}/rejections',
            [OperatorMediaLibraryController::class, 'rejectUpload'],
        )->whereUlid('upload')
            ->name('api.v1.operator.media.upload-rejections.store');
        Route::post(
            '/operator/media/assets/{asset}/adoptions',
            [OperatorMediaLibraryController::class, 'adoptAsset'],
        )->whereUlid('asset')
            ->name('api.v1.operator.media.asset-adoptions.store');
        Route::post(
            '/operator/media/assets/{asset}/metadata-revisions',
            [OperatorMediaLibraryController::class, 'appendMetadata'],
        )->whereUlid('asset')
            ->name('api.v1.operator.media.metadata-revisions.store');
        Route::post(
            '/operator/media/metadata-revisions/{revision}/kannada-variants',
            [OperatorMediaLibraryController::class, 'appendKannada'],
        )->whereUlid('revision')
            ->name('api.v1.operator.media.metadata-variants.store');
        Route::post(
            '/operator/media/metadata-reviews',
            [OperatorMediaLibraryController::class, 'reviewMetadata'],
        )
            ->name('api.v1.operator.media.metadata-reviews.store');
        Route::post(
            '/operator/media/rights-decisions',
            [OperatorMediaLibraryController::class, 'rightsDecision'],
        )
            ->name('api.v1.operator.media.rights-decisions.store');
        Route::post(
            '/operator/media/assets/{asset}/attachments',
            [OperatorMediaLibraryController::class, 'attach'],
        )->whereUlid('asset')
            ->name('api.v1.operator.media.attachments.store');
        Route::post(
            '/operator/media/assets/{asset}/replacements',
            [OperatorMediaLibraryController::class, 'replace'],
        )->whereUlid('asset')
            ->name('api.v1.operator.media.replacements.store');
        Route::post(
            '/operator/media/assets/{asset}/retirements',
            [OperatorMediaLibraryController::class, 'retire'],
        )->whereUlid('asset')
            ->name('api.v1.operator.media.retirements.store');
        Route::get(
            '/operator/media/transcription-campaigns/{campaign}/assets/{campaignAsset}/candidate',
            [OperatorMediaTranscriptionController::class, 'candidate'],
        )->whereUlid('campaign')->whereUlid('campaignAsset')
            ->name('api.v1.operator.media.transcription-candidates.show');
        Route::post(
            '/operator/media/transcription-campaigns/{campaign}/assets/{campaignAsset}/reconciliations',
            [OperatorMediaTranscriptionController::class, 'reconcile'],
        )->whereUlid('campaign')->whereUlid('campaignAsset')
            ->name('api.v1.operator.media.transcription-reconciliations.store');
        Route::get(
            '/operator/audit-events',
            [OperatorContentController::class, 'auditEvents'],
        )->name('api.v1.operator.audit-events.index');
        Route::get(
            '/operator/pilot-analytics',
            [OperatorContentController::class, 'pilotAnalytics'],
        )->name('api.v1.operator.pilot-analytics.show');
        Route::get(
            '/operator/speech-economics',
            [OperatorSpeechEconomicsController::class, 'show'],
        )->name('api.v1.operator.speech-economics.show');
        Route::get(
            '/operator/speech-economics/export',
            [OperatorSpeechEconomicsController::class, 'export'],
        )->name('api.v1.operator.speech-economics.export');
        Route::get(
            '/operator/speech-economics/commercial',
            [OperatorSpeechEconomicsController::class, 'commercial'],
        )->name('api.v1.operator.speech-economics.commercial.show');
        Route::post(
            '/operator/speech-economics/commercial/rate-cards',
            [OperatorSpeechEconomicsController::class, 'recordRateCard'],
        )->name('api.v1.operator.speech-economics.commercial.rate-cards.store');
        Route::post(
            '/operator/speech-economics/commercial/statements',
            [OperatorSpeechEconomicsController::class, 'recordStatement'],
        )->name('api.v1.operator.speech-economics.commercial.statements.store');
        Route::get(
            '/operator/support/diagnostics',
            [OperatorPrivacyController::class, 'diagnostics'],
        )->name('api.v1.operator.support.diagnostics.index');
        Route::post(
            '/operator/privacy/exports',
            [OperatorPrivacyController::class, 'createExport'],
        )
            ->name('api.v1.operator.privacy.exports.store');
        Route::get(
            '/operator/privacy/exports/{exportRequest}',
            [OperatorPrivacyController::class, 'showExport'],
        )->whereUlid('exportRequest')
            ->name('api.v1.operator.privacy.exports.show');
        Route::post(
            '/operator/privacy/erasures',
            [OperatorPrivacyController::class, 'createErasure'],
        )
            ->name('api.v1.operator.privacy.erasures.store');
        Route::get(
            '/operator/privacy/erasures/{erasureRequest}',
            [OperatorPrivacyController::class, 'showErasure'],
        )->whereUlid('erasureRequest')
            ->name('api.v1.operator.privacy.erasures.show');
    });
});
