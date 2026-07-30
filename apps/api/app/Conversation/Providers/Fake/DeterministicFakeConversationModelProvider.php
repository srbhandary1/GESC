<?php

declare(strict_types=1);

namespace App\Conversation\Providers\Fake;

use App\Conversation\Providers\ConversationModelProvider;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Providers\ProviderUsage;

final class DeterministicFakeConversationModelProvider implements ConversationModelProvider
{
    public function respond(
        ConversationModelRequest $request,
    ): ConversationModelResult {
        if (
            $request->personaStyleCode !== 'patient-friend-v1'
            || $request->scenarioResponseCode
                !== 'friend.everyday.follow-up.v1'
            || $request->coachingMode !== 'gentle'
        ) {
            throw new ConversationProviderException(
                safeCode: 'fake_model_contract_mismatch',
                providerKey: 'deterministic-fake',
                modelVersion: 'fake-conversation-v1',
            );
        }

        $reply = 'Thank you for sharing that. What would you like to talk about next?';

        return new ConversationModelResult(
            understoodIntent: 'prototype_utterance_received',
            assistantReply: $reply,
            providerUsage: new ProviderUsage(
                providerKey: 'deterministic-fake',
                modelVersion: 'fake-conversation-v1',
                latencyMilliseconds: 7,
                inputUnits: str_word_count($request->transcript),
                outputUnits: str_word_count($reply),
                estimatedCostMicrounits: 0,
            ),
        );
    }
}
