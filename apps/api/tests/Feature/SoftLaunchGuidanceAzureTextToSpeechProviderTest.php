<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Providers\SoftLaunch\SoftLaunchGuidanceAzureTextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Support\GuidanceTtsPolicy;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SoftLaunchGuidanceAzureTextToSpeechProviderTest extends TestCase
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

    public function test_kannada_guidance_uses_a_separate_voice_for_english_runs(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake([
            'https://guidance-test.cognitiveservices.azure.com/*' => Http::response(
                self::wav(),
                200,
                ['Content-Type' => 'audio/wav'],
            ),
        ]);

        $result = $this->app->make(
            SoftLaunchGuidanceAzureTextToSpeechProvider::class,
        )->synthesize(new TextToSpeechRequest(
            interactionId: 'opaque-synthetic-interaction',
            text: "ಈ ವಾಕ್ಯದಲ್ಲಿ 'I goes daily' ತಪ್ಪಾಗಿದೆ.",
            voice: 'kn-IN-SapnaNeural',
            purpose: GuidanceTtsPolicy::PURPOSE_GUIDANCE_L1,
            uiLocale: 'kn-IN',
        ));

        $this->assertTrue($result->playbackAvailable);
        $this->assertSame(
            'soft-launch-guidance-tts-v1:kn-IN-SapnaNeural+en-IN-NeerjaNeural',
            $result->usage()->modelVersion,
        );
        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->url()
                    === 'https://guidance-test.cognitiveservices.azure.com/tts/cognitiveservices/v1'
                && $request->hasHeader(
                    'Ocp-Apim-Subscription-Key',
                    'not-a-real-provider-key',
                )
                && str_contains(
                    $body,
                    '<voice name="kn-IN-SapnaNeural">',
                )
                && str_contains(
                    $body,
                    '<voice name="en-IN-NeerjaNeural">',
                )
                && ! str_contains($body, 'opaque-synthetic-interaction');
        });
    }

    private function configureAuthorizedBoundary(): void
    {
        $secretPath = $this->temporaryPath('secret');
        $vendorPath = $this->temporaryPath('vendor.json');
        $spendPath = $this->temporaryPath('spend.json');
        $agreementSha256 = str_repeat('a', 64);
        $dpaSha256 = str_repeat('b', 64);
        $subprocessorSha256 = str_repeat('c', 64);

        file_put_contents($secretPath, 'not-a-real-provider-key');
        chmod($secretPath, 0600);
        $this->writeEvidence($vendorPath, [
            'schema_version' => 1,
            'provider' => 'azure-speech',
            'region' => 'centralindia',
            'approved_for_soft_launch_guidance_tts' => true,
            'data_logging_enabled' => false,
            'subscription_evidence_id' => 'subscription-evidence-v1',
            'contracting_entity_review_id' => 'entity-review-v1',
            'agreement_sha256' => $agreementSha256,
            'dpa_sha256' => $dpaSha256,
            'subprocessor_sha256' => $subprocessorSha256,
            'privacy_legal_review_id' => 'privacy-review-v1',
            'logging_disabled_evidence_id' => 'logging-review-v1',
        ]);
        $this->writeEvidence($spendPath, [
            'schema_version' => 1,
            'approval_id' => 'APP-0064',
            'approved' => true,
            'cap_inr' => 500,
            'valid_until' => '2099-08-07T00:00:00Z',
        ]);

        config()->set([
            'conversation.guidance_tts.live_requests_enabled' => true,
            'conversation.guidance_tts.endpoint' => 'https://guidance-test.cognitiveservices.azure.com',
            'conversation.guidance_tts.allowed_host' => 'guidance-test.cognitiveservices.azure.com',
            'conversation.guidance_tts.credential_kind' => 'subscription-key',
            'conversation.guidance_tts.secret_file' => $secretPath,
            'conversation.guidance_tts.approved_spend_cap_inr' => 500,
            'conversation.guidance_tts.vendor_evidence_path' => $vendorPath,
            'conversation.guidance_tts.spend_approval_path' => $spendPath,
            'conversation.guidance_tts.evidence.subscription' => 'subscription-evidence-v1',
            'conversation.guidance_tts.evidence.contracting_entity' => 'entity-review-v1',
            'conversation.guidance_tts.evidence.agreement_sha256' => $agreementSha256,
            'conversation.guidance_tts.evidence.dpa_sha256' => $dpaSha256,
            'conversation.guidance_tts.evidence.subprocessor_sha256' => $subprocessorSha256,
            'conversation.guidance_tts.evidence.privacy_legal_review' => 'privacy-review-v1',
            'conversation.guidance_tts.evidence.logging_disabled' => 'logging-review-v1',
            'conversation.guidance_tts.evidence.credential_version' => 'credential-version-v1',
            'conversation.guidance_tts.evidence.spend_approval' => 'APP-0064',
        ]);
    }

    private function temporaryPath(string $suffix): string
    {
        $path = sys_get_temp_dir()
            .'/guidance-tts-'.Str::random(20).'-'.$suffix;
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function writeEvidence(string $path, array $evidence): void
    {
        file_put_contents($path, json_encode($evidence, JSON_THROW_ON_ERROR));
        chmod($path, 0600);
    }

    private static function wav(): string
    {
        $sampleRate = 24_000;
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
