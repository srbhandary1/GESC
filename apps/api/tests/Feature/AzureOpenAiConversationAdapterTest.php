<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Providers\AzureOpenAi\AzureOpenAiConversationConfiguration;
use App\Conversation\Providers\AzureOpenAi\AzureOpenAiConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationProviderException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AzureOpenAiConversationAdapterTest extends TestCase
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
            $this->app->make(AzureOpenAiConversationModelProvider::class)
                ->respond($this->sampleRequest());
            self::fail('Expected the disabled provider to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
            $this->assertFalse($exception->retryable);
        }

        Http::assertNothingSent();
    }

    public function test_production_environment_cannot_authorize_live_azure_openai(): void
    {
        $this->configureAuthorizedBoundary();
        $this->app->detectEnvironment(static fn (): string => 'production');

        $errors = $this->app->make(
            AzureOpenAiConversationConfiguration::class,
        )->preflightErrors();

        $this->assertContains('environment_not_allowlisted', $errors);
        Http::preventStrayRequests();

        try {
            $this->app->make(AzureOpenAiConversationModelProvider::class)
                ->respond($this->sampleRequest());
            self::fail('Expected production to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
        }

        Http::assertNothingSent();
    }

    public function test_configuration_rejects_repository_secret_and_http_endpoint(): void
    {
        $this->configureAuthorizedBoundary();
        config()->set(
            'conversation.azure_openai.endpoint',
            'http://0.0.0.0:8000/provider',
        );
        config()->set(
            'conversation.azure_openai.secret_file',
            base_path('.env.example'),
        );

        $errors = $this->app->make(
            AzureOpenAiConversationConfiguration::class,
        )->preflightErrors();

        $this->assertContains('endpoint_invalid', $errors);
        $this->assertContains('secret_file_invalid', $errors);
    }

    public function test_chat_completions_maps_structured_turn_with_explanation_l1(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();

        $modelJson = json_encode([
            'understood_intent' => 'share_about_work',
            'assistant_reply_en' => 'Thank you for sharing that. What happened next?',
            'safety_state' => 'ok',
            'priority_correction' => [
                'needed' => true,
                'error_category' => 'grammar.article',
                'correction_en' => 'Say: "I enjoy learning English after work."',
                'explanation_l1' => 'ಕೆಲಸದ ನಂತರ ಇಂಗ್ಲಿಷ್ ಕಲಿಯುವುದು ಇಷ್ಟ ಎಂದು ಹೇಳಿ.',
                'explanation_locale' => 'kn-IN',
                'retry_recommended' => true,
            ],
        ], JSON_THROW_ON_ERROR);

        Http::fake([
            'https://conv-test.openai.azure.com/*' => Http::response(
                [
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => $modelJson,
                        ],
                    ]],
                    'usage' => [
                        'prompt_tokens' => 120,
                        'completion_tokens' => 80,
                    ],
                ],
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $result = $this->app->make(AzureOpenAiConversationModelProvider::class)
            ->respond($this->sampleRequest());

        $this->assertSame('share_about_work', $result->understoodIntent);
        $this->assertSame(
            'Thank you for sharing that. What happened next?',
            $result->assistantReply,
        );
        $this->assertNotNull($result->priorityCorrection);
        $this->assertSame(
            'ಕೆಲಸದ ನಂತರ ಇಂಗ್ಲಿಷ್ ಕಲಿಯುವುದು ಇಷ್ಟ ಎಂದು ಹೇಳಿ.',
            $result->priorityCorrection->explanationL1,
        );
        $this->assertSame('kn-IN', $result->priorityCorrection->explanationLocale);
        $this->assertSame('azure-openai', $result->usage()->providerKey);
        $this->assertSame('confidence-gpt-dev', $result->usage()->modelVersion);
        $this->assertSame(120, $result->usage()->inputUnits);
        $this->assertSame(80, $result->usage()->outputUnits);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://conv-test.openai.azure.com/openai/deployments/confidence-gpt-dev/chat/completions?api-version=2024-10-21'
                && $request->hasHeader('api-key', 'not-a-real-provider-key')
                && ($payload['response_format']['type'] ?? null) === 'json_object'
                && str_contains((string) ($payload['messages'][1]['content'] ?? ''), 'kn-IN')
                && ! str_contains($request->body(), 'not-a-real-provider-key');
        });
    }

    public function test_malformed_model_json_fails_closed_without_leaking_body(): void
    {
        $this->configureAuthorizedBoundary();
        Http::preventStrayRequests();
        Http::fake([
            'https://conv-test.openai.azure.com/*' => Http::response(
                [
                    'choices' => [[
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'not-json {{{',
                        ],
                    ]],
                ],
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        try {
            $this->app->make(AzureOpenAiConversationModelProvider::class)
                ->respond($this->sampleRequest());
            self::fail('Expected malformed response to fail closed.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_malformed_response', $exception->safeCode);
            $this->assertStringNotContainsString(
                'not-json',
                $exception->getMessage(),
            );
        }
    }

    private function sampleRequest(): ConversationModelRequest
    {
        $body = "You are a patient Friend coach.\nReturn structured JSON.";

        return new ConversationModelRequest(
            interactionId: 'opaque-turn-id',
            transcript: 'I enjoy learn English after work.',
            personaStyleCode: 'patient-friend-v1',
            scenarioResponseCode: 'friend.everyday.follow-up.v1',
            coachingMode: 'gentle',
            uiLocale: 'kn-IN',
            promptVersionId: (string) Str::ulid(),
            promptBodySha256: hash('sha256', $body),
            promptBody: $body,
        );
    }

    private function configureAuthorizedBoundary(): void
    {
        $this->app->detectEnvironment(
            static fn (): string => 'conversation-model-dev',
        );
        $secretPath = $this->temporaryPath('secret');
        $vendorPath = $this->temporaryPath('vendor.json');
        $spendPath = $this->temporaryPath('spend.json');
        file_put_contents($secretPath, 'not-a-real-provider-key');
        chmod($secretPath, 0600);
        $this->writeEvidence($vendorPath, [
            'schema_version' => 1,
            'provider' => 'azure-openai',
            'region' => 'centralindia',
            'approved_for_conversation_model' => true,
            'data_logging_enabled' => false,
            'model_improvement_enabled' => false,
            'subscription_evidence_id' => 'subscription-evidence-v1',
            'contracting_entity_review_id' => 'entity-review-v1',
            'agreement_sha256' => str_repeat('a', 64),
            'dpa_sha256' => str_repeat('b', 64),
            'subprocessor_sha256' => str_repeat('c', 64),
            'privacy_legal_review_id' => 'privacy-review-v1',
            'logging_disabled_evidence_id' => 'logging-review-v1',
            'reviewed_at' => '2026-08-07T00:00:00Z',
            'valid_until' => '2099-08-07T00:00:00Z',
        ]);
        $this->writeEvidence($spendPath, [
            'schema_version' => 1,
            'approval_id' => 'APP-0022',
            'approved' => true,
            'cap_inr' => 500,
            'reviewed_at' => '2026-08-07T00:00:00Z',
            'valid_until' => '2099-08-07T00:00:00Z',
        ]);

        config()->set([
            'conversation.provider_driver' => 'azure-openai-dev',
            'conversation.azure_openai.live_requests_enabled' => true,
            'conversation.azure_openai.endpoint' => 'https://conv-test.openai.azure.com',
            'conversation.azure_openai.allowed_host' => 'conv-test.openai.azure.com',
            'conversation.azure_openai.deployment' => 'confidence-gpt-dev',
            'conversation.azure_openai.api_version' => '2024-10-21',
            'conversation.azure_openai.credential_kind' => 'api-key',
            'conversation.azure_openai.secret_file' => $secretPath,
            'conversation.azure_openai.approved_spend_cap_inr' => 500,
            'conversation.azure_openai.vendor_evidence_path' => $vendorPath,
            'conversation.azure_openai.spend_approval_path' => $spendPath,
            'conversation.azure_openai.evidence.subscription' => 'subscription-evidence-v1',
            'conversation.azure_openai.evidence.contracting_entity' => 'entity-review-v1',
            'conversation.azure_openai.evidence.agreement_sha256' => str_repeat('a', 64),
            'conversation.azure_openai.evidence.dpa_sha256' => str_repeat('b', 64),
            'conversation.azure_openai.evidence.subprocessor_sha256' => str_repeat('c', 64),
            'conversation.azure_openai.evidence.privacy_legal_review' => 'privacy-review-v1',
            'conversation.azure_openai.evidence.logging_disabled' => 'logging-review-v1',
            'conversation.azure_openai.evidence.credential_version' => 'credential-version-v1',
            'conversation.azure_openai.evidence.spend_approval' => 'spend-approval-v1',
            'conversation.azure_openai.evidence.region_acceptance' => 'region-acceptance-v1',
        ]);
    }

    private function temporaryPath(string $suffix): string
    {
        $path = sys_get_temp_dir()
            .'/a4-openai-'.Str::random(20).'-'.$suffix;
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
}
