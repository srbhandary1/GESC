<?php

declare(strict_types=1);

namespace App\Conversation\Capacity;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Throwable;

/** Shared, non-blocking pacing. Capacity reservations are separate from student billing. */
final class ConversationCapacityAdmission
{
    public function admit(string $organizationId, string $membershipId): void
    {
        if (! config('conversation.capacity.enabled')) {
            return;
        }
        $storeName = (string) config('conversation.capacity.store', 'redis');
        if ($storeName !== 'redis' && ! app()->environment('testing')) {
            throw new ConversationCapacityUnavailable(5, true);
        }
        $rpm = (int) config('conversation.capacity.model_rpm');
        $tpm = (int) config('conversation.capacity.model_tpm');
        $tokens = (int) config('conversation.capacity.reserved_tokens_per_turn');
        $learnerRpm = (int) config('conversation.capacity.learner_rpm');
        if (min($rpm, $tpm, $tokens, $learnerRpm) < 1 || $tokens > $tpm) {
            throw new ConversationCapacityUnavailable(60, true);
        }
        // All organizations and HTTP workers using this deployment share one lane.
        $resource = hash('sha256', (string) config('conversation.azure_openai.endpoint').'|'.(string) config('conversation.azure_openai.deployment'));
        $prefix = 'conversation-capacity:'.$resource;
        $student = $prefix.':student:'.hash('sha256', $organizationId.'|'.$membershipId);
        try {
            $cache = Cache::store($storeName);
            if (! $cache instanceof Repository || ! $cache->getStore() instanceof LockProvider) {
                throw new ConversationCapacityUnavailable(5, true);
            }
            $lock = $cache->getStore()->lock($prefix.':lock', 5);
            if (! $lock->get()) {
                throw new ConversationCapacityUnavailable(1);
            }
            try {
                $now = Date::now()->getTimestampMs();
                $next = max((int) $cache->get($prefix, 0), (int) $cache->get($student, 0));
                if ($next > $now) {
                    throw new ConversationCapacityUnavailable((int) ceil(($next - $now) / 1000));
                }
                // Token reservation is an explicit planning estimate, never a reported token count.
                $interval = (int) ceil(max(60000 / $rpm, 60000 * $tokens / $tpm));
                $studentInterval = (int) ceil(60000 / $learnerRpm);
                $cache->put($prefix, $now + $interval, (int) ceil($interval / 1000) + 60);
                $cache->put($student, $now + $studentInterval, (int) ceil($studentInterval / 1000) + 60);
            } finally {
                $lock->release();
            }
        } catch (ConversationCapacityUnavailable $exception) {
            throw $exception;
        } catch (Throwable) {
            // A cache outage must not bypass the vendor/spend boundary.
            throw new ConversationCapacityUnavailable(5, true);
        }
    }
}
