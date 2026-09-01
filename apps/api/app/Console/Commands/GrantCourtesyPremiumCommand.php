<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Entitlements\CourtesyPremiumGrantService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class GrantCourtesyPremiumCommand extends Command
{
    protected $signature = 'entitlements:grant-courtesy-premium
        {--dry-run : Count Direct learners without writing grants}
        {--csv= : Absolute path to a username CSV with a username header}';

    protected $description = 'Grant one complimentary Premium month to current Direct learners.';

    public function __construct(private readonly CourtesyPremiumGrantService $courtesy)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $organizationId = $this->courtesy->directOrganizationId();

        if ($organizationId === null) {
            $this->error('Global English Direct is not available.');

            return self::FAILURE;
        }

        try {
            $usernames = $this->usernames();
            $summary = $this->courtesy->grantForOrganization(
                $organizationId,
                null,
                (bool) $this->option('dry-run'),
                $usernames,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            '%s %d Direct learners: granted %d, skipped %d, unmatched usernames %d.',
            (bool) $this->option('dry-run') ? 'Would process' : 'Processed',
            $summary->considered,
            $summary->granted,
            $summary->skipped,
            $summary->unmatchedUsernames,
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<string>|null
     */
    private function usernames(): ?array
    {
        $path = $this->option('csv');

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('The username CSV could not be read.');
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('The username CSV could not be read.');
        }

        $usernames = [];
        $first = true;

        try {
            while (($row = fgetcsv($handle, 4096, ',', '"', '\\')) !== false) {
                $value = isset($row[0]) ? trim($row[0]) : '';

                if ($value === '') {
                    continue;
                }

                if ($first && strtolower($value) === 'username') {
                    $first = false;

                    continue;
                }

                $first = false;
                $usernames[] = $value;
            }
        } finally {
            fclose($handle);
        }

        return $usernames;
    }
}
