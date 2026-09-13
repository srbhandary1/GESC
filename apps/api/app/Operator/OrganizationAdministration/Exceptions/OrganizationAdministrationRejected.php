<?php

declare(strict_types=1);

namespace App\Operator\OrganizationAdministration\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class OrganizationAdministrationRejected extends HttpException
{
    public const string NOT_FOUND = 'problem.not_found';

    public const string SELF_TARGET = 'problem.organization_administration.self_target_forbidden';

    public function __construct(
        public readonly string $messageKey = self::NOT_FOUND,
        int $status = 404,
    ) {
        parent::__construct($status);
    }
}
