<?php

declare(strict_types=1);

namespace App\Operator\EntitlementAdministration\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class EntitlementAdministrationConflict extends HttpException
{
    public const string STALE = 'problem.stale_state';

    public const string IDEMPOTENCY = 'problem.idempotency_conflict';

    public function __construct(
        public readonly string $messageKey = self::STALE,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(409, '', $previous);
    }
}
