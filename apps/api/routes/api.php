<?php

declare(strict_types=1);

use App\Http\Controllers\AuthenticationSessionController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CurrentIdentityController;
use App\Http\Controllers\FederatedAuthenticationController;
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
use App\Http\Controllers\LearnerProgressDashboardController;
use App\Http\Controllers\LearnerSubscriptionController;
use App\Http\Controllers\LearnerTuitionRequestController;
use App\Http\Controllers\LocalPasswordEstablishmentController;
use App\Http\Controllers\MobileAuthenticationController;
use App\Http\Controllers\MobilePasswordAuthenticationController;
use App\Http\Controllers\OperatorContentController;
use App\Http\Controllers\OperatorContentWorkspaceController;
use App\Http\Controllers\OperatorEntitlementAdministrationController;
use App\Http\Controllers\OperatorLearnerAdministrationController;
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
    Route::post('/auth/mobile/password/invitation-enrollments', [PasswordAuthenticationController::class, 'enrollWithInvitation'])
        ->middleware('throttle:password-recoveries')
        ->name('api.v1.auth.mobile.password.invitation-enrollments.store');
    Route::get('/auth/mobile/password/mode', [PasswordAuthenticationController::class, 'mode'])
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
            ->whereIn('provider', ['google', 'facebook'])
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
            ->whereIn('provider', ['google', 'facebook'])
            ->name('api.v1.auth.mobile.social.link-starts.store');
        Route::get('/auth/mobile/social/links', [FederatedAuthenticationController::class, 'links'])
            ->name('api.v1.auth.mobile.social.links.index');
        Route::delete(
            '/auth/mobile/social/{provider}/links',
            [FederatedAuthenticationController::class, 'unlink'],
        )
            ->whereIn('provider', ['google', 'facebook'])
            ->name('api.v1.auth.mobile.social.links.destroy');
    });

    Route::middleware([
        'auth.first-party',
        'organization.context',
    ])->group(function (): void {
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
        )->whereUlid('learner')
            ->name('api.v1.operator.learners.entitlements.show');
        Route::post(
            '/operator/learners/{learner}/entitlement-grants',
            [OperatorEntitlementAdministrationController::class, 'grant'],
        )->whereUlid('learner')
            ->name('api.v1.operator.learners.entitlement-grants.store');
        Route::post(
            '/operator/courtesy-premium-grants',
            [OperatorEntitlementAdministrationController::class, 'grantCourtesyPremium'],
        )->name('api.v1.operator.courtesy-premium-grants.store');
        Route::post(
            '/operator/learners/{learner}/signin-throttle-clears',
            [OperatorEntitlementAdministrationController::class, 'clearThrottle'],
        )->whereUlid('learner')
            ->name('api.v1.operator.learners.signin-throttle-clears.store');
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
