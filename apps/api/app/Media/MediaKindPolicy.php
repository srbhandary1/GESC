<?php

declare(strict_types=1);

namespace App\Media;

final class MediaKindPolicy
{
    public const string MODEL_AUDIO = 'model_audio';

    public const string LESSON_AUDIO = 'lesson_audio';

    public const string LESSON_VIDEO = 'lesson_video';

    /** @var list<string> */
    public const array SUPPORTED = [
        self::MODEL_AUDIO,
        self::LESSON_AUDIO,
        self::LESSON_VIDEO,
    ];

    /** @var list<string> */
    public const array CURRENTLY_ATTACHABLE = [
        self::MODEL_AUDIO,
        self::LESSON_VIDEO,
    ];

    public static function isAudio(string $assetKind): bool
    {
        return in_array(
            $assetKind,
            [self::MODEL_AUDIO, self::LESSON_AUDIO],
            true,
        );
    }

    public static function supportsPreviewByteRanges(string $assetKind): bool
    {
        return in_array(
            $assetKind,
            [self::LESSON_AUDIO, self::LESSON_VIDEO],
            true,
        );
    }
}
