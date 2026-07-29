<?php

declare(strict_types=1);

namespace App\Authentication;

use App\Models\AuthenticationSession;
use App\Models\MobileTokenFamily;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use LogicException;

final readonly class AuthenticatedPrincipal
{
    public const string REQUEST_ATTRIBUTE = 'authenticated_principal';

    private function __construct(
        public User $user,
        public AuthenticationTransport $transport,
        public ?AuthenticationSession $webSession,
        public ?MobileTokenFamily $mobileTokenFamily,
    ) {
        if (
            ($transport === AuthenticationTransport::WebSession)
            !== ($webSession instanceof AuthenticationSession)
            || ($transport === AuthenticationTransport::MobilePassport)
            !== ($mobileTokenFamily instanceof MobileTokenFamily)
        ) {
            throw new LogicException('The authentication principal transport is inconsistent.');
        }
    }

    public static function web(
        User $user,
        AuthenticationSession $session,
    ): self {
        return new self(
            user: $user,
            transport: AuthenticationTransport::WebSession,
            webSession: $session,
            mobileTokenFamily: null,
        );
    }

    public static function mobile(
        User $user,
        MobileTokenFamily $family,
    ): self {
        return new self(
            user: $user,
            transport: AuthenticationTransport::MobilePassport,
            webSession: null,
            mobileTokenFamily: $family,
        );
    }

    public static function fromRequest(Request $request): self
    {
        $principal = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if (! $principal instanceof self) {
            throw new AuthenticationException;
        }

        return $principal;
    }
}
