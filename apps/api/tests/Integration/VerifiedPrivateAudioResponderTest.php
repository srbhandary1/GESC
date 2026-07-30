<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\Media\VerifiedPrivateAudioResponder;
use App\Learning\Missions\DailyMissionDefinition;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VerifiedPrivateAudioResponderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('learning.model_audio_disk', 'local');
    }

    public function test_exact_approved_bytes_are_spooled_verified_and_served_privately(): void
    {
        config()->set('learning.model_audio_maximum_bytes', 1024);
        Storage::fake('local');
        $content = 'approved-model-audio';
        $mission = $this->mission($content);
        Storage::disk('local')->put(
            $mission->modelAudioStorageKey,
            $content,
        );

        $response = $this->app
            ->make(VerifiedPrivateAudioResponder::class)
            ->respond($mission);
        $callback = $response->getCallback();

        if (! is_callable($callback)) {
            self::fail('The verified audio response must be streamable.');
        }

        ob_start();
        $callback();
        $served = ob_get_clean();

        self::assertSame($content, $served);
        self::assertStringContainsString(
            'private',
            (string) $response->headers->get('Cache-Control'),
        );
        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
        self::assertSame(
            (string) strlen($content),
            $response->headers->get('Content-Length'),
        );
        self::assertSame(
            hash('sha256', $content),
            $response->headers->get('X-Content-SHA256'),
        );
    }

    public function test_tampered_or_truncated_storage_object_is_never_served(): void
    {
        config()->set('learning.model_audio_maximum_bytes', 1024);
        Storage::fake('local');
        $approved = 'approved-model-audio';
        $mission = $this->mission($approved);
        Storage::disk('local')->put(
            $mission->modelAudioStorageKey,
            'tampered-model-audio',
        );

        $this->expectException(LearningOperationRejected::class);

        $this->app
            ->make(VerifiedPrivateAudioResponder::class)
            ->respond($mission);
    }

    public function test_over_limit_metadata_fails_before_media_delivery(): void
    {
        config()->set('learning.model_audio_maximum_bytes', 4);
        Storage::fake('local');
        $mission = $this->mission('five!');
        Storage::disk('local')->put(
            $mission->modelAudioStorageKey,
            'five!',
        );

        $this->expectException(LearningOperationRejected::class);

        $this->app
            ->make(VerifiedPrivateAudioResponder::class)
            ->respond($mission);
    }

    public function test_unsupported_audio_mime_fails_closed(): void
    {
        config()->set('learning.model_audio_maximum_bytes', 1024);
        Storage::fake('local');
        $audio = 'approved-model-audio';
        $mission = $this->mission(
            $audio,
            "audio/ogg\r\nX-Unsafe: value",
        );
        Storage::disk('local')->put(
            $mission->modelAudioStorageKey,
            $audio,
        );

        $this->expectException(LearningOperationRejected::class);

        $this->app
            ->make(VerifiedPrivateAudioResponder::class)
            ->respond($mission);
    }

    private function mission(
        string $audio,
        string $mimeType = 'audio/webm',
    ): DailyMissionDefinition {
        return new DailyMissionDefinition(
            missionId: (string) Str::ulid(),
            enrollmentId: (string) Str::ulid(),
            publicationId: (string) Str::ulid(),
            ownerOrganizationId: (string) Str::ulid(),
            curriculumVersionId: (string) Str::ulid(),
            objectiveId: (string) Str::ulid(),
            learningItemId: (string) Str::ulid(),
            manifestHash: str_repeat('1', 64),
            definitionHash: str_repeat('2', 64),
            sourceRevisionId: (string) Str::ulid(),
            sourceContentHash: str_repeat('3', 64),
            kannadaVariantId: (string) Str::ulid(),
            kannadaContentHash: str_repeat('4', 64),
            goalKey: 'everyday-conversation',
            estimatedMinutes: 5,
            title: 'Daily conversation',
            kannadaGuidance: 'ಕನ್ನಡ ಮಾರ್ಗದರ್ಶನ',
            modelText: 'How are you?',
            transcript: 'How are you?',
            canDo: 'I can greet someone.',
            checkId: 'greeting-check',
            checkPrompt: 'Choose the greeting.',
            checkOptions: [
                ['id' => 'correct', 'label' => 'How are you?'],
                ['id' => 'incorrect', 'label' => 'Goodbye.'],
            ],
            correctOptionId: 'correct',
            modelAudioAssetId: (string) Str::ulid(),
            modelAudioMimeType: $mimeType,
            modelAudioContentHash: hash('sha256', $audio),
            modelAudioByteSize: strlen($audio),
            modelAudioDurationMs: 1000,
            modelAudioStorageKey: 'test/model-audio.webm',
        );
    }
}
