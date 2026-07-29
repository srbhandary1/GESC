<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authentication\Mobile\MobileTokenFamilyService;
use App\Models\MobileRefreshToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class TrackMobileTokenFamily
{
    public function __construct(private MobileTokenFamilyService $families) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->is('oauth/token')) {
            return $next($request);
        }

        try {
            return DB::transaction(function () use ($request, $next): Response {
                $grantType = $request->string('grant_type')->toString();
                $installationId = $request->string('installation_id')->toString();
                $previousRefreshToken = null;

                if ($grantType === 'refresh_token') {
                    $presentedRefreshToken = $request->string('refresh_token')->toString();
                    $previousRefreshToken = $this->families->lockRefreshToken(
                        $presentedRefreshToken,
                    );

                    if (
                        ! $previousRefreshToken instanceof MobileRefreshToken
                        || ! $this->families->validateRefresh(
                            $previousRefreshToken,
                            $installationId,
                        )
                    ) {
                        return $this->invalidGrant();
                    }
                }

                $response = $next($request);

                if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                    return $response;
                }

                $content = $response->getContent();
                $payload = is_string($content) ? json_decode($content, true) : null;

                if (
                    ! is_array($payload)
                    || ! is_string($payload['access_token'] ?? null)
                    || ! is_string($payload['refresh_token'] ?? null)
                ) {
                    throw new \RuntimeException('The OAuth token response is incomplete.');
                }

                $this->families->trackIssuedTokens(
                    $grantType,
                    $installationId,
                    $payload['access_token'],
                    $payload['refresh_token'],
                    $previousRefreshToken,
                );

                return $response;
            }, 3);
        } catch (Throwable $exception) {
            Log::error('authentication.mobile_token_family_failed', [
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'error' => 'server_error',
                'error_description' => 'The authorization server could not complete the request.',
            ], 500, [
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache',
            ]);
        }
    }

    private function invalidGrant(): JsonResponse
    {
        return response()->json([
            'error' => 'invalid_grant',
            'error_description' => 'The refresh token is invalid.',
        ], 400, [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }
}
