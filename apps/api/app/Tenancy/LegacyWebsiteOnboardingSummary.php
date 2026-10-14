<?php

declare(strict_types=1);

namespace App\Tenancy;

final readonly class LegacyWebsiteOnboardingSummary
{
    public function __construct(
        public int $considered,
        public int $provisioned,
        public int $replayed,
        public int $credentialsImported,
        public int $credentialsReplayed,
        public int $granted,
        public int $grantSkipped,
        public int $skippedInvalid,
        public int $skippedConflict,
        public bool $dryRun,
    ) {}

    /**
     * @return array<string, int|bool>
     */
    public function toArray(): array
    {
        return [
            'considered' => $this->considered,
            'provisioned' => $this->provisioned,
            'replayed' => $this->replayed,
            'credentials_imported' => $this->credentialsImported,
            'credentials_replayed' => $this->credentialsReplayed,
            'granted' => $this->granted,
            'grant_skipped' => $this->grantSkipped,
            'skipped_invalid' => $this->skippedInvalid,
            'skipped_conflict' => $this->skippedConflict,
            'dry_run' => $this->dryRun,
        ];
    }
}
