<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning\MediaTranscription;

interface CurriculumMediaTranscriptionProvider
{
    public function transcribe(
        MediaTranscriptionRequest $request,
    ): MediaTranscriptionResult;
}
