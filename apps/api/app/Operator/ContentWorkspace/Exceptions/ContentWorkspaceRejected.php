<?php

declare(strict_types=1);

namespace App\Operator\ContentWorkspace\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class ContentWorkspaceRejected extends HttpException
{
    public const string NOT_FOUND = 'problem.content_workspace.not_found';

    public function __construct(
        public readonly string $messageKey = self::NOT_FOUND,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(404, '', $previous);
    }
}
