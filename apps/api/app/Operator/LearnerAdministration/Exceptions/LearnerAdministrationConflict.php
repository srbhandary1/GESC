<?php

declare(strict_types=1);

namespace App\Operator\LearnerAdministration\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class LearnerAdministrationConflict extends HttpException
{
    public const string STALE_STATE = 'problem.learner_administration.stale_state';

    public const string IDEMPOTENCY = 'problem.learner_administration.idempotency_conflict';

    public const string ACTIVE_DEPENDENCY = 'problem.learner_administration.active_dependency';

    public const string UNSAFE_ROLE = 'problem.learner_administration.unsafe_role';

    public function __construct(
        public readonly string $messageKey = self::STALE_STATE,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(409, '', $previous);
    }
}
