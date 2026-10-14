<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tenancy\LegacyWebsiteLearnerOnboarder;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use Throwable;

final class OnboardLegacyWebsiteLearnersCommand extends Command
{
    protected $signature = 'learners:onboard-legacy-website
        {--manifest= : Absolute path to the outside-repository learner manifest}
        {--dry-run : Count valid rows without writing}';

    protected $description = 'Onboard previous-website students as Direct basic-tier learners using their existing phone and password hashes.';

    public function __construct(private readonly LegacyWebsiteLearnerOnboarder $onboarder)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = $this->option('manifest');

        if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path)) {
            $this->error('The learner manifest could not be read.');

            return self::FAILURE;
        }

        $real = realpath($path);

        if (! is_string($real) || str_contains($real, DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR)) {
            $this->error('The learner manifest must live outside Git.');

            return self::FAILURE;
        }

        try {
            $decoded = json_decode(
                (string) file_get_contents($real),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            $this->error('The learner manifest is not valid JSON.');

            return self::FAILURE;
        }

        $learners = $decoded['learners'] ?? null;

        if (! is_array($learners)) {
            $this->error('The learner manifest must contain a learners array.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($learners as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('The learner manifest contains a non-object row.');
            }

            $rows[] = $row;
        }

        try {
            $summary = $this->onboarder->onboard(
                $rows,
                (bool) $this->option('dry-run'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($summary->toArray(), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
