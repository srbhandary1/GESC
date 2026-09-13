<?php

declare(strict_types=1);

namespace App\Conversation\Routing;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\TextToSpeechProvider;
use App\Conversation\Providers\TextToSpeechRequest;
use App\Conversation\Providers\TextToSpeechResult;

final readonly class PrimaryFallbackTextToSpeechProvider implements TextToSpeechProvider
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
        private TextToSpeechProvider $primary,
        private TextToSpeechProvider $fallback,
        private int $maximumDispatches = 2,
    ) {}

    public function synthesize(TextToSpeechRequest $request): TextToSpeechResult
    {
        try {
            return $this->primary->synthesize($request);
        } catch (ConversationProviderException $primary) {
            if (
                $this->maximumDispatches < 2
                || ! in_array($primary->safeCode, self::TRANSIENT_CODES, true)
            ) {
                throw $primary;
            }

            try {
                return $this->fallback->synthesize($request);
            } catch (ConversationProviderException $failover) {
                if ($failover->safeCode === 'provider_not_authorized') {
                    throw $primary;
                }

                throw $failover;
            }
        }
    }
}
