<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create the isolated test storage tree before Laravel resolves paths that
     * require an existing directory, including the compiled Blade view path.
     */
    public function createApplication(): Application
    {
        $this->ensureIsolatedStorageTreeExists();

        return parent::createApplication();
    }

    private function ensureIsolatedStorageTreeExists(): void
    {
        $storagePath = getenv('LARAVEL_STORAGE_PATH');

        if (! is_string($storagePath) || trim($storagePath) === '') {
            return;
        }

        $storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);
        $directories = [
            'app/private',
            'app/public',
            'app/media-quarantine',
            'framework/cache/data',
            'framework/sessions',
            'framework/testing',
            'framework/views',
            'logs',
        ];

        foreach ($directories as $relativeDirectory) {
            $directory = $storagePath.DIRECTORY_SEPARATOR.$relativeDirectory;

            if (is_dir($directory)) {
                continue;
            }

            if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException(sprintf(
                    'Unable to create isolated test storage directory: %s.',
                    $directory,
                ));
            }
        }
    }
}
