<?php

declare(strict_types=1);

namespace App\Learning\Media;

use App\Learning\LevelVideos\LevelVideoDefinition;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class VerifiedPrivateVideoResponder
{
    private const int RESPONSE_CHUNK_BYTES = 65_536;

    public function __construct(private LessonVideoObjectStore $objects) {}

    public function respond(
        LevelVideoDefinition $video,
        Request $request,
    ): StreamedResponse {
        $etag = '"'.$video->contentHash.'"';
        $range = VideoByteRange::fromRequest(
            rangeHeader: $request->headers->get('Range'),
            ifRangeHeader: $request->headers->get('If-Range'),
            totalBytes: $video->byteSize,
            etag: $etag,
        );
        $verified = $this->objects->openVerifiedRange($video, $range);
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline',
            'Content-Length' => (string) $range->length(),
            'Content-Type' => $video->mimeType,
            'ETag' => $etag,
            'X-Content-SHA256' => $video->contentHash,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($range->partial) {
            $headers['Content-Range'] = sprintf(
                'bytes %d-%d/%d',
                $range->start,
                $range->end,
                $range->totalBytes,
            );
        }

        return response()->stream(
            static function () use ($verified, $range): void {
                $remaining = $range->length();

                try {
                    while ($remaining > 0) {
                        $chunk = fread(
                            $verified,
                            min(self::RESPONSE_CHUNK_BYTES, $remaining),
                        );

                        if ($chunk === false || $chunk === '') {
                            throw new RuntimeException(
                                'Verified lesson-video stream ended early.',
                            );
                        }

                        echo $chunk;
                        $remaining -= strlen($chunk);
                    }
                } finally {
                    fclose($verified);
                }
            },
            $range->partial ? 206 : 200,
            $headers,
        );
    }
}
