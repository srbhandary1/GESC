<?php

declare(strict_types=1);

namespace App\Conversation\Capacity;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class ConversationCapacityUnavailable extends HttpException
{
    public function __construct(int $retryAfter, bool $storeUnavailable = false)
    {
        parent::__construct($storeUnavailable ? 503 : 429, 'Conversation capacity is temporarily unavailable.', headers: ['Retry-After' => (string) max(1, $retryAfter)]);
    }
}
