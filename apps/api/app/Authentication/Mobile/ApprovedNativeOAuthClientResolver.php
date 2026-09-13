<?php

declare(strict_types=1);

namespace App\Authentication\Mobile;

final class ApprovedNativeOAuthClientResolver
{
    private const CLOSED_BETA_CLIENT_NAME = 'Android closed-beta';

    /**
     * Returns the approved first-party Android OAuth client id when it can be
     * resolved deterministically. Multiple approved clients stay fail-closed
     * unless one is explicitly configured or uniquely named for closed beta.
     */
    public function resolveClientId(): ?string
    {
        $configured = config('authentication.mobile.native_oauth_client_id');

        if (is_string($configured) && $configured !== '') {
            $client = MobileOAuthClient::query()->find($configured);

            return $client instanceof MobileOAuthClient
                && $client->isApprovedNativeClient()
                ? (string) $client->getKey()
                : null;
        }

        $matches = [];

        MobileOAuthClient::query()
            ->where('revoked', false)
            ->orderBy('id')
            ->each(function (MobileOAuthClient $client) use (&$matches): void {
                if ($client->isApprovedNativeClient()) {
                    $matches[] = $client;
                }
            });

        if ($matches === []) {
            return null;
        }

        if (count($matches) === 1) {
            return (string) $matches[0]->getKey();
        }

        $closedBetaMatches = array_values(array_filter(
            $matches,
            fn (MobileOAuthClient $client): bool => $client->getAttribute('name')
                === self::CLOSED_BETA_CLIENT_NAME,
        ));

        if (count($closedBetaMatches) === 1) {
            return (string) $closedBetaMatches[0]->getKey();
        }

        return null;
    }
}
