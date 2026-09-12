<?php

declare(strict_types=1);

namespace App\Providers;

use App\Authentication\AuthenticationSecretDigester;
use App\Authentication\Federated\DisabledFederatedIdentityProvider;
use App\Authentication\Federated\FakeFederatedIdentityProvider;
use App\Authentication\Federated\FederatedIdentityProvider;
use App\Authentication\Federated\HttpOidcFederatedIdentityProvider;
use App\Authentication\Mfa\PrivilegedMfaVerifier;
use App\Authentication\Mobile\MobilePasswordAuthorizationServerFactory;
use App\Authentication\Mobile\MobilePasswordTokenIssuer;
use App\Authentication\Otp\LocalFixedOtpCodeGenerator;
use App\Authentication\Otp\LocalOtpDeliveryAdapter;
use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Authentication\Otp\SecureOtpCodeGenerator;
use App\Authentication\PhoneNumberNormalizer;
use App\Authentication\Sessions\MembershipRolePrivilegeClassifier;
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
use App\Conversation\Voice\Providers\DeterministicTestingSpeechToTextProvider;
use App\Conversation\Voice\Providers\DisabledRecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\Indic\IndicHttpSpeechToTextProvider;
use App\Conversation\Voice\Providers\Indic\IndicWithAzureFailoverSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Health\DatabaseReadinessProbe;
use App\Health\ReadinessChecker;
use App\Health\RedisReadinessProbe;
use App\Learning\Missions\DailyMissionReader;
use App\Learning\Missions\PinnedDailyMissionReader;
use App\Observability\HttpRequestLogger;
use App\Tenancy\OrganizationContextManager;
use DateInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(PrivilegeClassifier::class, MembershipRolePrivilegeClassifier::class);
        $this->app->bind(DailyMissionReader::class, PinnedDailyMissionReader::class);
        $this->app->bind(PrivilegedMfaVerifier::class, UnavailablePrivilegedMfaVerifier::class);
        $conversationDriver = (string) config('conversation.provider_driver');
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
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
}
