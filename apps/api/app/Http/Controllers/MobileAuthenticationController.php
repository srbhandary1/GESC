<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Conversation\Dialogue\ConversationRecoveryStore;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Contracts\OAuthenticatable;
use Symfony\Component\HttpFoundation\Response;

final class MobileAuthenticationController extends Controller
{
    public function __construct(
        private readonly MobileTokenFamilyService $families,
        private readonly ConversationRecoveryStore $recovery,
    ) {}

    public function revoke(Request $request): Response
    {
        if (array_diff(array_keys($request->all()), ['installation_id']) !== []) {
            throw ValidationException::withMessages([
                'request' => ['Unexpected request fields are not permitted.'],
            ]);
        }

        $validated = $request->validate([
            'installation_id' => ['required', 'ulid'],
        ]);

        $user = $request->user('api');

        abort_unless($user instanceof OAuthenticatable, 401);

        $accessToken = $user->currentAccessToken();

        abort_unless($accessToken instanceof AccessToken, 401);

        $this->families->revokeByAccessToken(
            $accessToken->oauth_access_token_id,
            (string) $validated['installation_id'],
        );
        $this->recovery->deleteForUser((string) $user->getAuthIdentifier());

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
