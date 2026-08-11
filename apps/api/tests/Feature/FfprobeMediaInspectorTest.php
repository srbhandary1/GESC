<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Media\Inspection\FfprobeMediaInspector;
use Tests\TestCase;

final class FfprobeMediaInspectorTest extends TestCase
{
    /** @var list<string> */
    private array $executables = [];

    protected function tearDown(): void
    {
        foreach ($this->executables as $executable) {
            @unlink($executable);
        }

        parent::tearDown();
    }

    public function test_long_form_audio_passes_only_the_lesson_audio_duration_policy(): void
    {
        $this->configureProbe(
            durationSeconds: '459.867563',
            streams: [
                [
                    'codec_type' => 'audio',
                    'codec_name' => 'mp3',
                    'nb_read_frames' => '17596',
                ],
            ],
        );
        $inspector = new FfprobeMediaInspector;
        $bytes = 'exact-private-mp3-source-bytes';
        $hash = hash('sha256', $bytes);

        $modelAudio = $inspector->inspect(
            $this->stream($bytes),
            'model_audio',
            strlen($bytes),
            $hash,
        );
        $lessonAudio = $inspector->inspect(
            $this->stream($bytes),
            'lesson_audio',
            strlen($bytes),
            $hash,
        );

        self::assertSame('failed', $modelAudio->outcome);
        self::assertSame('passed', $lessonAudio->outcome);
        self::assertSame('audio/mpeg', $lessonAudio->mimeType);
        self::assertSame(459_868, $lessonAudio->durationMilliseconds);
    }

    public function test_lesson_audio_rejects_video_streams_and_more_than_fifteen_minutes(): void
    {
        $inspector = new FfprobeMediaInspector;
        $bytes = 'exact-private-media-source-bytes';
        $hash = hash('sha256', $bytes);
        $this->configureProbe(
            durationSeconds: '900.001',
            streams: [
                [
                    'codec_type' => 'audio',
                    'codec_name' => 'mp3',
                    'nb_read_frames' => '1',
                ],
            ],
        );

        $tooLong = $inspector->inspect(
            $this->stream($bytes),
            'lesson_audio',
            strlen($bytes),
            $hash,
        );
        self::assertSame('failed', $tooLong->outcome);

        $this->configureProbe(
            durationSeconds: '120.000',
            streams: [
                [
                    'codec_type' => 'audio',
                    'codec_name' => 'aac',
                    'nb_read_frames' => '1',
                ],
                [
                    'codec_type' => 'video',
                    'codec_name' => 'h264',
                    'nb_read_frames' => '1',
                ],
            ],
        );

        $mixedStreams = $inspector->inspect(
            $this->stream($bytes),
            'lesson_audio',
            strlen($bytes),
            $hash,
        );
        self::assertSame('failed', $mixedStreams->outcome);
    }

    /** @param list<array<string, string>> $streams */
    private function configureProbe(
        string $durationSeconds,
        array $streams,
    ): void {
        $document = json_encode([
            'format' => [
                'format_name' => 'mp3',
                'duration' => $durationSeconds,
            ],
            'streams' => $streams,
        ], JSON_THROW_ON_ERROR);
        $executable = tempnam(sys_get_temp_dir(), 'gse-fake-ffprobe-');
        self::assertIsString($executable);
        self::assertNotFalse(file_put_contents(
            $executable,
            "#!/bin/sh\nprintf '%s' ".escapeshellarg($document)."\n",
        ));
        self::assertTrue(chmod($executable, 0700));
        $this->executables[] = $executable;
        config()->set('media.ffprobe_binary', $executable);
    }

    /** @return resource */
    private function stream(string $bytes)
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        self::assertSame(strlen($bytes), fwrite($stream, $bytes));
        self::assertTrue(rewind($stream));

        return $stream;
    }
}
