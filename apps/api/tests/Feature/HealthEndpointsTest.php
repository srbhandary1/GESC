<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Health\ReadinessChecker;
use App\Health\ReadinessProbe;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class HealthEndpointsTest extends TestCase
{
    public function test_versioned_liveness_is_available_without_resolving_dependencies(): void
    {
        $this->app->bind(
            ReadinessChecker::class,
            fn (): never => throw new RuntimeException('Liveness touched readiness.'),
        );

        $response = $this->getJson('/api/v1/health/live');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'global-english-api',
            ])
            ->assertJsonStructure([
                'status',
                'service',
                'checked_at',
                'correlation_id',
            ]);

        $correlationId = $response->headers->get('X-Correlation-ID');

        $this->assertIsString($correlationId);
        $this->assertTrue(Str::isUuid($correlationId));
        $this->assertSame($correlationId, $response->json('correlation_id'));
        $this->assertNoStore($response->headers->get('Cache-Control'));
    }

    public function test_valid_correlation_uuid_is_returned_unchanged(): void
    {
        $correlationId = '4f55cc15-cd9f-43b3-bb95-b85176107a42';

        $this->withHeader('X-Correlation-ID', $correlationId)
            ->getJson('/api/v1/health/live')
            ->assertOk()
            ->assertHeader('X-Correlation-ID', $correlationId)
            ->assertJsonPath('correlation_id', $correlationId);
    }

    public function test_invalid_correlation_value_is_replaced(): void
    {
        $response = $this->withHeader('X-Correlation-ID', 'not-a-uuid')
            ->getJson('/api/v1/health/live')
            ->assertOk();

        $correlationId = $response->headers->get('X-Correlation-ID');

        $this->assertIsString($correlationId);
        $this->assertNotSame('not-a-uuid', $correlationId);
        $this->assertTrue(Str::isUuid($correlationId));
    }

    public function test_sequential_requests_receive_distinct_generated_ids(): void
    {
        $first = $this->getJson('/api/v1/health/live')
            ->headers->get('X-Correlation-ID');
        $second = $this->getJson('/api/v1/health/live')
            ->headers->get('X-Correlation-ID');

        $this->assertIsString($first);
        $this->assertIsString($second);
        $this->assertNotSame($first, $second);
    }

    public function test_readiness_succeeds_when_every_required_probe_succeeds(): void
    {
        $this->useReadinessProbe(new class implements ReadinessProbe
        {
            public function name(): string
            {
                return 'test';
            }

            public function isReady(): bool
            {
                return true;
            }
        });

        $response = $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJson([
                'status' => 'ready',
                'service' => 'global-english-api',
            ])
            ->assertJsonStructure([
                'status',
                'service',
                'checked_at',
                'correlation_id',
            ]);

        $this->assertNoStore($response->headers->get('Cache-Control'));
    }

    public function test_unavailable_dependency_returns_safe_problem_without_docker(): void
    {
        $secret = 'postgres://operator:super-secret@private-host/learners';
        $log = Log::spy();

        $this->useReadinessProbe(new class($secret) implements ReadinessProbe
        {
            public function __construct(private readonly string $secret) {}

            public function name(): string
            {
                return 'database';
            }

            public function isReady(): bool
            {
                throw new RuntimeException($this->secret);
            }
        });

        $response = $this->getJson('/api/v1/health/ready')
            ->assertStatus(503)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('Retry-After', '5')
            ->assertJson([
                'type' => 'urn:global-english:problem:service-unavailable',
                'title' => 'Service unavailable',
                'status' => 503,
                'message_key' => 'problem.service_unavailable',
            ])
            ->assertJsonStructure([
                'type',
                'title',
                'status',
                'detail',
                'instance',
                'message_key',
                'correlation_id',
            ]);

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertStringNotContainsString($secret, $content);
        $this->assertStringNotContainsString('database', $content);
        $this->assertNoStore($response->headers->get('Cache-Control'));
        $this->assertSame(
            $response->headers->get('X-Correlation-ID'),
            $response->json('correlation_id'),
        );

        $log->shouldHaveReceived(
            'warning',
            static function (string $message, array $context) use ($secret): bool {
                if ($message !== 'health.readiness_probe_failed') {
                    return false;
                }

                $encoded = json_encode($context);

                return is_string($encoded)
                    && ! str_contains($encoded, $secret)
                    && ! array_key_exists('exception', $context);
            },
        );
        $log->shouldNotHaveReceived(
            'warning',
            static fn (string $message, array $context): bool => str_contains(
                (string) json_encode([$message, $context]),
                $secret,
            ),
        );
    }

    public function test_request_log_uses_an_allowlist_and_omits_query_values(): void
    {
        $secret = 'private-phone-value';
        $log = Log::spy();

        $this->getJson('/api/v1/health/live?phone='.$secret)->assertOk();

        $log->shouldHaveReceived(
            'info',
            static function (string $message, array $context) use ($secret): bool {
                if ($message !== 'http.request_completed') {
                    return false;
                }

                $encoded = json_encode($context);

                return is_string($encoded)
                    && ! str_contains($encoded, $secret)
                    && array_keys($context) === [
                        'http_method',
                        'route_name',
                        'status_code',
                        'duration_ms',
                    ];
            },
        );
    }

    private function useReadinessProbe(ReadinessProbe $probe): void
    {
        $this->app->instance(
            ReadinessChecker::class,
            new ReadinessChecker($probe),
        );
    }

    private function assertNoStore(?string $cacheControl): void
    {
        $this->assertIsString($cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }
}
