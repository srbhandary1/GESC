<?php

declare(strict_types=1);

namespace App\Conversation\Providers;

final readonly class ProviderUsage
{
    /**
     * @param  list<ProviderBillableUsage>  $billableComponents
     */
    public function __construct(
        public string $providerKey,
        public string $modelVersion,
        public int $latencyMilliseconds,
        public int $inputUnits,
        public int $outputUnits,
        public int $estimatedCostMicrounits,
        public string $inputUnitKind = 'unknown',
        public string $outputUnitKind = 'unknown',
        public ?string $billableUnitKind = null,
        public ?int $billableUnitQuantity = null,
        public string $dispatchKind = 'provider_dispatch',
        public ?string $apiVersion = null,
        public ?string $voiceKey = null,
        public ?string $locale = null,
        public ?string $region = null,
        public ?string $configurationSha256 = null,
        public ?int $connectionLatencyMilliseconds = null,
        public ?int $firstResultLatencyMilliseconds = null,
        public array $billableComponents = [],
    ) {}

    /** @return list<ProviderBillableUsage> */
    public function normalizedBillableComponents(): array
    {
        if ($this->billableComponents !== []) {
            return $this->billableComponents;
        }

        if (
            $this->billableUnitKind === null
            || $this->billableUnitQuantity === null
        ) {
            return [];
        }

        return [new ProviderBillableUsage(
            componentKey: 'primary',
            unitKind: $this->billableUnitKind,
            quantity: $this->billableUnitQuantity,
        )];
    }
}
