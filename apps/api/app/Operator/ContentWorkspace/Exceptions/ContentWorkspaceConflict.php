<?php

declare(strict_types=1);

namespace App\Operator\ContentWorkspace\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class ContentWorkspaceConflict extends HttpException
{
    public const string STALE_VERSION = 'problem.content_workspace.stale_version';

    public const string IDEMPOTENCY = 'problem.content_workspace.idempotency_conflict';

    public const string STATE = 'problem.content_workspace.state_conflict';

    public const string HEAD = 'problem.content_workspace.head_conflict';

    public const string CONCURRENT_CHANGE = 'problem.content_workspace.concurrent_change';

    public function __construct(
        public readonly string $messageKey = self::STALE_VERSION,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(409, '', $previous);
    }
}
