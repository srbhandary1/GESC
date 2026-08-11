<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary;

use App\Learning\Media\VideoByteRange;
use App\Media\Exceptions\MediaObjectStoreFailure;
use App\Media\MediaKindPolicy;
use App\Media\MediaObjectStore;
use App\Models\User;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryRejected;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class MediaPreviewResponder
{
    private const int CHUNK_BYTES = 65_536;

    public function __construct(
        private MediaLibraryAuthorization $authorization,
        private MediaObjectStore $objects,
    ) {}

    public function asset(
        User $actor,
        string $assetId,
        Request $request,
    ): StreamedResponse {
        $this->authorization->assertRead($actor);
        $organizationId = $this->authorization->context($actor)->organizationId;
        $asset = DB::table('media_assets')
            ->join('media_asset_lifecycles', function ($join): void {
                $join
                    ->on(
                        'media_asset_lifecycles.organization_id',
                        '=',
                        'media_assets.organization_id',
                    )
                    ->on(
                        'media_asset_lifecycles.media_asset_id',
                        '=',
                        'media_assets.id',
                    );
            })
            ->where('media_assets.organization_id', $organizationId)
            ->where('media_assets.id', $assetId)
            ->first([
                'media_assets.asset_kind',
                'media_assets.storage_object_key',
                'media_assets.mime_type',
                'media_assets.byte_size',
                'media_assets.asset_hash',
            ]);

        if (! $asset instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        try {
            $source = $this->objects->openAccepted(
                $this->string($asset, 'asset_kind'),
                $this->string($asset, 'storage_object_key'),
            );
        } catch (MediaObjectStoreFailure $exception) {
            throw new MediaLibraryRejected($exception);
        }

        return $this->respond(
            source: $source,
            assetKind: $this->string($asset, 'asset_kind'),
            mimeType: $this->string($asset, 'mime_type'),
            byteSize: $this->integer($asset, 'byte_size'),
            sha256: $this->string($asset, 'asset_hash'),
            request: $request,
        );
    }

    public function upload(
        User $actor,
        string $uploadId,
        Request $request,
    ): StreamedResponse {
        $this->authorization->assert($actor, 'media.quarantine.review');
        $organizationId = $this->authorization->context($actor)->organizationId;
        $upload = DB::table('media_uploads')
            ->join('media_security_scans', function ($join): void {
                $join
                    ->on(
                        'media_security_scans.organization_id',
                        '=',
                        'media_uploads.organization_id',
                    )
                    ->on(
                        'media_security_scans.media_upload_id',
                        '=',
                        'media_uploads.id',
                    )
                    ->on(
                        'media_security_scans.id',
                        '=',
                        'media_uploads.selected_scan_id',
                    );
            })
            ->where('media_uploads.organization_id', $organizationId)
            ->where('media_uploads.id', $uploadId)
            ->where('media_uploads.state', 'scan_clean')
            ->where('media_security_scans.inspection_outcome', 'passed')
            ->where('media_security_scans.malware_outcome', 'clean')
            ->first([
                'media_uploads.asset_kind',
                'media_uploads.quarantine_object_key',
                'media_uploads.total_bytes',
                'media_uploads.whole_object_hash',
                'media_security_scans.detected_mime_type',
            ]);

        if (! $upload instanceof stdClass) {
            throw new MediaLibraryRejected;
        }

        try {
            $source = $this->objects->openQuarantine(
                $this->string($upload, 'quarantine_object_key'),
            );
        } catch (MediaObjectStoreFailure $exception) {
            throw new MediaLibraryRejected($exception);
        }

        return $this->respond(
            source: $source,
            assetKind: $this->string($upload, 'asset_kind'),
            mimeType: $this->string($upload, 'detected_mime_type'),
            byteSize: $this->integer($upload, 'total_bytes'),
            sha256: $this->string($upload, 'whole_object_hash'),
            request: $request,
        );
    }

    /** @param resource $source */
    private function respond(
        $source,
        string $assetKind,
        string $mimeType,
        int $byteSize,
        string $sha256,
        Request $request,
    ): StreamedResponse {
        $maximum = config('media.kinds.'.$assetKind.'.maximum_bytes');

        if (
            ! is_int($maximum)
            || $byteSize < 1
            || $byteSize > $maximum
            || preg_match('/\A[0-9a-f]{64}\z/', $sha256) !== 1
        ) {
            fclose($source);

            throw new MediaLibraryRejected;
        }

        $verified = fopen('php://temp/maxmemory:2097152', 'w+b');

        if (! is_resource($verified)) {
            fclose($source);

            throw new MediaLibraryRejected;
        }

        $hash = hash_init('sha256');
        $bytes = 0;

        try {
            while (! feof($source)) {
                $chunk = fread($source, self::CHUNK_BYTES);

                if ($chunk === false || ($chunk === '' && ! feof($source))) {
                    throw new MediaLibraryRejected;
                }

                if ($chunk === '') {
                    break;
                }

                $written = fwrite($verified, $chunk);

                if ($written !== strlen($chunk)) {
                    throw new MediaLibraryRejected;
                }

                $bytes += $written;
                hash_update($hash, $chunk);

                if ($bytes > $byteSize) {
                    throw new MediaLibraryRejected;
                }
            }
        } finally {
            fclose($source);
        }

        if (
            $bytes !== $byteSize
            || ! hash_equals(hash_final($hash), $sha256)
            || rewind($verified) !== true
        ) {
            fclose($verified);

            throw new MediaLibraryRejected;
        }

        $etag = '"'.$sha256.'"';
        try {
            $range = MediaKindPolicy::supportsPreviewByteRanges($assetKind)
                ? VideoByteRange::fromRequest(
                    $request->headers->get('Range'),
                    $request->headers->get('If-Range'),
                    $byteSize,
                    $etag,
                )
                : VideoByteRange::fromRequest(null, null, $byteSize, $etag);
        } catch (\Throwable $exception) {
            fclose($verified);

            throw $exception;
        }

        if (fseek($verified, $range->start, SEEK_SET) !== 0) {
            fclose($verified);

            throw new MediaLibraryRejected;
        }

        $headers = [
            'Accept-Ranges' => MediaKindPolicy::supportsPreviewByteRanges($assetKind)
                ? 'bytes'
                : 'none',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline',
            'Content-Length' => (string) $range->length(),
            'Content-Type' => $mimeType,
            'ETag' => $etag,
            'X-Content-SHA256' => $sha256,
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
                            min(self::CHUNK_BYTES, $remaining),
                        );

                        if ($chunk === false || $chunk === '') {
                            throw new RuntimeException(
                                'Verified media preview ended early.',
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

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (! is_string($value) || $value === '') {
            throw new MediaLibraryRejected;
        }

        return $value;
    }

    private function integer(stdClass $row, string $property): int
    {
        $value = $row->{$property} ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw new MediaLibraryRejected;
    }
}
