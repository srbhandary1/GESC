<?php

declare(strict_types=1);

namespace App\Conversation\Providers\AzureOpenAi;

use App\Conversation\Orchestration\PriorityCorrection;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Support\ConversationUiLocales;
use Illuminate\Http\Client\ConnectionException;
use JsonException;

final readonly class AzureOpenAiConversationModelProvider implements ConversationModelProvider
{
    private const string OUTPUT_CONTRACT = <<<'TEXT'
Return only one JSON object (no markdown) with keys:
understood_intent (string),
assistant_reply_en (string, English Friend reply, no HTML),
safety_state ("ok" or "fallback"),
priority_correction (object or null) with:
  needed (boolean),
  error_category (token),
  correction_en (string),
  explanation_l1 (string in the session ui_locale),
  explanation_locale (must equal ui_locale),
  retry_recommended (boolean),
  learner_span (optional string),
  encouragement_l1 (optional string).
Never invent scores. Never change learning records. Never use shame language.
TEXT;

    public function __construct(
        private AzureOpenAiConversationHttpBoundary $http,
        private AzureOpenAiConversationConfiguration $configuration,
    ) {}

    public function respond(
        ConversationModelRequest $request,
    ): ConversationModelResult {
        $this->assertRequestContract($request);

        $started = hrtime(true);
        $maximumBytes = $this->configuration->integer(
            'conversation.azure_openai.maximum_json_response_bytes',
        );

        try {
            $response = $this->http
                ->request($maximumBytes)
                ->acceptJson()
                ->asJson()
                ->post(
                    $this->configuration->chatCompletionsUrl(),
                    $this->requestPayload($request),
                );
        } catch (ConnectionException $exception) {
            throw $this->http->fromConnectionFailure(
                $exception,
                $this->elapsedMilliseconds($started),
            );
        } catch (ConversationProviderException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            if (str_contains(
                $exception->getMessage(),
                'exceeded its byte limit',
            )) {
                throw $this->configuration->failure(
                    'provider_response_too_large',
                    $this->elapsedMilliseconds($started),
                );
            }

            throw $this->configuration->failure(
                'provider_policy_failure',
                $this->elapsedMilliseconds($started),
            );
        }

        $latency = $this->elapsedMilliseconds($started);
        if ($response->status() !== 200) {
            throw $this->http->fromHttpStatus($response->status(), $latency);
        }

        $body = $this->http->boundedBody($response, $maximumBytes);

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->configuration->failure(
                'provider_malformed_response',
                $latency,
            );
        }

        return $this->mapResult($decoded, $request, $latency);
    }

    private function assertRequestContract(ConversationModelRequest $request): void
    {
        if (
            $request->personaStyleCode === ''
            || $request->scenarioResponseCode === ''
            || ! in_array($request->coachingMode, ['gentle', 'tutor', 'fluency'], true)
            || ! ConversationUiLocales::isSupported($request->uiLocale)
            || $request->promptVersionId === ''
            || ! preg_match('/\A[0-9a-f]{64}\z/', $request->promptBodySha256)
            || $request->promptBody === ''
            || ! hash_equals(
                hash('sha256', $request->promptBody),
                $request->promptBodySha256,
            )
            || trim($request->transcript) === ''
        ) {
            throw $this->configuration->failure('provider_request_invalid');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(ConversationModelRequest $request): array
    {
        $memory = $request->memoryFacts === []
            ? 'none'
            : implode("\n- ", $request->memoryFacts);

        $user = json_encode([
            'interaction_id' => $request->interactionId,
            'transcript' => $request->transcript,
            'persona_style_code' => $request->personaStyleCode,
            'scenario_response_code' => $request->scenarioResponseCode,
            'coaching_mode' => $request->coachingMode,
            'ui_locale' => $request->uiLocale,
            'prompt_version_id' => $request->promptVersionId,
            'prompt_body_sha256' => $request->promptBodySha256,
            'memory_facts' => $request->memoryFacts,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return [
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $request->promptBody."\n\n".self::OUTPUT_CONTRACT,
                ],
                [
                    'role' => 'user',
                    'content' => "Session ui_locale={$request->uiLocale}\n"
                        ."coaching_mode={$request->coachingMode}\n"
                        ."memory_facts:\n- {$memory}\n\n"
                        ."Turn payload JSON:\n{$user}",
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function mapResult(
        array $decoded,
        ConversationModelRequest $request,
        int $latencyMilliseconds,
    ): ConversationModelResult {
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (! is_string($content) || trim($content) === '') {
            throw $this->configuration->failure(
                'provider_malformed_response',
                $latencyMilliseconds,
            );
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->configuration->failure(
                'provider_malformed_response',
                $latencyMilliseconds,
            );
        }

        $intent = $payload['understood_intent'] ?? null;
        $reply = $payload['assistant_reply_en'] ?? null;
        $safety = $payload['safety_state'] ?? 'ok';
        if (! is_string($intent) || ! is_string($reply) || ! is_string($safety)) {
            throw $this->configuration->failure(
                'provider_malformed_response',
                $latencyMilliseconds,
            );
        }

        $usage = $decoded['usage'] ?? [];
        $inputUnits = is_array($usage) && is_int($usage['prompt_tokens'] ?? null)
            ? $usage['prompt_tokens']
            : str_word_count($request->transcript);
        $outputUnits = is_array($usage) && is_int($usage['completion_tokens'] ?? null)
            ? $usage['completion_tokens']
            : str_word_count($reply);

        $correction = $this->mapCorrection(
            $payload['priority_correction'] ?? null,
            $request->uiLocale,
            $latencyMilliseconds,
        );

        return new ConversationModelResult(
            understoodIntent: $intent,
            assistantReply: $reply,
            providerUsage: new ProviderUsage(
                providerKey: $this->configuration->providerKey(),
                modelVersion: $this->configuration->modelVersion(),
                latencyMilliseconds: $latencyMilliseconds,
                inputUnits: $inputUnits,
                outputUnits: $outputUnits,
                estimatedCostMicrounits: $this->estimateCostMicrounits(
                    $inputUnits,
                    $outputUnits,
                ),
            ),
            priorityCorrection: $correction,
            safetyState: $safety,
            usedFallback: false,
        );
    }

    private function mapCorrection(
        mixed $raw,
        string $uiLocale,
        int $latencyMilliseconds,
    ): ?PriorityCorrection {
        if ($raw === null) {
            return null;
        }

        if (! is_array($raw)) {
            throw $this->configuration->failure(
                'provider_malformed_response',
                $latencyMilliseconds,
            );
        }

        $needed = $raw['needed'] ?? true;
        if ($needed === false) {
            return new PriorityCorrection(
                errorCategory: 'none',
                correctionEn: 'none',
                explanationL1: 'none',
                explanationLocale: $uiLocale,
                retryRecommended: false,
                needed: false,
            );
        }

        $category = $raw['error_category'] ?? null;
        $correctionEn = $raw['correction_en'] ?? null;
        $explanationL1 = $raw['explanation_l1'] ?? null;
        $explanationLocale = $raw['explanation_locale'] ?? $uiLocale;
        $retry = $raw['retry_recommended'] ?? false;

        if (
            ! is_string($category)
            || ! is_string($correctionEn)
            || ! is_string($explanationL1)
            || ! is_string($explanationLocale)
            || ! is_bool($retry)
        ) {
            throw $this->configuration->failure(
                'provider_malformed_response',
                $latencyMilliseconds,
            );
        }

        $learnerSpan = $raw['learner_span'] ?? null;
        $encouragement = $raw['encouragement_l1'] ?? null;

        return new PriorityCorrection(
            errorCategory: $category,
            correctionEn: $correctionEn,
            explanationL1: $explanationL1,
            explanationLocale: $explanationLocale,
            retryRecommended: $retry,
            needed: true,
            learnerSpan: is_string($learnerSpan) ? $learnerSpan : null,
            encouragementL1: is_string($encouragement) ? $encouragement : null,
        );
    }

    private function estimateCostMicrounits(int $inputUnits, int $outputUnits): int
    {
        $inputRate = $this->configuration->integer(
            'conversation.azure_openai.pricing.microunits_per_input_token',
        );
        $outputRate = $this->configuration->integer(
            'conversation.azure_openai.pricing.microunits_per_output_token',
        );

        return ($inputUnits * $inputRate) + ($outputUnits * $outputRate);
    }

    private function elapsedMilliseconds(int $started): int
    {
        return (int) max(0, (hrtime(true) - $started) / 1_000_000);
    }
}
