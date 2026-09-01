<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Federated\FederatedIdentityProvider;
use App\Authentication\Federated\FederatedIdentityUnavailable;
use App\Authentication\Federated\FederatedSignInGrant;
use App\Authentication\Federated\FederatedSignInService;
use App\Authentication\Mobile\MobileOAuthClient;
use App\Authentication\Mobile\MobilePasswordTokenIssuer;
use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Authentication\Password\PasswordAuthenticationFailed;
use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Models\FederatedIdentity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class FederatedAuthenticationController extends Controller
{
    public function __construct(
        private readonly FederatedIdentityProvider $providers,
        private readonly FederatedSignInService $federated,
        private readonly PasswordCredentialService $passwords,
        private readonly AuthenticationSessionService $sessions,
        private readonly MobilePasswordTokenIssuer $issuer,
        private readonly MobileTokenFamilyService $families,
    ) {}

    public function providers(): JsonResponse
    {
        return response()->json($this->enabledProviders())
            ->header('Cache-Control', 'private, no-store');
    }

    public function links(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        return response()->json($this->federated->linksFor($user))
            ->header('Cache-Control', 'private, no-store');
    }

    public function unlink(Request $request, string $provider): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->federated->isProvider($provider)) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $this->federated->unlink($user, $provider);

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }

    public function authorize(Request $request, string $provider): RedirectResponse
    {
        if ($this->passwords->mode() === 'legacy_otp' || ! $this->providers->enabled($provider)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $intent = $request->query->get('intent') === 'link' ? 'link' : 'sign_in';
        $user = $this->authorizeUser($request, $intent);

        $installationId = $request->query->get('installation_id');
        $clientId = $request->query->get('client_id');
        $return = is_string($installationId) && $installationId !== '' ? 'android' : 'web';

        if ($return === 'android') {
            $validated = $request->validate([
                'installation_id' => ['required', 'ulid'],
                'client_id' => ['required', 'uuid'],
            ]);
            $installationId = (string) $validated['installation_id'];
            $clientId = (string) $validated['client_id'];
        } else {
            $installationId = null;
            $clientId = null;
        }

        $state = Str::lower(Str::random(43));
        $redirectUri = $this->redirectUri($provider);
        Cache::put(
            $this->stateKey($state),
            [
                'provider' => $provider,
                'intent' => $intent,
                'user_id' => $user instanceof User ? (string) $user->getKey() : null,
                'installation_id' => $installationId,
                'client_id' => $clientId,
                'return' => $return,
                'redirect_uri' => $redirectUri,
            ],
            now()->addMinutes(10),
        );

        try {
            return redirect()->away(
                $this->providers->authorizationUrl($provider, $state, $redirectUri),
            );
        } catch (FederatedIdentityUnavailable) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        if ($this->passwords->mode() === 'legacy_otp' || ! $this->federated->isProvider($provider)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $state = $request->query->get('state');
        $code = $request->query->get('code');

        if (! is_string($state) || $state === '' || ! is_string($code) || $code === '') {
            return $this->federatedFailureRedirect('web', '');
        }

        /** @var array<string, mixed>|null $pending */
        $pending = Cache::pull($this->stateKey($state));

        if (! is_array($pending) || ($pending['provider'] ?? null) !== $provider) {
            return $this->federatedFailureRedirect('web', $state);
        }

        $redirectUri = is_string($pending['redirect_uri'] ?? null)
            ? $pending['redirect_uri']
            : $this->redirectUri($provider);
        $return = is_string($pending['return'] ?? null) ? $pending['return'] : 'web';

        try {
            $account = $this->providers->complete($provider, $code, $redirectUri);
            $intent = is_string($pending['intent'] ?? null) ? $pending['intent'] : 'sign_in';

            if ($intent === 'link') {
                $userId = is_string($pending['user_id'] ?? null) ? $pending['user_id'] : null;
                $user = $userId === null
                    ? null
                    : User::query()->whereKey($userId)->first();

                if (! $user instanceof User) {
                    throw new PasswordAuthenticationFailed;
                }

                $this->federated->link($user, $account);

                return $return === 'android'
                    ? $this->androidTicketRedirect($user, $pending, $state)
                    : redirect($this->webReturnUrl());
            }

            $user = $this->federated->userFor($account);
            $this->federated->record((string) $user->getKey(), 'succeeded', $provider, $request);

            if ($return === 'android') {
                return $this->androidTicketRedirect($user, $pending, $state);
            }

            $this->sessions->start($user, $request);

            return redirect($this->webReturnUrl());
        } catch (FederatedIdentityUnavailable|PasswordAuthenticationFailed) {
            $this->federated->record(null, 'failed', $provider, $request);

            return $this->federatedFailureRedirect($return, $state);
        }
    }

    public function mobileAuthorization(Request $request, string $provider): JsonResponse
    {
        if ($this->passwords->mode() === 'legacy_otp' || ! $this->providers->enabled($provider)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'installation_id' => ['required', 'ulid'],
            'client_id' => ['required', 'uuid'],
        ]);

        $url = url('/oauth/social/'.$provider.'/authorize').'?'.http_build_query([
            'client_id' => $validated['client_id'],
            'installation_id' => $validated['installation_id'],
            'intent' => 'sign_in',
        ]);

        return response()->json(['authorization_url' => $url])
            ->header('Cache-Control', 'private, no-store');
    }

    public function mobileLinkStart(Request $request, string $provider): JsonResponse
    {
        $user = $request->user();

        if (
            ! $user instanceof User
            || $this->passwords->mode() === 'legacy_otp'
            || ! $this->providers->enabled($provider)
        ) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'installation_id' => ['required', 'ulid'],
            'client_id' => ['required', 'uuid'],
        ]);

        $linkRequest = Str::lower(Str::random(43));
        Cache::put(
            $this->linkRequestKey($linkRequest),
            (string) $user->getKey(),
            now()->addMinutes(10),
        );

        $url = url('/oauth/social/'.$provider.'/authorize').'?'.http_build_query([
            'client_id' => $validated['client_id'],
            'installation_id' => $validated['installation_id'],
            'intent' => 'link',
            'link_request' => $linkRequest,
        ]);

        return response()->json(['authorization_url' => $url])
            ->header('Cache-Control', 'private, no-store');
    }

    public function mobileSignIn(Request $request): JsonResponse
    {
        if ($this->passwords->mode() === 'legacy_otp') {
            abort(Response::HTTP_NOT_FOUND);
        }

        try {
            if (array_diff(array_keys($request->all()), [
                'ticket',
                'installation_id',
                'client_id',
            ]) !== []) {
                throw ValidationException::withMessages([
                    'request' => ['Unexpected request fields are not permitted.'],
                ]);
            }

            $validated = $request->validate([
                'ticket' => ['required', 'string', 'min:20', 'max:128'],
                'installation_id' => ['required', 'ulid'],
                'client_id' => ['required', 'uuid'],
            ]);

            $userId = Cache::pull($this->ticketKey((string) $validated['ticket']));

            if (! is_string($userId)) {
                throw new PasswordAuthenticationFailed;
            }

            $client = MobileOAuthClient::query()
                ->whereKey((string) $validated['client_id'])
                ->where('revoked', false)
                ->first();

            if (
                ! $client instanceof MobileOAuthClient
                || ! $client->isApprovedNativeClient()
            ) {
                throw new PasswordAuthenticationFailed;
            }

            $user = User::query()
                ->whereKey($userId)
                ->where('status', User::STATUS_ACTIVE)
                ->first();

            if (! $user instanceof User) {
                throw new PasswordAuthenticationFailed;
            }

            $tokens = DB::transaction(function () use ($client, $user, $validated): array {
                $issued = $this->issuer->issue($user, $client);
                $this->families->trackIssuedTokens(
                    FederatedSignInGrant::IDENTIFIER,
                    (string) $validated['installation_id'],
                    $issued['access_token'],
                    $issued['refresh_token'],
                    null,
                );

                return $issued;
            }, 3);

            return response()->json($tokens)
                ->header('Cache-Control', 'private, no-store');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (PasswordAuthenticationFailed) {
            throw ValidationException::withMessages([
                'credentials' => ['Sign-in could not be completed.'],
            ]);
        }
    }

    private function authorizeUser(Request $request, string $intent): ?User
    {
        if ($intent === 'sign_in') {
            $user = $request->user();

            return $user instanceof User ? $user : null;
        }

        if ($intent !== 'link') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $linkRequest = $request->query->get('link_request');

        if (is_string($linkRequest) && $linkRequest !== '') {
            $userId = Cache::get($this->linkRequestKey($linkRequest));
            $user = is_string($userId)
                ? User::query()->whereKey($userId)->first()
                : null;

            if ($user instanceof User) {
                return $user;
            }
        }

        $sessionUser = $request->user();

        if ($sessionUser instanceof User) {
            return $sessionUser;
        }

        abort(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array{google: bool, facebook: bool}
     */
    private function enabledProviders(): array
    {
        return [
            'google' => $this->providers->enabled(FederatedIdentity::PROVIDER_GOOGLE),
            'facebook' => $this->providers->enabled(FederatedIdentity::PROVIDER_FACEBOOK),
        ];
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function androidTicketRedirect(User $user, array $pending, string $state): RedirectResponse
    {
        $installationId = $pending['installation_id'] ?? null;
        $clientId = $pending['client_id'] ?? null;

        if (! is_string($installationId) || ! is_string($clientId)) {
            throw new PasswordAuthenticationFailed;
        }

        $ticket = Str::lower(Str::random(43));
        Cache::put(
            $this->ticketKey($ticket),
            (string) $user->getKey(),
            now()->addMinutes(2),
        );

        return redirect()->away(
            'com.globalenglish.app:/oauth/callback?'.http_build_query([
                'social_ticket' => $ticket,
                'state' => $state,
            ]),
        );
    }

    private function federatedFailureRedirect(string $return, string $state): RedirectResponse
    {
        if ($return === 'android') {
            return redirect()->away(
                'com.globalenglish.app:/oauth/callback?'.http_build_query([
                    'error' => 'access_denied',
                    'state' => $state,
                ]),
            );
        }

        return redirect($this->webReturnUrl());
    }

    private function redirectUri(string $provider): string
    {
        $configured = config('authentication.federated.'.$provider.'.redirect_uri');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return url('/oauth/social/'.$provider.'/callback');
    }

    private function webReturnUrl(): string
    {
        $configured = config('authentication.federated.web_return_url');

        return is_string($configured) && $configured !== '' ? $configured : '/';
    }

    private function stateKey(string $state): string
    {
        return 'federated-oauth-state:'.$state;
    }

    private function ticketKey(string $ticket): string
    {
        return 'federated-oauth-ticket:'.$ticket;
    }

    private function linkRequestKey(string $linkRequest): string
    {
        return 'federated-link-request:'.$linkRequest;
    }
}
