<?php

declare(strict_types=1);

namespace App\Providers;

use App\Authentication\AuthenticationSecretDigester;
use App\Authentication\Mfa\PrivilegedMfaVerifier;
use App\Authentication\Mfa\UnavailablePrivilegedMfaVerifier;
use App\Authentication\Otp\LocalFixedOtpCodeGenerator;
use App\Authentication\Otp\LocalOtpDeliveryAdapter;
use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Authentication\Otp\SecureOtpCodeGenerator;
use App\Authentication\PhoneNumberNormalizer;
use App\Authentication\Sessions\MembershipRolePrivilegeClassifier;
use App\Authentication\Sessions\PrivilegeClassifier;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\Disabled\DisabledConversationModelProvider;
use App\Conversation\Providers\Disabled\DisabledSpeechToTextProvider;
use App\Conversation\Providers\Disabled\DisabledTextToSpeechProvider;
use App\Conversation\Providers\Fake\DeterministicFakeConversationModelProvider;
use App\Conversation\Providers\Fake\DeterministicFakeSpeechToTextProvider;
use App\Conversation\Providers\Fake\DeterministicFakeTextToSpeechProvider;
use App\Conversation\Providers\SpeechToTextProvider;
use App\Conversation\Providers\TextToSpeechProvider;
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

        $this->app->scoped(
            OrganizationContextManager::class,
            static fn (): OrganizationContextManager => new OrganizationContextManager,
        );

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
        $fakeConversationEnabled = (
            $this->app->environment('local')
            || $this->app->environment('testing')
        ) && config('conversation.provider_driver') === 'fake';
        $this->app->bind(
            SpeechToTextProvider::class,
            $fakeConversationEnabled
                ? DeterministicFakeSpeechToTextProvider::class
                : DisabledSpeechToTextProvider::class,
        );
        $this->app->bind(
            ConversationModelProvider::class,
            $fakeConversationEnabled
                ? DeterministicFakeConversationModelProvider::class
                : DisabledConversationModelProvider::class,
        );
        $this->app->bind(
            TextToSpeechProvider::class,
            $fakeConversationEnabled
                ? DeterministicFakeTextToSpeechProvider::class
                : DisabledTextToSpeechProvider::class,
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
}
