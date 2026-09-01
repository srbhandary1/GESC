<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Voice\Providers\Indic\IndicHttpSpeechToTextProvider;
use App\Conversation\Voice\Providers\Indic\IndicRecordedSpeechConfiguration;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class IndicSpeechAdapterTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_default_configuration_fails_before_any_http_request(): void
    {
        Http::preventStrayRequests();

        try {
            $this->app->make(IndicHttpSpeechToTextProvider::class)->transcribe(
                new RecordedSpeechToTextRequest(
                    interactionId: 'opaque-synthetic-interaction',
                    audioBytes: self::wav(),
                    mimeType: 'audio/wav',
                    language: 'en-IN',
                ),
            );
            self::fail('Expected the disabled provider to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
        }

        Http::assertNothingSent();
    }

    public function test_translate_mode_returns_english_transcript_from_indic_audio(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake([
            'https://stt.indic.test/speech-to-text' => Http::response(
                [
                    'transcript' => 'I practise English after work.',
                    'language_code' => 'kn-IN',
                ],
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $result = $this->app->make(IndicHttpSpeechToTextProvider::class)
            ->transcribe(new RecordedSpeechToTextRequest(
                interactionId: 'opaque-synthetic-interaction',
                audioBytes: self::wav(),
                mimeType: 'audio/wav',
                language: 'en-IN',
            ));

        $this->assertSame('I practise English after work.', $result->transcript);
        $this->assertSame(8700, $result->confidenceBps);
        $this->assertSame('indic-http-stt', $result->usage->providerKey);
        $this->assertSame(
            'indic-http-stt-v1:indic-translate-test',
            $result->usage->modelVersion,
        );
        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->method() === 'POST'
                && $request->url() === 'https://stt.indic.test/speech-to-text'
                && $request->hasHeader('api-subscription-key', 'not-a-real-indic-key')
                && str_contains($body, 'name="model"')
                && str_contains($body, 'indic-translate-test')
                && str_contains($body, 'name="mode"')
                && str_contains($body, 'translate')
                && str_contains($body, 'name="language_code"')
                && str_contains($body, 'unknown')
                && ! str_contains($body, 'opaque-synthetic-interaction');
        });
    }

    public function test_rejects_repository_secret_and_non_https_endpoint(): void
    {
        $this->configureAuthorizedBoundary();
        config()->set('voice.indic.transcribe_url', 'http://stt.indic.test/speech-to-text');
        config()->set('voice.indic.secret_file', base_path('.env.example'));

        $errors = $this->app->make(IndicRecordedSpeechConfiguration::class)
            ->preflightErrors();

        $this->assertContains('endpoint_invalid', $errors);
        $this->assertContains('secret_file_invalid', $errors);
    }

    private function configureAuthorizedBoundary(): void
    {
        $secretPath = sys_get_temp_dir().'/indic-stt-'.Str::random(20).'.key';
        $this->temporaryFiles[] = $secretPath;
        file_put_contents($secretPath, 'not-a-real-indic-key');
        chmod($secretPath, 0600);

        config()->set([
            'voice.upload_enabled' => true,
            'voice.provider_driver' => 'indic',
            'voice.indic.transcribe_url' => 'https://stt.indic.test/speech-to-text',
            'voice.indic.allowed_host' => 'stt.indic.test',
            'voice.indic.secret_file' => $secretPath,
            'voice.indic.auth_header' => 'api-subscription-key',
            'voice.indic.model' => 'indic-translate-test',
            'voice.indic.mode' => 'translate',
            'voice.indic.language_code' => 'unknown',
            'voice.indic.output_language' => 'en-IN',
            'voice.indic.maximum_json_response_bytes' => 65536,
        ]);
    }

    private static function wav(): string
    {
        $sampleRate = 16_000;
        $dataSize = (int) ($sampleRate * 2 / 10);
        $format = 'fmt '
            .pack(
                'VvvVVvv',
                16,
                1,
                1,
                $sampleRate,
                $sampleRate * 2,
                2,
                16,
            );
        $data = 'data'.pack('V', $dataSize).str_repeat("\0", $dataSize);
        $body = 'WAVE'.$format.$data;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }
}
