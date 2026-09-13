<?php

declare(strict_types=1);

namespace App\Conversation\Routing;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;

final readonly class PrimaryFallbackRecordedSpeechToTextProvider implements RecordedSpeechToTextProvider
{
    /**
     * @var list<string>
     */
    private const array TRANSIENT_CODES = [
        'provider_http_failure',
        'provider_connection_failure',
        'provider_transport_failure',
    ];

    public function __construct(
        private RecordedSpeechToTextProvider $primary,
        private RecordedSpeechToTextProvider $fallback,
        private int $maximumDispatches = 2,
    ) {}

    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        try {
            return $this->primary->transcribe($request);
        } catch (ConversationProviderException $primary) {
            if (
                $this->maximumDispatches < 2
                || ! in_array($primary->safeCode, self::TRANSIENT_CODES, true)
            ) {
                throw $primary;
            }

            try {
                return $this->fallback->transcribe($request);
            } catch (ConversationProviderException $failover) {
                if ($failover->safeCode === 'provider_not_authorized') {
                    throw $primary;
                }

                throw $failover;
            }
        }
    }
}
