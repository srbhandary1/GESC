<?php

declare(strict_types=1);

namespace App\Operator\OrganizationAdministration\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class OrganizationAdministrationConflict extends HttpException
{
    public const string STALE_STATE = 'problem.organization_administration.stale_state';

    public const string IDEMPOTENCY = 'problem.organization_administration.idempotency_conflict';

    public const string LAST_MANAGER = 'problem.organization_administration.last_manager_required';

    public const string ACTIVE_DEPENDENCY = 'problem.organization_administration.active_dependency';

    public function __construct(
        public readonly string $messageKey = self::STALE_STATE,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(409, '', $previous);
    }
}
