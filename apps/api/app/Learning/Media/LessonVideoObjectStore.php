<?php

declare(strict_types=1);

namespace App\Learning\Media;

use App\Learning\LevelVideos\LevelVideoDefinition;

interface LessonVideoObjectStore
{
    /**
     * Opens a hash-verified object positioned at the first requested byte.
     *
     * @return resource
     */
    public function openVerifiedRange(
        LevelVideoDefinition $video,
        VideoByteRange $range,
    );
}
