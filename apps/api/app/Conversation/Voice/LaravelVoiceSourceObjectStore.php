<?php

declare(strict_types=1);

namespace App\Conversation\Voice;

use Illuminate\Support\Facades\Storage;

final readonly class LaravelVoiceSourceObjectStore implements VoiceSourceObjectStore
{
    public function delete(string $objectKey): bool
    {
        return Storage::disk((string) config('voice.storage_disk', 'local'))
            ->delete($objectKey);
    }
}
