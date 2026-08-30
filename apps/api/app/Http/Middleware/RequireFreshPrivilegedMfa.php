<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authentication\Mfa\FreshPrivilegedMfaRequired;
use App\Authentication\Password\FreshPasswordConfirmationRequired;
use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Sessions\AuthenticationSessionService;
use App\Models\AuthenticationSession;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireFreshPrivilegedMfa
{
    public function __construct(
        private AuthenticationSessionService $sessions,
        private PasswordCredentialService $passwords,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authenticationSession = $request->attributes->get(
            EnforceAuthenticationSession::REQUEST_ATTRIBUTE,
        );

        if (! $authenticationSession instanceof AuthenticationSession) {
            throw new FreshPasswordConfirmationRequired;
        }

        $credentialReady = $authenticationSession->exists
            && $authenticationSession->user_id !== ''
            && User::query()
                ->whereKey($authenticationSession->user_id)
                ->whereNotNull('username')
                ->whereHas('passwordCredential')
                ->exists();

        if ($this->passwords->mode() === 'legacy_otp' || ! $credentialReady) {
            if (! $this->sessions->hasFreshMfa($authenticationSession)) {
                throw new FreshPrivilegedMfaRequired;
            }
        } elseif (! $this->passwords->hasFreshConfirmation($authenticationSession)) {
            throw new FreshPasswordConfirmationRequired;
        }

        return $next($request);
    }
}
