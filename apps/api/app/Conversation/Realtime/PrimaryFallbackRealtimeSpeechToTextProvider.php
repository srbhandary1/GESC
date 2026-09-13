<?php

declare(strict_types=1);

namespace App\Conversation\Realtime;

use App\Conversation\Providers\ConversationProviderException;

final readonly class PrimaryFallbackRealtimeSpeechToTextProvider implements RealtimeSpeechToTextProvider
{
    public function __construct(
        private RealtimeSpeechToTextProvider $primary,
        private RealtimeSpeechToTextProvider $fallback,
    ) {}

    public function open(
        RealtimeSpeechToTextRequest $request,
    ): RealtimeSpeechToTextSession {
        try {
            return $this->primary->open($request);
        } catch (ConversationProviderException $exception) {
            if ($exception->safeCode === 'provider_not_authorized') {
                throw $exception;
            }

            return $this->fallback->open($request);
        }
    }
}
