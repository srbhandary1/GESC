<?php

declare(strict_types=1);

namespace App\Authentication\Mobile;

use Illuminate\Http\Request;

final class OAuthAuthorizationTarget
{
    public const SESSION_KEY = 'mobile_oauth.authorize_target';

    public function capture(Request $request): ?string
    {
        if (! $request->isMethod('GET') || $request->path() !== 'oauth/authorize') {
            return null;
        }

        $target = '/oauth/authorize';
        $query = $request->getQueryString();

        if (is_string($query) && $query !== '') {
            $target .= '?'.$query;
        }

        if (! $this->isSafe($target)) {
            return null;
        }

        $request->session()->put(self::SESSION_KEY, $target);

        return $target;
    }

    public function current(Request $request): ?string
    {
        $target = $request->session()->get(self::SESSION_KEY);

        return is_string($target) && $this->isSafe($target) ? $target : null;
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private function isSafe(string $target): bool
    {
        if (strlen($target) > 4096 || ! str_starts_with($target, '/')) {
            return false;
        }

        $parts = parse_url($target);

        return is_array($parts)
            && ($parts['path'] ?? null) === '/oauth/authorize'
            && ! isset($parts['scheme'])
            && ! isset($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['fragment']);
    }
}
