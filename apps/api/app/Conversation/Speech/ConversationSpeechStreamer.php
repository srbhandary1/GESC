<?php

declare(strict_types=1);

namespace App\Conversation\Speech;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final readonly class ConversationSpeechStreamer
{
    public function __construct(
        private LearnerAuthority $authority,
        private ConversationSpeechArtifactStore $artifacts,
    ) {}

    public function stream(User $learner, string $turnId, string $kind): StreamedResponse
    {
        if (! in_array($kind, [
            ConversationSpeechArtifactStore::KIND_MODEL,
            ConversationSpeechArtifactStore::KIND_GUIDANCE,
        ], true)) {
            throw new ConversationOperationRejected;
        }

        $context = $this->authority->require($learner);
        $artifact = $this->artifacts->find(
            $context->organizationId,
            (string) $context->membershipId,
            $turnId,
            $kind,
        );

        if ($artifact === null) {
            throw new ConversationOperationRejected;
        }

        $verified = $this->verifiedContent($artifact);

        return response()->stream(
            static function () use ($verified): void {
                try {
                    fpassthru($verified);
                } finally {
                    fclose($verified);
                }
            },
            200,
            [
                'Cache-Control' => 'private, no-store',
                'Content-Disposition' => 'inline',
                'Content-Length' => (string) $artifact->byte_size,
                'Content-Type' => (string) $artifact->mime_type,
                'X-Content-SHA256' => (string) $artifact->content_sha256,
            ],
        );
    }

    /**
     * @return resource
     */
    private function verifiedContent(stdClass $artifact)
    {
        $path = (string) $artifact->storage_path;
        $expectedBytes = (int) $artifact->byte_size;
        $expectedHash = (string) $artifact->content_sha256;
        $disk = (string) config('conversation.speech_artifact_disk', 'local');
        $maximumBytes = max(1, (int) config(
            'conversation.speech_artifact_maximum_bytes',
            2_000_000,
        ));

        if (
            $path === ''
            || $expectedBytes < 1
            || $expectedBytes > $maximumBytes
            || ! preg_match('/^[0-9a-f]{64}$/', $expectedHash)
        ) {
            throw new ConversationOperationRejected;
        }

        try {
            $source = Storage::disk($disk === '' ? 'local' : $disk)->readStream($path);
        } catch (Throwable) {
            throw new ConversationOperationRejected;
        }

        if (! is_resource($source)) {
            throw new ConversationOperationRejected;
        }

        $verified = tmpfile();
        if (! is_resource($verified)) {
            fclose($source);

            throw new ConversationOperationRejected;
        }

        $hash = hash_init('sha256');
        $bytes = 0;

        try {
            while (! feof($source)) {
                $chunk = fread($source, 8192);
                if ($chunk === false) {
                    throw new ConversationOperationRejected;
                }
                if ($chunk === '') {
                    continue;
                }
                $bytes += strlen($chunk);
                if ($bytes > $expectedBytes) {
                    throw new ConversationOperationRejected;
                }
                hash_update($hash, $chunk);
                if (fwrite($verified, $chunk) === false) {
                    throw new ConversationOperationRejected;
                }
            }
        } catch (Throwable $exception) {
            fclose($verified);
            if ($exception instanceof ConversationOperationRejected) {
                throw $exception;
            }

            throw new ConversationOperationRejected;
        } finally {
            fclose($source);
        }

        if (
            $bytes !== $expectedBytes
            || ! hash_equals($expectedHash, hash_final($hash))
            || ! rewind($verified)
        ) {
            fclose($verified);

            throw new ConversationOperationRejected;
        }

        return $verified;
    }
}
