<?php

declare(strict_types=1);

namespace Tests;

use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Config\Repository;
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

        $application = parent::createApplication();
        $config = $application->make(Repository::class);

        // Keep conversation tests isolated from soft-launch developer .env.
        $application->detectEnvironment(static fn (): string => 'testing');
        $config->set([
            'conversation.provider_driver' => 'fake',
            'conversation.guidance_tts.live_requests_enabled' => false,
            'conversation.azure_openai.live_requests_enabled' => false,
            'voice.provider_driver' => 'deterministic-test',
        ]);
        (new AppServiceProvider($application))->register();

        if ($config->get('database.default') === 'pgsql') {
            $database = (string) $config->get('database.connections.pgsql.database');
            if ($database === 'global_english') {
                throw new RuntimeException(
                    'Refusing to boot tests against the live PostgreSQL database global_english.',
                );
            }

            // PostgreSQL HTTP integration tests always exercise the HTTPS
            // session contract, independently of developer-machine settings.
            $config->set([
                'session.cookie' => '__Host-global_english_session',
                'session.secure' => true,
            ]);
        }

        return $application;
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
