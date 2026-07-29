<?php

declare(strict_types=1);

namespace App\Observability;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

final class CorrelationId
{
    public const ATTRIBUTE = 'correlation_id';

    public const HEADER = 'X-Correlation-ID';

    public const STARTED_AT_ATTRIBUTE = 'request_started_at_nanoseconds';

    public static function initialize(Request $request): string
    {
        $candidate = $request->headers->get(self::HEADER);
        $correlationId = is_string($candidate) && Str::isUuid($candidate)
            ? strtolower($candidate)
            : (string) Str::uuid();

        $request->attributes->set(self::ATTRIBUTE, $correlationId);
        Context::add(self::ATTRIBUTE, $correlationId);

        return $correlationId;
    }

    public static function forRequest(Request $request): string
    {
        $correlationId = $request->attributes->get(self::ATTRIBUTE);

        if (is_string($correlationId) && Str::isUuid($correlationId)) {
            return $correlationId;
        }

        return self::initialize($request);
    }
}
