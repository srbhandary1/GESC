<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Providers\Indic;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Voice\Providers\RecordedSpeechToTextProvider;
use App\Conversation\Voice\Providers\RecordedSpeechToTextRequest;
use App\Conversation\Voice\Providers\RecordedSpeechToTextResult;

/**
 * One Azure Fast Transcription retry after a transient Indic HTTP failure.
 * Live Speak stays on Azure until VOICE_PROVIDER_DRIVER=indic (Wave 6).
 */
final readonly class IndicWithAzureFailoverSpeechToTextProvider implements RecordedSpeechToTextProvider
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
        private RecordedSpeechToTextProvider $failover,
    ) {}

    public function transcribe(
        RecordedSpeechToTextRequest $request,
    ): RecordedSpeechToTextResult {
        try {
            return $this->primary->transcribe($request);
        } catch (ConversationProviderException $primary) {
            if (! in_array($primary->safeCode, self::TRANSIENT_CODES, true)) {
                throw $primary;
            }

            try {
                return $this->failover->transcribe($request);
            } catch (ConversationProviderException $failover) {
                if ($failover->safeCode === 'provider_not_authorized') {
                    throw $primary;
                }

                throw $failover;
            }
        }
    }
}
