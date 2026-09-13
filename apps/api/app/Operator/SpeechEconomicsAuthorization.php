<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Operator\Exceptions\SpeechEconomicsRejected;
use App\Tenancy\OrganizationAuthorization;
use Illuminate\Contracts\Config\Repository;

final readonly class SpeechEconomicsAuthorization
{
    public const string USAGE_READ = 'speech.economics.usage';

    public function __construct(
        private OrganizationAuthorization $authorization,
        private Repository $config,
    ) {}

    public function canReadUsage(User $actor): bool
    {
        return $this->authorization->allows($actor, self::USAGE_READ);
    }

    public function assertUsage(User $actor): void
    {
        if (! $this->canReadUsage($actor)) {
            throw new SpeechEconomicsRejected;
        }
    }

    public function canReadCommercial(User $actor): bool
    {
        $allowed = $this->config->get('platform.speech_economics.commercial_actor_user_ids', []);
        if (! is_array($allowed)) {
            return false;
        }

        $actorId = (string) $actor->getKey();

        foreach ($allowed as $candidate) {
            if (is_string($candidate) && hash_equals($candidate, $actorId)) {
                return true;
            }
        }

        return false;
    }

    public function assertCommercial(User $actor): void
    {
        if (! $this->canReadCommercial($actor)) {
            throw new SpeechEconomicsRejected;
        }
    }
}
