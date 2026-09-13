<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Providers\ConversationProviderException;
use App\Conversation\Realtime\DeterministicFakeRealtimeSpeechToTextProvider;
use App\Conversation\Realtime\PrimaryFallbackRealtimeSpeechToTextProvider;
use App\Conversation\Realtime\RealtimeSpeechBenchmarkRunner;
use App\Conversation\Realtime\RealtimeSpeechToTextProvider;
use App\Conversation\Realtime\RealtimeSpeechToTextRequest;
use App\Conversation\Realtime\RealtimeSpeechToTextSession;
use App\Conversation\Realtime\SarvamSaarasRealtimeSpeechToTextProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SpeechRealtimeBenchmarkTest extends TestCase
{
    public function test_synthetic_realtime_benchmark_stays_offline_and_advisory(): void
    {
        Http::preventStrayRequests();
        $report = $this->app->make(RealtimeSpeechBenchmarkRunner::class)->run();

        $this->assertSame('completed', $report['status']);
        $this->assertFalse($report['http_sent']);
        $this->assertSame('denied', $report['live_dispatch']);
        $this->assertSame('recorded_upload', $report['recovery_route']);
        $this->assertFalse($report['always_on']);
        $this->assertFalse($report['full_duplex']);
        $this->assertTrue($report['authoritative_turn_required']);
        $this->assertTrue($report['transcripts_match']);
        $this->assertTrue($report['duplicate_finalize_suppressed']);
        $this->assertTrue($report['vendor_candidates_fail_closed']);
        $this->assertSame('first_result', $report['first_result_emitted']);
        $this->assertContains(
            'http_enablement_not_authorized',
            $report['sarvam_denial'],
        );
        Http::assertNothingSent();
    }

    public function test_artisan_realtime_benchmark_exits_zero_without_http(): void
    {
        Http::preventStrayRequests();
        $this->assertSame(0, Artisan::call('speech:realtime-benchmark'));
        Http::assertNothingSent();
    }

    public function test_live_realtime_router_does_not_fallback_on_authorization_failure(): void
    {
        $primary = $this->app->make(SarvamSaarasRealtimeSpeechToTextProvider::class);
        $fallback = new class implements RealtimeSpeechToTextProvider
        {
            public int $calls = 0;

            public function open(
                RealtimeSpeechToTextRequest $request,
            ): RealtimeSpeechToTextSession {
                $this->calls++;

                return (new DeterministicFakeRealtimeSpeechToTextProvider)
                    ->open($request);
            }
        };

        try {
            (new PrimaryFallbackRealtimeSpeechToTextProvider($primary, $fallback))
                ->open(new RealtimeSpeechToTextRequest(
                    interactionId: '01J00000000000000000000001',
                ));
            self::fail('Authorization failures must not open a realtime fallback.');
        } catch (ConversationProviderException $exception) {
            $this->assertSame('provider_not_authorized', $exception->safeCode);
        }

        $this->assertSame(0, $fallback->calls);
    }
}
