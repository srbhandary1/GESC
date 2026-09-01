<?php

declare(strict_types=1);

namespace App\Operator\EntitlementAdministration\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class EntitlementAdministrationRejected extends HttpException
{
    public const string NOT_FOUND = 'problem.not_found';

    public function __construct(
        public readonly string $messageKey = self::NOT_FOUND,
        int $status = 404,
    ) {
        parent::__construct($status);
    }
}
