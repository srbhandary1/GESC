<?php

declare(strict_types=1);

namespace App\Providers;

use App\Authentication\AuthenticationSecretDigester;
use App\Authentication\Federated\DisabledFederatedIdentityProvider;
use App\Authentication\Federated\FakeFederatedIdentityProvider;
use App\Authentication\Federated\FederatedIdentityProvider;
use App\Authentication\Federated\HttpOidcFederatedIdentityProvider;
use App\Authentication\Mfa\PrivilegedMfaVerifier;
use App\Authentication\Mfa\Rfc6238PrivilegedMfaVerifier;
use App\Authentication\Mobile\MobileOAuthClient;
use App\Authentication\Mobile\MobilePasswordAuthorizationServerFactory;
use App\Authentication\Mobile\MobilePasswordTokenIssuer;
use App\Authentication\Otp\LocalFixedOtpCodeGenerator;
use App\Authentication\Otp\LocalOtpDeliveryAdapter;
use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Authentication\Otp\SecureOtpCodeGenerator;
use App\Authentication\Password\ConfiguredPasswordBlocklist;
use App\Authentication\Password\FormerRecoveryContactNotifier;
use App\Authentication\Password\PasswordBlocklist;
use App\Authentication\Password\PasswordRecoveryDeliveryAdapter;
use App\Authentication\Password\ProtectedLocalPasswordRecoveryDeliveryAdapter;
use App\Authentication\Password\UnavailableFormerRecoveryContactNotifier;
use App\Authentication\Password\UnavailablePasswordRecoveryDeliveryAdapter;
use App\Authentication\PhoneNumberNormalizer;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Authentication\Sessions\PlatformOrMembershipPrivilegeClassifier;
use App\Authentication\Sessions\PrivilegeClassifier;
use App\Billing\DisabledPaymentProvider;
use App\Billing\DummyPaymentProvider;
use App\Billing\PaymentProvider;
use App\Conversation\Providers\AzureOpenAi\AzureOpenAiConversationModelProvider;
use App\Conversation\Providers\ConfirmedTextSpeechToTextProvider;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\Disabled\DisabledConversationModelProvider;
use App\Conversation\Providers\Disabled\DisabledSpeechToTextProvider;
use App\Conversation\Providers\Disabled\DisabledTextToSpeechProvider;
use App\Conversation\Providers\Fake\DeterministicFakeConversationModelProvider;
use App\Conversation\Providers\Fake\DeterministicFakeSpeechToTextProvider;
use App\Conversation\Providers\Fake\DeterministicFakeTextToSpeechProvider;
use App\Conversation\Providers\SoftLaunch\SoftLaunchGuidanceAzureTextToSpeechProvider;
use App\Conversation\Providers\SoftLaunch\SoftLaunchRecordedAzureSpeechToTextProvider;
use App\Conversation\Providers\SpeechToTextProvider;
use App\Conversation\Providers\TextFirstTextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Realtime\ElevenLabsScribeRealtimeSpeechToTextProvider;
use App\Conversation\Realtime\PrimaryFallbackRealtimeSpeechToTextProvider;
use App\Conversation\Realtime\RealtimeSpeechToTextProvider;
use App\Conversation\Realtime\SarvamSaarasRealtimeSpeechToTextProvider;
use App\Conversation\Voice\LaravelVoiceSourceObjectStore;
use App\Conversation\Voice\Providers\DeterministicTestingSpeechToTextProvider;
use App\Conversation\Voice\Providers\DisabledRecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\Indic\IndicHttpSpeechToTextProvider;
use App\Conversation\Voice\Providers\Indic\IndicWithAzureFailoverSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\VoiceSourceObjectStore;
use App\Curriculum\Commissioning\MediaTranscription\CurriculumMediaSourceReader;
use App\Curriculum\Commissioning\MediaTranscription\LaravelCurriculumMediaSourceReader;
use App\Curriculum\Commissioning\MediaTranscription\LaravelMediaTranscriptionCandidateStore;
use App\Curriculum\Commissioning\MediaTranscription\MediaTranscriptionCandidateStore;
use App\Health\DatabaseReadinessProbe;
use App\Health\PasswordRecoveryDeliveryReadinessProbe;
use App\Health\ReadinessChecker;
use App\Health\RedisReadinessProbe;
use App\Learning\Media\LaravelLessonVideoObjectStore;
use App\Learning\Media\LessonVideoObjectStore;
use App\Learning\Missions\DailyMissionReader;
use App\Learning\Missions\PinnedDailyMissionReader;
use App\Media\Inspection\DeterministicTestingMediaInspector;
use App\Media\Inspection\FfprobeMediaInspector;
use App\Media\Inspection\UnavailableMediaInspector;
use App\Media\LaravelMediaObjectStore;
use App\Media\MediaInspector;
use App\Media\MediaMalwareScanner;
use App\Media\MediaObjectStore;
use App\Media\Scanning\ClamAvMediaMalwareScanner;
use App\Media\Scanning\DeterministicTestingMediaMalwareScanner;
use App\Media\Scanning\UnavailableMediaMalwareScanner;
use App\Observability\HttpRequestLogger;
use App\Tenancy\OrganizationContextManager;
use DateInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::useClientModel(MobileOAuthClient::class);
        Passport::ignoreRoutes();
        Passport::$deviceCodeGrantEnabled = false;
        $this->app->when(MobilePasswordTokenIssuer::class)
            ->needs(AuthorizationServer::class)
            ->give(fn (): AuthorizationServer => $this->app
                ->make(MobilePasswordAuthorizationServerFactory::class)
                ->make());

        $this->app->scoped(
            OrganizationContextManager::class,
            static fn (): OrganizationContextManager => new OrganizationContextManager,
        );

        $this->app->bind(FederatedIdentityProvider::class, function (): FederatedIdentityProvider {
            $driver = (string) config('authentication.federated.driver', 'oidc');

            if ($this->app->environment('production') && $driver === 'fake') {
                return new DisabledFederatedIdentityProvider;
            }

            if ($this->app->environment('testing') || $driver === 'fake') {
                return new FakeFederatedIdentityProvider;
            }

            return $this->app->make(HttpOidcFederatedIdentityProvider::class);
        });
        $this->app->bind(PaymentProvider::class, function (): PaymentProvider {
            $driver = (string) config('billing.driver', 'dummy');

            if ($this->app->environment('production') || $driver === 'disabled') {
                return new DisabledPaymentProvider;
            }

            return new DummyPaymentProvider;
        });
        $this->app->bind(OtpCodeGenerator::class, function (): OtpCodeGenerator {
            $localCode = config('authentication.otp.local_fixed_code');

            if (
                $this->app->environment('local')
                && config('authentication.otp.driver') === 'local'
                && is_string($localCode)
                && $localCode !== ''
            ) {
                return new LocalFixedOtpCodeGenerator($this->app, $localCode);
            }

            return $this->app->make(SecureOtpCodeGenerator::class);
        });
        $this->app->bind(PrivilegeClassifier::class, PlatformOrMembershipPrivilegeClassifier::class);
        $this->app->bind(PasswordBlocklist::class, ConfiguredPasswordBlocklist::class);
        $this->app->bind(
            FormerRecoveryContactNotifier::class,
            UnavailableFormerRecoveryContactNotifier::class,
        );
        $this->app->bind(PasswordRecoveryDeliveryAdapter::class, function (): PasswordRecoveryDeliveryAdapter {
            if ($this->app->environment('local')
                && config('authentication.password.recovery_delivery_driver') === 'protected-local') {
                return $this->app->make(ProtectedLocalPasswordRecoveryDeliveryAdapter::class);
            }

            return $this->app->make(UnavailablePasswordRecoveryDeliveryAdapter::class);
        });
        $this->app->bind(DailyMissionReader::class, PinnedDailyMissionReader::class);
        $this->app->bind(
            LessonVideoObjectStore::class,
            LaravelLessonVideoObjectStore::class,
        );
        $this->app->bind(MediaObjectStore::class, LaravelMediaObjectStore::class);
        $this->app->bind(
            MediaTranscriptionCandidateStore::class,
            LaravelMediaTranscriptionCandidateStore::class,
        );
        $this->app->bind(
            CurriculumMediaSourceReader::class,
            LaravelCurriculumMediaSourceReader::class,
        );
        $this->app->bind(MediaInspector::class, function (): MediaInspector {
            if ($this->app->environment('testing')) {
                return $this->app->make(
                    DeterministicTestingMediaInspector::class,
                );
            }

            return config('media.inspector_driver') === 'ffprobe'
                ? $this->app->make(FfprobeMediaInspector::class)
                : $this->app->make(UnavailableMediaInspector::class);
        });
        $this->app->bind(
            MediaMalwareScanner::class,
            function (): MediaMalwareScanner {
                if ($this->app->environment('testing')) {
                    return $this->app->make(
                        DeterministicTestingMediaMalwareScanner::class,
                    );
                }

                return config('media.scanner_driver') === 'clamav'
                    ? $this->app->make(ClamAvMediaMalwareScanner::class)
                    : $this->app->make(
                        UnavailableMediaMalwareScanner::class,
                    );
            },
        );
        $this->app->bind(
            PrivilegedMfaVerifier::class,
            Rfc6238PrivilegedMfaVerifier::class,
        );
        $conversationDriver = (string) config('conversation.provider_driver');
        // Non-production only: production must keep CONVERSATION_PROVIDER_DRIVER=disabled.
        $nonProductionConversationEnv = $this->app->environment(
            ...self::conversationNonProductionEnvironments(),
        );
        $fakeConversationEnabled = $nonProductionConversationEnv
            && $conversationDriver === 'fake';
        $azureConversationEnabled = $nonProductionConversationEnv
            && $conversationDriver === 'azure-openai-dev';
        $this->app->bind(
            SpeechToTextProvider::class,
            match (true) {
                $fakeConversationEnabled => DeterministicFakeSpeechToTextProvider::class,
                $azureConversationEnabled => ConfirmedTextSpeechToTextProvider::class,
                default => DisabledSpeechToTextProvider::class,
            },
        );
        $this->app->bind(
            ConversationModelProvider::class,
            match (true) {
                $fakeConversationEnabled => DeterministicFakeConversationModelProvider::class,
                $azureConversationEnabled => AzureOpenAiConversationModelProvider::class,
                default => DisabledConversationModelProvider::class,
            },
        );
        // Typed or learner-confirmed transcripts remain primary. Soft-launch
        // guidance TTS (APP-0064) may bind Azure Speech for GuidanceTtsPolicy
        // voices; otherwise the product reports an explicit text-first fallback.
        $guidanceTtsLive = $azureConversationEnabled
            && $this->app->environment('soft-launch')
            && filter_var(
                config('conversation.guidance_tts.live_requests_enabled'),
                FILTER_VALIDATE_BOOL,
            );
        $this->app->bind(
            TextToSpeechProvider::class,
            match (true) {
                $fakeConversationEnabled => DeterministicFakeTextToSpeechProvider::class,
                $guidanceTtsLive => SoftLaunchGuidanceAzureTextToSpeechProvider::class,
                $azureConversationEnabled => TextFirstTextToSpeechProvider::class,
                default => DisabledTextToSpeechProvider::class,
            },
        );
        $testingVoiceEnabled = (
            $this->app->environment('local')
            || $this->app->environment('testing')
        ) && config('voice.provider_driver') === 'deterministic-test';
        $softLaunchVoiceEnabled = $this->app->environment('soft-launch')
            && filter_var(config('voice.upload_enabled'), FILTER_VALIDATE_BOOL)
            && config('voice.provider_driver') === 'azure';
        $indicVoiceEnabled = (
            $this->app->environment('local')
            || $this->app->environment('testing')
            || $this->app->environment('soft-launch')
        ) && filter_var(config('voice.upload_enabled'), FILTER_VALIDATE_BOOL)
            && config('voice.provider_driver') === 'indic';
        $this->app->bind(
            RecordedSpeechToTextProvider::class,
            function () use (
                $testingVoiceEnabled,
                $softLaunchVoiceEnabled,
                $indicVoiceEnabled,
            ): RecordedSpeechToTextProvider {
                if ($testingVoiceEnabled) {
                    return $this->app->make(
                        DeterministicTestingSpeechToTextProvider::class,
                    );
                }

                if ($softLaunchVoiceEnabled) {
                    return $this->app->make(
                        SoftLaunchRecordedAzureSpeechToTextProvider::class,
                    );
                }

                if ($indicVoiceEnabled) {
                    return new IndicWithAzureFailoverSpeechToTextProvider(
                        $this->app->make(IndicHttpSpeechToTextProvider::class),
                        $this->app->make(
                            SoftLaunchRecordedAzureSpeechToTextProvider::class,
                        ),
                    );
                }

                return $this->app->make(
                    DisabledRecordedSpeechToTextProvider::class,
                );
            },
        );
        $this->app->bind(
            RealtimeSpeechToTextProvider::class,
            function (): RealtimeSpeechToTextProvider {
                return new PrimaryFallbackRealtimeSpeechToTextProvider(
                    $this->app->make(
                        SarvamSaarasRealtimeSpeechToTextProvider::class,
                    ),
                    $this->app->make(
                        ElevenLabsScribeRealtimeSpeechToTextProvider::class,
                    ),
                );
            },
        );
        $this->app->bind(
            VoiceSourceObjectStore::class,
            LaravelVoiceSourceObjectStore::class,
        );
        $this->app->bind(OtpDeliveryAdapter::class, function (): OtpDeliveryAdapter {
            if (config('authentication.otp.driver') !== 'local') {
                throw new LogicException('No reviewed OTP delivery adapter is configured.');
            }

            return $this->app->make(LocalOtpDeliveryAdapter::class);
        });

        $this->app->singleton(
            ReadinessChecker::class,
            fn (): ReadinessChecker => new ReadinessChecker(
                $this->app->make(DatabaseReadinessProbe::class),
                $this->app->make(RedisReadinessProbe::class),
                $this->app->make(PasswordRecoveryDeliveryReadinessProbe::class),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $applicationUrl = config('app.url');

        if (
            is_string($applicationUrl)
            && parse_url($applicationUrl, PHP_URL_SCHEME) === 'https'
        ) {
            URL::forceScheme('https');
        }

        Passport::tokensExpireIn(new DateInterval(sprintf(
            'PT%dM',
            (int) config('authentication.mobile.access_token_minutes', 15),
        )));
        Passport::refreshTokensExpireIn(new DateInterval(sprintf(
            'P%dD',
            (int) config('authentication.mobile.family_inactivity_days', 30),
        )));
        Passport::$revokeRefreshTokenAfterUse = true;
        Passport::tokensCan([
            'mobile:api' => 'Use the Global English mobile API.',
        ]);
        Passport::defaultScopes(['mobile:api']);
        Passport::authorizationView('auth.oauth-authorize');

        RateLimiter::for('otp-requests', function (Request $request): array {
            $digester = $this->app->make(AuthenticationSecretDigester::class);
            $destination = trim((string) $request->input('mobile_number', ''));
            $ip = (string) ($request->ip() ?? 'unknown');

            try {
                $destination = $this->app
                    ->make(PhoneNumberNormalizer::class)
                    ->normalize($destination);
            } catch (\InvalidArgumentException) {
                // Invalid input still receives an abuse-control key before
                // validation returns the generic public problem response.
            }

            return [
                Limit::perMinutes(
                    (int) config('authentication.otp.request_limit_minutes', 10),
                    (int) config('authentication.otp.request_limit_per_destination', 3),
                )->by($digester->digest('otp-request-destination', $destination)),
                Limit::perMinutes(
                    (int) config('authentication.otp.request_limit_minutes', 10),
                    (int) config('authentication.otp.request_limit_per_ip', 10),
                )->by($digester->digest('otp-request-ip', $ip)),
            ];
        });

        RateLimiter::for('otp-verifications', function (Request $request): Limit {
            $digester = $this->app->make(AuthenticationSecretDigester::class);
            $challenge = (string) $request->input(
                'challenge_id',
                $request->hasSession()
                    ? $request->session()->get('mobile_oauth.challenge_id', '')
                    : '',
            );
            $ip = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinutes(
                (int) config('authentication.otp.verify_limit_minutes', 10),
                (int) config('authentication.otp.verify_limit_per_challenge', 10),
            )->by($digester->digest('otp-verification', $challenge."\0".$ip));
        });

        RateLimiter::for('password-sign-ins', function (Request $request): array {
            $digester = $this->app->make(AuthenticationSecretDigester::class);
            $account = strtolower(trim((string) $request->input('username', '')));
            $ip = (string) ($request->ip() ?? 'unknown');

            return [
                Limit::perMinutes(15, 5)->by(
                    $digester->digest('password-sign-in-account', $account),
                ),
                Limit::perMinutes(15, 20)->by(
                    $digester->digest('password-sign-in-ip', $ip),
                ),
                Limit::perMinute(100)->by('password-sign-in-global'),
            ];
        });

        RateLimiter::for('password-confirmations', function (Request $request): array {
            $digester = $this->app->make(AuthenticationSecretDigester::class);
            $actor = (string) ($request->user('web')?->getAuthIdentifier() ?? 'anonymous');
            $session = $request->hasSession()
                ? (string) $request->session()->get(
                    AuthenticationSessionService::SESSION_KEY,
                    'unknown',
                )
                : 'unknown';

            return [
                Limit::perMinutes(15, 5)->by(
                    $digester->digest('password-confirmation-account', $actor),
                ),
                Limit::perMinutes(15, 5)->by(
                    $digester->digest('password-confirmation-session', $session),
                ),
            ];
        });

        RateLimiter::for('password-recovery-requests', function (Request $request): array {
            $digester = $this->app->make(AuthenticationSecretDigester::class);
            $lookup = strtolower(trim((string) $request->input('username', '')));
            $ip = (string) ($request->ip() ?? 'unknown');

            return [
                Limit::perHour(3)->by($digester->digest('password-recovery-lookup', $lookup)),
                Limit::perHour(10)->by($digester->digest('password-recovery-ip', $ip)),
            ];
        });

        RateLimiter::for('password-recoveries', function (Request $request): array {
            $digester = $this->app->make(AuthenticationSecretDigester::class);
            $challenge = (string) $request->input('challenge_id', 'unknown');
            $ip = (string) ($request->ip() ?? 'unknown');

            return [
                Limit::perHour(5)->by($digester->digest('password-recovery-challenge', $challenge)),
                Limit::perHour(10)->by($digester->digest('password-recovery-consume-ip', $ip)),
            ];
        });

        RateLimiter::for('account-recovery-management', function (Request $request): array {
            $digester = $this->app->make(AuthenticationSecretDigester::class);
            $actor = (string) ($request->user('web')?->getAuthIdentifier() ?? 'anonymous');
            $target = (string) (
                $request->route('userId')
                ?? $request->input('target_user_id', 'unknown')
            );

            return [
                Limit::perHour(5)->by(
                    $digester->digest('account-recovery-management-actor', $actor),
                ),
                Limit::perHour(3)->by(
                    $digester->digest('account-recovery-management-target', $target),
                ),
            ];
        });

        RateLimiter::for('mfa-enrollment', function (Request $request): array {
            return $this->mfaLimits($request, 'enrollment', 3, 10);
        });
        RateLimiter::for('mfa-challenge', function (Request $request): array {
            return $this->mfaLimits($request, 'challenge', 5, 5);
        });
        RateLimiter::for('mfa-verification', function (Request $request): array {
            $digester = $this->app->make(AuthenticationSecretDigester::class);
            $challenge = (string) (
                $request->route('challengeId')
                ?? $request->input('challenge_id', 'unknown')
            );

            return [
                Limit::perMinutes(5, 5)->by(
                    $digester->digest('mfa-verification-challenge', $challenge),
                ),
                ...$this->mfaLimits($request, 'verification', 10, 5),
            ];
        });
        RateLimiter::for('mfa-management', function (Request $request): array {
            return $this->mfaLimits($request, 'management', 3, 60);
        });

        RateLimiter::for('oauth-token', function (Request $request): Limit {
            $digester = $this->app->make(AuthenticationSecretDigester::class);
            $client = (string) $request->input('client_id', '');
            $ip = (string) ($request->ip() ?? 'unknown');

            return Limit::perMinute(60)->by(
                $digester->digest('oauth-token-request', $client."\0".$ip),
            );
        });

        RateLimiter::for(
            'conversation-prototype',
            function (Request $request): Limit {
                $digester = $this->app->make(
                    AuthenticationSecretDigester::class,
                );
                $actor = (string) (
                    $request->user()?->getAuthIdentifier()
                    ?? 'anonymous'
                );
                $ip = (string) ($request->ip() ?? 'unknown');

                return Limit::perMinute(30)->by(
                    $digester->digest(
                        'conversation-prototype',
                        $actor."\0".$ip,
                    ),
                );
            },
        );
        RateLimiter::for(
            'voice-recording',
            function (Request $request): Limit {
                $digester = $this->app->make(
                    AuthenticationSecretDigester::class,
                );
                $actor = (string) (
                    $request->user()?->getAuthIdentifier()
                    ?? 'anonymous'
                );

                return Limit::perMinute(10)->by(
                    $digester->digest('voice-recording', $actor),
                );
            },
        );

        if (! $this->app->routesAreCached()) {
            require base_path('routes/auth.php');
        }

        Event::listen(
            RequestHandled::class,
            fn (RequestHandled $event) => $this->app
                ->make(HttpRequestLogger::class)
                ->handle($event),
        );
    }

    /**
     * Environments that may bind non-disabled conversation providers.
     * Production is intentionally absent.
     *
     * @return list<string>
     */
    public static function conversationNonProductionEnvironments(): array
    {
        return [
            'local',
            'testing',
            'conversation-model-dev',
            'staging',
            'soft-launch',
        ];
    }

    /**
     * @return list<Limit>
     */
    private function mfaLimits(
        Request $request,
        string $operation,
        int $attempts,
        int $minutes,
    ): array {
        $digester = $this->app->make(AuthenticationSecretDigester::class);
        $actor = (string) (
            $request->user('web')?->getAuthIdentifier()
            ?? 'anonymous'
        );
        $session = $request->hasSession()
            ? (string) $request->session()->get(
                AuthenticationSessionService::SESSION_KEY,
                'unknown',
            )
            : 'unknown';
        $ip = (string) ($request->ip() ?? 'unknown');

        return [
            Limit::perMinutes($minutes, $attempts)->by(
                $digester->digest('mfa-'.$operation.'-user', $actor),
            ),
            Limit::perMinutes($minutes, $attempts)->by(
                $digester->digest('mfa-'.$operation.'-session', $session),
            ),
            Limit::perMinutes($minutes, $attempts)->by(
                $digester->digest('mfa-'.$operation.'-ip', $ip),
            ),
        ];
    }
}
