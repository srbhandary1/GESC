<?php

declare(strict_types=1);

namespace App\Conversation\Providers\AzureOpenAi;

use JsonException;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * @phpstan-type StreamState array{
 *   content: string,
 *   prompt_tokens: int|null,
 *   completion_tokens: int|null,
 *   first_result_ms: int|null,
 *   done: bool
 * }
 */
final readonly class AzureOpenAiConversationStreamDecoder
{
    public function decode(
        StreamInterface $stream,
        int $maximumBytes,
        int $startedAtNanoseconds,
        int $connectionLatencyMilliseconds,
    ): AzureOpenAiConversationStreamResult {
        if ($maximumBytes < 1 || $startedAtNanoseconds < 1) {
            throw new AzureOpenAiConversationStreamException(
                'provider_policy_failure',
            );
        }

        /** @var StreamState $state */
        $state = [
            'content' => '',
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'first_result_ms' => null,
            'done' => false,
        ];
        $buffer = '';
        $receivedBytes = 0;
        /** @var list<string> $eventData */
        $eventData = [];

        try {
            while (! $stream->eof()) {
                $chunk = $stream->read(8192);
                if ($chunk === '') {
                    throw new AzureOpenAiConversationStreamException(
                        'provider_stream_incomplete',
                        true,
                    );
                }

                $receivedBytes += strlen($chunk);
                if ($receivedBytes > $maximumBytes) {
                    throw new AzureOpenAiConversationStreamException(
                        'provider_response_too_large',
                    );
                }

                $buffer .= $chunk;
                while (($newline = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $newline), "\r");
                    $buffer = substr($buffer, $newline + 1);
                    $state = $this->consumeLine(
                        $line,
                        $eventData,
                        $state,
                        $startedAtNanoseconds,
                    );
                }
            }
        } catch (AzureOpenAiConversationStreamException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new AzureOpenAiConversationStreamException(
                'provider_stream_incomplete',
                true,
            );
        }

        if ($buffer !== '') {
            $state = $this->consumeLine(
                rtrim($buffer, "\r"),
                $eventData,
                $state,
                $startedAtNanoseconds,
            );
        }
        if ($eventData !== []) {
            $state = $this->consumeEvent(
                $eventData,
                $state,
                $startedAtNanoseconds,
            );
        }

        if (
            ! $state['done']
            || $state['content'] === ''
            || $state['first_result_ms'] === null
        ) {
            throw new AzureOpenAiConversationStreamException(
                'provider_stream_incomplete',
                true,
            );
        }

        return new AzureOpenAiConversationStreamResult(
            content: $state['content'],
            promptTokens: $state['prompt_tokens'],
            completionTokens: $state['completion_tokens'],
            connectionLatencyMilliseconds: max(
                0,
                $connectionLatencyMilliseconds,
            ),
            firstResultLatencyMilliseconds: $state['first_result_ms'],
            completionLatencyMilliseconds: $this->elapsedMilliseconds(
                $startedAtNanoseconds,
            ),
        );
    }

    /**
     * @param  list<string>  $eventData
     * @param  StreamState  $state
     * @return StreamState
     */
    private function consumeLine(
        string $line,
        array &$eventData,
        array $state,
        int $startedAtNanoseconds,
    ): array {
        if ($line === '') {
            if ($eventData === []) {
                return $state;
            }

            $state = $this->consumeEvent(
                $eventData,
                $state,
                $startedAtNanoseconds,
            );
            $eventData = [];

            return $state;
        }

        if (str_starts_with($line, ':')) {
            return $state;
        }

        if (str_starts_with($line, 'data:')) {
            $data = substr($line, 5);
            $eventData[] = str_starts_with($data, ' ')
                ? substr($data, 1)
                : $data;
        }

        return $state;
    }

    /**
     * @param  list<string>  $eventData
     * @param  StreamState  $state
     * @return StreamState
     */
    private function consumeEvent(
        array $eventData,
        array $state,
        int $startedAtNanoseconds,
    ): array {
        $data = implode("\n", $eventData);
        if ($data === '[DONE]') {
            $state['done'] = true;

            return $state;
        }
        if ($state['done']) {
            throw new AzureOpenAiConversationStreamException(
                'provider_malformed_response',
            );
        }

        try {
            $decoded = json_decode($data, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AzureOpenAiConversationStreamException(
                'provider_malformed_response',
            );
        }
        if (! is_array($decoded) || array_key_exists('error', $decoded)) {
            throw new AzureOpenAiConversationStreamException(
                'provider_malformed_response',
            );
        }

        $usage = $decoded['usage'] ?? null;
        if (is_array($usage)) {
            $promptTokens = $usage['prompt_tokens'] ?? null;
            $completionTokens = $usage['completion_tokens'] ?? null;
            if (
                is_int($promptTokens)
                && $promptTokens >= 0
                && is_int($completionTokens)
                && $completionTokens >= 0
            ) {
                $state['prompt_tokens'] = $promptTokens;
                $state['completion_tokens'] = $completionTokens;
            }
        }

        $choices = $decoded['choices'] ?? [];
        if (! is_array($choices)) {
            throw new AzureOpenAiConversationStreamException(
                'provider_malformed_response',
            );
        }
        foreach ($choices as $choice) {
            if (! is_array($choice) || ($choice['index'] ?? 0) !== 0) {
                throw new AzureOpenAiConversationStreamException(
                    'provider_malformed_response',
                );
            }
            $delta = $choice['delta'] ?? [];
            if (! is_array($delta)) {
                throw new AzureOpenAiConversationStreamException(
                    'provider_malformed_response',
                );
            }
            $content = $delta['content'] ?? null;
            if ($content === null) {
                continue;
            }
            if (! is_string($content)) {
                throw new AzureOpenAiConversationStreamException(
                    'provider_malformed_response',
                );
            }
            if ($content !== '' && $state['first_result_ms'] === null) {
                $state['first_result_ms'] = $this->elapsedMilliseconds(
                    $startedAtNanoseconds,
                );
            }
            $state['content'] .= $content;
        }

        return $state;
    }

    private function elapsedMilliseconds(int $startedAtNanoseconds): int
    {
        return (int) max(
            0,
            (hrtime(true) - $startedAtNanoseconds) / 1_000_000,
        );
    }
}
