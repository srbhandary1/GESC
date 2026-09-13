<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class MediaLibraryConflict extends HttpException
{
    public const string STALE_UPLOAD = 'problem.media_library.stale_upload';

    public const string STALE_METADATA = 'problem.media_library.stale_metadata';

    public const string STALE_LIFECYCLE = 'problem.media_library.stale_lifecycle';

    public const string STALE_WORKSPACE = 'problem.media_library.stale_workspace';

    public const string IDEMPOTENCY = 'problem.media_library.idempotency_conflict';

    public const string DUPLICATE_USE = 'problem.media_library.duplicate_use';

    public const string STORAGE = 'problem.media_library.storage_failure';

    public const string CONCURRENT_CHANGE = 'problem.media_library.concurrent_change';

    public function __construct(
        public readonly string $messageKey = self::CONCURRENT_CHANGE,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(409, '', $previous);
    }
}
