<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class MediaLibraryRejected extends HttpException
{
    public const string NOT_FOUND = 'problem.media_library.not_found';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(404, '', $previous);
    }
}
