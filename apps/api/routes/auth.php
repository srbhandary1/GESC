<?php

declare(strict_types=1);

use App\Http\Controllers\MobileAuthorizationLoginController;
use App\Http\Middleware\EnforceMobileOAuthPolicy;
use App\Http\Middleware\EnforcePassportAuthenticationSession;
use App\Http\Middleware\ProtectAuthenticationPageResponse;
use App\Http\Middleware\TrackMobileTokenFamily;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

Route::middleware(['web', ProtectAuthenticationPageResponse::class])->group(function (): void {
    Route::get('/auth/mobile/sign-in', [MobileAuthorizationLoginController::class, 'show'])
        ->name('auth.mobile.sign-in');
    Route::post(
        '/auth/mobile/sign-in/password',
        [MobileAuthorizationLoginController::class, 'submitPassword'],
    )
        ->middleware('throttle:password-sign-ins')
        ->name('auth.mobile.password.store');
    Route::post(
        '/auth/mobile/sign-in/otp/requests',
        [MobileAuthorizationLoginController::class, 'requestChallenge'],
    )
        ->middleware('throttle:otp-requests')
        ->name('auth.mobile.otp.requests.store');
    Route::post(
        '/auth/mobile/sign-in/otp/verifications',
        [MobileAuthorizationLoginController::class, 'verifyChallenge'],
    )
        ->middleware('throttle:otp-verifications')
        ->name('auth.mobile.otp.verifications.store');

    Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
        ->middleware([
            EnforceMobileOAuthPolicy::class,
            EnforcePassportAuthenticationSession::class,
        ])
        ->name('passport.authorizations.authorize');
    Route::post('/oauth/authorize', [ApproveAuthorizationController::class, 'approve'])
        ->middleware(EnforcePassportAuthenticationSession::class)
        ->name('passport.authorizations.approve');
    Route::delete('/oauth/authorize', [DenyAuthorizationController::class, 'deny'])
        ->middleware(EnforcePassportAuthenticationSession::class)
        ->name('passport.authorizations.deny');
});

Route::post('/oauth/token', [AccessTokenController::class, 'issueToken'])
    ->middleware([
        EnforceMobileOAuthPolicy::class,
        'throttle:oauth-token',
        TrackMobileTokenFamily::class,
    ])
    ->name('passport.token');
