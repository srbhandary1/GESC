<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Authentication\Mobile\MobileOAuthClient;
use App\Authentication\Mobile\MobilePasswordSignInGrant;
use App\Authentication\Mobile\MobilePasswordTokenIssuer;
use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Authentication\Password\PasswordAuthenticationFailed;
use App\Authentication\Password\PasswordCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class MobilePasswordAuthenticationController extends Controller
{
    public function __construct(
        private readonly PasswordCredentialService $passwords,
        private readonly MobilePasswordTokenIssuer $issuer,
        private readonly MobileTokenFamilyService $families,
    ) {}

    public function signIn(Request $request): JsonResponse
    {
        if ($this->passwords->mode() === 'legacy_otp') {
            abort(Response::HTTP_NOT_FOUND);
        }

        try {
            if (array_diff(array_keys($request->all()), [
                'username',
                'password',
                'installation_id',
                'client_id',
            ]) !== []) {
                throw ValidationException::withMessages([
                    'request' => ['Unexpected request fields are not permitted.'],
                ]);
            }

            $validated = $request->validate([
                'username' => ['required', 'string', 'max:64'],
                'password' => ['required', 'string', 'max:512'],
                'installation_id' => ['required', 'ulid'],
                'client_id' => ['required', 'uuid'],
            ]);

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

            $user = $this->passwords->authenticate(
                (string) $validated['username'],
                (string) $validated['password'],
                $request,
            );
            $tokens = DB::transaction(function () use ($client, $user, $validated): array {
                $issued = $this->issuer->issue($user, $client);
                $this->families->trackIssuedTokens(
                    MobilePasswordSignInGrant::IDENTIFIER,
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
        } finally {
            $request->request->remove('password');
        }
    }
}
