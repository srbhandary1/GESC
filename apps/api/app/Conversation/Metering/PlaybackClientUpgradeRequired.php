<?php

declare(strict_types=1);

namespace App\Conversation\Metering;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class PlaybackClientUpgradeRequired extends HttpException
{
    public function __construct()
    {
        parent::__construct(426, 'Update the app or reload the page to use measured speaking and listening time.');
    }
}
