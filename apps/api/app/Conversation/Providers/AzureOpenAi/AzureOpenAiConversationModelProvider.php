<?php

declare(strict_types=1);

namespace App\Conversation\Providers\AzureOpenAi;

use App\Conversation\Orchestration\PriorityCorrection;
use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderBillableUsage;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Support\ConversationUiLocales;
use App\Conversation\Support\LessonDrillStepper;
use Illuminate\Http\Client\ConnectionException;
use JsonException;

final readonly class AzureOpenAiConversationModelProvider implements ConversationModelProvider
{
    private const string OUTPUT_CONTRACT = <<<'TEXT'
Return only one JSON object (no markdown) with keys:
understood_intent (string),
assistant_reply_en (string, English reply spoken as Swathi, no HTML, never any other personal name),
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
Speak only as Swathi, the on-screen English teacher. Do not use any other name.
TEXT;

    public function __construct(
        private AzureOpenAiConversationHttpBoundary $http,
        private AzureOpenAiConversationConfiguration $configuration,
        private AzureOpenAiConversationStreamDecoder $streamDecoder,
    ) {}

    public function respond(
        ConversationModelRequest $request,
    ): ConversationModelResult {
        $this->assertRequestContract($request);

        $started = hrtime(true);
        $maximumBytes = $this->configuration->integer(
            'conversation.azure_openai.maximum_json_response_bytes',
        );

        if ($this->configuration->streamResponses()) {
            return $this->respondStreamed(
                $request,
                $started,
                $maximumBytes,
            );
        }

        return $this->respondBuffered($request, $started, $maximumBytes);
    }

    private function respondBuffered(
        ConversationModelRequest $request,
        int $started,
        int $maximumBytes,
    ): ConversationModelResult {
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

    private function respondStreamed(
        ConversationModelRequest $request,
        int $started,
        int $maximumBytes,
    ): ConversationModelResult {
        try {
            $response = $this->http
                ->request($maximumBytes)
                ->withOptions(['stream' => true])
                ->accept('text/event-stream')
                ->asJson()
                ->post(
                    $this->configuration->chatCompletionsUrl(),
                    $this->requestPayload($request, true),
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

        $connectionLatency = $this->elapsedMilliseconds($started);
        if ($response->status() !== 200) {
            throw $this->http->fromHttpStatus(
                $response->status(),
                $connectionLatency,
            );
        }

        try {
            $stream = $this->streamDecoder->decode(
                $response->toPsrResponse()->getBody(),
                $maximumBytes,
                $started,
                $connectionLatency,
            );
        } catch (AzureOpenAiConversationStreamException $exception) {
            throw $this->configuration->failure(
                $exception->safeCode,
                $this->elapsedMilliseconds($started),
                $exception->retryable,
            );
        }

        /** @var array<string, mixed> $decoded */
        $decoded = [
            'choices' => [[
                'message' => ['content' => $stream->content],
            ]],
        ];
        if (
            $stream->promptTokens !== null
            && $stream->completionTokens !== null
        ) {
            $decoded['usage'] = [
                'prompt_tokens' => $stream->promptTokens,
                'completion_tokens' => $stream->completionTokens,
            ];
        }

        return $this->mapResult(
            $decoded,
            $request,
            $stream->completionLatencyMilliseconds,
            $stream->connectionLatencyMilliseconds,
            $stream->firstResultLatencyMilliseconds,
        );
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
    private function requestPayload(
        ConversationModelRequest $request,
        bool $stream = false,
    ): array {
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
            'lesson_drill' => $request->lessonDrill,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $drillInstruction = '';
        if ($request->lessonDrill !== []) {
            $patterns = $request->lessonDrill['target_patterns'] ?? [];
            $listed = is_array($patterns)
                ? array_values(array_map('strval', $patterns))
                : [];
            $partId = is_string($request->lessonDrill['part_id'] ?? null)
                ? (string) $request->lessonDrill['part_id']
                : 'introduction';
            if ($partId !== 'practice') {
                $partId = 'introduction';
            }
            $completedTurns = $request->lessonDrill['completed_turns'] ?? 0;
            $completedTurns = is_int($completedTurns)
                ? $completedTurns
                : (int) $completedTurns;
            $models = LessonDrillStepper::models($listed);
            $nextIndex = max(0, $completedTurns) + 1;
            $nextModel = $models[$nextIndex] ?? '';
            $currentModel = $models[$completedTurns] ?? ($models[0] ?? '');
            $style = $partId === 'practice'
                ? 'Practice transform drill: ask for the next form, then send them to the Check items. Do not open free conversation.'
                : 'Introduction one-pattern drill: one model sentence, one learner try, then the next pattern. Never list every formula. Never say Do not start a free conversation.';
            $drillInstruction = "This is a lesson grammar drill, not free conversation.\n"
                ."part_id={$partId}\n"
                ."{$style}\n"
                ."Current model sentence: {$currentModel}\n"
                ."Next model sentence to elicit: {$nextModel}\n"
                ."When you speak a pattern, say 'and' for '+' and 'Or' for '/'. Never say plus or slash.\n"
                ."Reply with one short Good and only the next model sentence (or a complete-the-session close if there is no next sentence).\n"
                ."Do not read the written formula list aloud.\n";
        }

        $payload = [
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $request->promptBody."\n\n".self::OUTPUT_CONTRACT,
                ],
                [
                    'role' => 'user',
                    'content' => $drillInstruction
                        ."Session ui_locale={$request->uiLocale}\n"
                        ."coaching_mode={$request->coachingMode}\n"
                        ."memory_facts:\n- {$memory}\n\n"
                        ."Turn payload JSON:\n{$user}",
                ],
            ],
        ];

        if ($stream) {
            $payload['stream'] = true;
            $payload['stream_options'] = ['include_usage' => true];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function mapResult(
        array $decoded,
        ConversationModelRequest $request,
        int $latencyMilliseconds,
        ?int $connectionLatencyMilliseconds = null,
        ?int $firstResultLatencyMilliseconds = null,
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
        $providerInputUnits = is_array($usage)
            && is_int($usage['prompt_tokens'] ?? null)
            && $usage['prompt_tokens'] >= 0
            ? $usage['prompt_tokens']
            : null;
        $providerOutputUnits = is_array($usage)
            && is_int($usage['completion_tokens'] ?? null)
            && $usage['completion_tokens'] >= 0
            ? $usage['completion_tokens']
            : null;
        $usageIsProviderReported = $providerInputUnits !== null
            && $providerOutputUnits !== null;
        $inputUnits = $providerInputUnits
            ?? str_word_count($request->transcript);
        $outputUnits = $providerOutputUnits ?? str_word_count($reply);

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
                estimatedCostMicrounits: $usageIsProviderReported
                    ? $this->estimateCostMicrounits(
                        $inputUnits,
                        $outputUnits,
                    )
                    : 0,
                inputUnitKind: $usageIsProviderReported
                    ? 'provider_input_tokens'
                    : 'estimated_words',
                outputUnitKind: $usageIsProviderReported
                    ? 'provider_output_tokens'
                    : 'estimated_words',
                connectionLatencyMilliseconds: $connectionLatencyMilliseconds,
                firstResultLatencyMilliseconds: $firstResultLatencyMilliseconds,
                billableComponents: $usageIsProviderReported
                    ? [
                        new ProviderBillableUsage(
                            componentKey: 'input',
                            unitKind: 'provider_input_tokens',
                            quantity: $inputUnits,
                        ),
                        new ProviderBillableUsage(
                            componentKey: 'output',
                            unitKind: 'provider_output_tokens',
                            quantity: $outputUnits,
                        ),
                    ]
                    : [],
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
            return null;
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
