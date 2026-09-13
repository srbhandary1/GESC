<?php

declare(strict_types=1);

namespace App\Conversation\Voice;

interface VoiceSourceObjectStore
{
    /**
     * Delete one private source object.
     *
     * The object key is sensitive operational data and must never be logged.
     */
    public function delete(string $objectKey): bool;
}
