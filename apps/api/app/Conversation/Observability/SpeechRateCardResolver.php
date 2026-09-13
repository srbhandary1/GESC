<?php

declare(strict_types=1);

namespace App\Conversation\Observability;

use App\Conversation\Providers\ProviderBillableUsage;
use App\Conversation\Providers\ProviderUsage;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class SpeechRateCardResolver
{
    public function quote(
        string $stage,
        ProviderUsage $usage,
        DateTimeInterface $at,
    ): ?SpeechRateQuote {
        $components = $usage->normalizedBillableComponents();
        if (count($components) !== 1) {
            return null;
        }

        return $this->quoteComponent($stage, $usage, $components[0], $at);
    }

    public function quoteComponent(
        string $stage,
        ProviderUsage $usage,
        ProviderBillableUsage $component,
        DateTimeInterface $at,
    ): ?SpeechRateQuote {
        if (
            $component->quantity < 0
            || $usage->dispatchKind !== 'provider_dispatch'
        ) {
            return null;
        }

        $rows = $this->matchingIdentityQuery($stage, $usage, $component)
            ->where('tier_start_units', '<=', $component->quantity)
            ->where(function ($query) use ($component): void {
                $query->whereNull('tier_end_units')
                    ->orWhere(
                        'tier_end_units',
                        '>',
                        $component->quantity,
                    );
            })
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $at);
            })
            ->orderByDesc('tier_start_units')
            ->orderByDesc('effective_from')
            ->limit(2)
            ->get();

        if ($rows->count() !== 1) {
            return null;
        }

        $row = $rows->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        $quantity = max(
            (int) $row->minimum_billable_units,
            $component->quantity,
        );
        $basis = max(1, (int) $row->unit_basis);
        $gross = $this->ceilRatio(
            $quantity * (int) $row->gross_price_microunits,
            $basis,
        );
        $tax = $this->ceilRatio(
            $gross * (int) $row->tax_basis_points,
            10_000,
        );
        $discount = $this->ceilRatio(
            $gross * (int) $row->discount_basis_points,
            10_000,
        );
        $credit = $this->ceilRatio(
            $quantity * (int) $row->credit_microunits_per_basis,
            $basis,
        );
        $native = max(0, $gross + $tax - $discount - $credit);
        $inr = $this->ceilRatio(
            $native * (int) $row->inr_per_native_unit_microunits,
            1_000_000,
        );

        return new SpeechRateQuote(
            rateCardId: (string) $row->id,
            nativeCurrency: (string) $row->billing_currency,
            nativeCostMicrounits: $native,
            inrCostMicrounits: $inr,
        );
    }

    public function componentResolutionStatus(
        string $stage,
        ProviderUsage $usage,
        ProviderBillableUsage $component,
        DateTimeInterface $at,
    ): string {
        if ($usage->dispatchKind !== 'provider_dispatch') {
            return 'not_billable';
        }
        if ($component->quantity < 0) {
            return 'usage_unclassified';
        }

        $activeCount = $this->matchingIdentityQuery($stage, $usage, $component)
            ->where('tier_start_units', '<=', $component->quantity)
            ->where(function ($query) use ($component): void {
                $query->whereNull('tier_end_units')
                    ->orWhere('tier_end_units', '>', $component->quantity);
            })
            ->where('effective_from', '<=', $at)
            ->where(function ($query) use ($at): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>', $at);
            })
            ->limit(2)
            ->count();

        if ($activeCount > 1) {
            return 'rate_ambiguous';
        }
        if ($activeCount === 1) {
            return 'unreconciled';
        }

        $hasApplicableCard = $this->matchingIdentityQuery(
            $stage,
            $usage,
            $component,
        )
            ->where('tier_start_units', '<=', $component->quantity)
            ->where(function ($query) use ($component): void {
                $query->whereNull('tier_end_units')
                    ->orWhere('tier_end_units', '>', $component->quantity);
            })
            ->exists();

        return $hasApplicableCard ? 'rate_stale' : 'rate_missing';
    }

    public function unresolvedStatus(
        string $stage,
        ProviderUsage $usage,
        ?DateTimeInterface $at = null,
    ): string {
        if ($usage->dispatchKind !== 'provider_dispatch') {
            return 'not_billable';
        }
        $components = $usage->normalizedBillableComponents();
        if ($components === []) {
            return 'usage_unclassified';
        }

        $statuses = array_values(array_unique(array_map(
            fn (ProviderBillableUsage $component): string => $this
                ->componentResolutionStatus(
                    $stage,
                    $usage,
                    $component,
                    $at ?? new \DateTimeImmutable('now'),
                ),
            $components,
        )));

        return count($statuses) === 1 ? $statuses[0] : 'partially_rated';
    }

    private function matchingIdentityQuery(
        string $stage,
        ProviderUsage $usage,
        ProviderBillableUsage $component,
    ): Builder {
        $query = DB::table('speech_rate_cards')
            ->where('provider_key', $usage->providerKey)
            ->where('provider_stage', $stage)
            ->where('model_version', $usage->modelVersion)
            ->where('billable_unit_kind', $component->unitKind);

        foreach ([
            'api_version' => $usage->apiVersion,
            'voice_key' => $usage->voiceKey,
            'region' => $usage->region,
            'configuration_sha256' => $usage->configurationSha256,
        ] as $column => $value) {
            $query->where(function ($identity) use ($column, $value): void {
                $identity->whereNull($column);
                if ($value !== null) {
                    $identity->orWhere($column, $value);
                }
            });
        }

        return $query;
    }

    private function ceilRatio(int $numerator, int $denominator): int
    {
        if ($numerator <= 0) {
            return 0;
        }

        return intdiv($numerator + $denominator - 1, $denominator);
    }
}
