<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ProblemDetailsTest extends TestCase
{
    public function test_unknown_api_route_returns_problem_details(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'type' => 'urn:global-english:problem:not-found',
                'title' => 'Resource not found',
                'status' => 404,
                'message_key' => 'problem.not_found',
            ]);

        $this->assertSame(
            $response->headers->get('X-Correlation-ID'),
            $response->json('correlation_id'),
        );
        $this->assertNoStore($response->headers->get('Cache-Control'));
    }

    public function test_api_problem_contract_is_used_even_when_html_is_requested(): void
    {
        $this->withHeader('Accept', 'text/html')
            ->get('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('message_key', 'problem.not_found');
    }

    public function test_method_not_allowed_problem_preserves_allow_header(): void
    {
        $response = $this->postJson('/api/v1/health/live')
            ->assertStatus(405)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('message_key', 'problem.method_not_allowed');

        $allow = $response->headers->get('Allow');

        $this->assertIsString($allow);
        $this->assertStringContainsString('GET', $allow);
    }

    public function test_internal_exception_is_safe_even_in_debug_mode(): void
    {
        $secret = 'learner-secret-transcript';
        config()->set('app.debug', true);
        $log = Log::spy();

        Route::middleware('api')
            ->get('/api/v1/testing/internal-error', function () use ($secret): never {
                throw new RuntimeException($secret);
            })
            ->name('api.v1.testing.internal-error');

        $response = $this->getJson('/api/v1/testing/internal-error')
            ->assertStatus(500)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'type' => 'urn:global-english:problem:internal-error',
                'title' => 'Internal server error',
                'status' => 500,
                'message_key' => 'problem.internal_error',
            ]);

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertStringNotContainsString($secret, $content);
        $this->assertStringNotContainsString('RuntimeException', $content);
        $this->assertNoStore($response->headers->get('Cache-Control'));

        $log->shouldHaveReceived(
            'error',
            static function (string $message, array $context) use ($secret): bool {
                $encoded = json_encode($context);

                return $message === 'http.request_failed'
                    && is_string($encoded)
                    && ! str_contains($encoded, $secret)
                    && ! array_key_exists('exception', $context);
            },
        );
        $log->shouldNotHaveReceived(
            'error',
            static fn (string $message, array $context): bool => str_contains(
                (string) json_encode([$message, $context]),
                $secret,
            ),
        );
    }

    public function test_validation_problem_does_not_echo_input_or_framework_messages(): void
    {
        $secret = 'private learner transcript that is too long';

        Route::middleware('api')
            ->post('/api/v1/testing/validation', function (Request $request): void {
                $request->validate([
                    'transcript' => ['required', 'string', 'max:5'],
                ]);
            })
            ->name('api.v1.testing.validation');

        $response = $this->postJson('/api/v1/testing/validation', [
            'transcript' => $secret,
        ])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'type' => 'urn:global-english:problem:validation-failed',
                'status' => 422,
                'message_key' => 'problem.validation_failed',
            ]);

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertStringNotContainsString($secret, $content);
        $this->assertStringNotContainsString('transcript field', $content);
    }

    private function assertNoStore(?string $cacheControl): void
    {
        $this->assertIsString($cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }
}
