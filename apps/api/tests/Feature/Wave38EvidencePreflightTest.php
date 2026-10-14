<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Routing\Wave38EvidencePreflight;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class Wave38EvidencePreflightTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-09-15T12:00:00Z');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        foreach (array_reverse($this->temporaryDirectories) as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }

        parent::tearDown();
    }

    public function test_unconfigured_preflight_fails_closed_without_http(): void
    {
        Http::preventStrayRequests();
        config()->set('speech_routing.wave38.status_file', '');

        $result = $this->app->make(Wave38EvidencePreflight::class)->evaluate();

        $this->assertSame('blocked', $result['status']);
        $this->assertFalse($result['checkpoint_closed']);
        $this->assertContains('wave38_status_unusable', $result['safe_error_codes']);

        $command = $this->artisan('speech-wave38:preflight');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('"status":"blocked"')
            ->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_incomplete_packet_records_assertions_and_stays_blocked(): void
    {
        Http::preventStrayRequests();
        $directory = $this->temporaryDirectory();
        $this->writeJson($directory.'/wave38-status.json', [
            'schema_version' => 1,
            'checkpoint' => 'WAVE-3.8',
            'status' => 'incomplete',
            'dispatch_authorized' => false,
            'inherited_azure' => [
                'speech_vendor_evidence' => $this->writeJson($directory.'/azure-speech-vendor-evidence.json', [
                    'schema_version' => 1,
                    'provider' => 'azure-speech',
                    'agreement_sha256' => hash('sha256', 'azure-agreement'),
                    'dpa_sha256' => hash('sha256', 'azure-dpa'),
                    'subprocessor_sha256' => hash('sha256', 'azure-sub'),
                    'reviewed_at' => '2026-08-05T19:09:38Z',
                    'valid_until' => '2026-08-19T19:09:38Z',
                ]),
            ],
        ]);
        $this->writeJson($directory.'/elevenlabs-vendor-evidence.json', [
            'schema_version' => 1,
            'provider' => 'elevenlabs',
            'products' => ['eleven_v3_conversational', 'scribe_v2'],
            'api_host' => 'api.in.residency.elevenlabs.io',
            'approved_for_learner_content' => false,
            'india_residency_asserted' => true,
            'zero_retention_asserted' => true,
            'key_rotated' => false,
            'deletion_test_evidence_id' => null,
            'agreement_sha256' => null,
            'dpa_sha256' => null,
            'subprocessor_sha256' => null,
            'reviewed_at' => '2026-09-09T14:20:42Z',
            'valid_until' => null,
        ]);
        $this->writeJson($directory.'/elevenlabs-spend-approval.json', [
            'schema_version' => 1,
            'provider' => 'elevenlabs',
            'approved' => true,
            'cap_inr' => 500,
            'reviewed_at' => '2026-09-09T14:20:42Z',
            'valid_until' => '2026-10-09T00:00:00Z',
            'content_dispatch_authorized' => false,
        ]);
        $this->writeJson($directory.'/sarvam-vendor-evidence.json', [
            'schema_version' => 1,
            'provider' => 'sarvam',
            'executed_dpa_present' => false,
            'dpa_sha256' => null,
        ]);
        $this->writeJson($directory.'/sarvam-spend-approval.json', [
            'schema_version' => 1,
            'provider' => 'sarvam',
            'approved' => true,
            'cap_inr' => 500,
            'reviewed_at' => '2026-09-09T14:20:42Z',
            'valid_until' => '2026-10-09T00:00:00Z',
            'content_dispatch_authorized' => false,
        ]);

        $result = $this->app->make(Wave38EvidencePreflight::class)
            ->evaluate($directory.'/wave38-status.json');

        $this->assertSame('blocked', $result['status']);
        $this->assertTrue($result['recorded_assertions']['india_residency_asserted']);
        $this->assertTrue($result['recorded_assertions']['zero_retention_asserted']);
        $this->assertSame(500, $result['recorded_assertions']['elevenlabs_cap_inr']);
        $this->assertSame(500, $result['recorded_assertions']['sarvam_cap_inr']);
        $this->assertContains('elevenlabs_key_not_rotated', $result['safe_error_codes']);
        $this->assertContains('elevenlabs_deletion_test_missing', $result['safe_error_codes']);
        $this->assertContains('elevenlabs_executed_dpa_missing', $result['safe_error_codes']);
        $this->assertContains('sarvam_executed_dpa_missing', $result['safe_error_codes']);
        $this->assertContains('azure_speech_vendor_evidence_expired', $result['safe_error_codes']);
        $this->assertSame([], $result['deferred_until_live_deployment']);
        Http::assertNothingSent();
    }

    public function test_live_deployment_deferral_closes_development_checkpoint_without_dispatch(): void
    {
        Http::preventStrayRequests();
        $directory = $this->temporaryDirectory();
        $this->writeJson($directory.'/wave38-status.json', [
            'schema_version' => 1,
            'checkpoint' => 'WAVE-3.8',
            'status' => 'development_closed',
            'dispatch_authorized' => false,
            'rotation_deferred_until' => 'live_deployment',
            'deletion_test_deferred_until' => 'live_deployment',
            'inherited_azure' => [
                'speech_vendor_evidence' => $this->writeJson($directory.'/azure-speech-vendor-evidence.json', [
                    'schema_version' => 1,
                    'provider' => 'azure-speech',
                    'agreement_sha256' => hash('sha256', 'azure-agreement-current'),
                    'dpa_sha256' => hash('sha256', 'azure-dpa-current'),
                    'subprocessor_sha256' => hash('sha256', 'azure-sub-current'),
                    'reviewed_at' => '2026-09-09T00:00:00Z',
                    'valid_until' => '2026-12-09T00:00:00Z',
                ]),
            ],
        ]);
        $this->writeJson($directory.'/elevenlabs-vendor-evidence.json', [
            'schema_version' => 1,
            'provider' => 'elevenlabs',
            'products' => ['eleven_v3_conversational', 'scribe_v2'],
            'api_host' => 'api.in.residency.elevenlabs.io',
            'approved_for_learner_content' => false,
            'india_residency_asserted' => true,
            'zero_retention_asserted' => true,
            'key_rotated' => false,
            'deletion_test_evidence_id' => null,
            'agreement_sha256' => hash('sha256', 'el-agreement'),
            'dpa_sha256' => hash('sha256', 'el-dpa'),
            'subprocessor_sha256' => hash('sha256', 'el-sub'),
            'reviewed_at' => '2026-09-09T00:00:00Z',
            'valid_until' => '2026-12-09T00:00:00Z',
        ]);
        $this->writeJson($directory.'/elevenlabs-spend-approval.json', [
            'schema_version' => 1,
            'provider' => 'elevenlabs',
            'approved' => true,
            'cap_inr' => 500,
            'reviewed_at' => '2026-09-09T00:00:00Z',
            'valid_until' => '2026-12-09T00:00:00Z',
            'content_dispatch_authorized' => false,
        ]);
        $this->writeJson($directory.'/sarvam-vendor-evidence.json', [
            'schema_version' => 1,
            'provider' => 'sarvam',
            'executed_dpa_present' => true,
            'dpa_sha256' => hash('sha256', 'sarvam-executed-dpa'),
        ]);
        $this->writeJson($directory.'/sarvam-spend-approval.json', [
            'schema_version' => 1,
            'provider' => 'sarvam',
            'approved' => true,
            'cap_inr' => 500,
            'reviewed_at' => '2026-09-09T00:00:00Z',
            'valid_until' => '2026-12-09T00:00:00Z',
            'content_dispatch_authorized' => false,
        ]);

        $result = $this->app->make(Wave38EvidencePreflight::class)
            ->evaluate($directory.'/wave38-status.json');

        $this->assertSame('ready', $result['status']);
        $this->assertTrue($result['checkpoint_closed']);
        $this->assertSame([], $result['safe_error_codes']);
        $this->assertSame([
            'elevenlabs_key_not_rotated',
            'elevenlabs_deletion_test_missing',
        ], $result['deferred_until_live_deployment']);
        Http::assertNothingSent();
    }

    public function test_complete_packet_is_ready_but_never_authorizes_dispatch(): void
    {
        Http::preventStrayRequests();
        $directory = $this->temporaryDirectory();
        $this->writeJson($directory.'/wave38-status.json', [
            'schema_version' => 1,
            'checkpoint' => 'WAVE-3.8',
            'status' => 'ready',
            'dispatch_authorized' => false,
            'inherited_azure' => [
                'speech_vendor_evidence' => $this->writeJson($directory.'/azure-speech-vendor-evidence.json', [
                    'schema_version' => 1,
                    'provider' => 'azure-speech',
                    'agreement_sha256' => hash('sha256', 'azure-agreement-current'),
                    'dpa_sha256' => hash('sha256', 'azure-dpa-current'),
                    'subprocessor_sha256' => hash('sha256', 'azure-sub-current'),
                    'reviewed_at' => '2026-09-09T00:00:00Z',
                    'valid_until' => '2026-12-09T00:00:00Z',
                ]),
            ],
        ]);
        $this->writeJson($directory.'/elevenlabs-vendor-evidence.json', [
            'schema_version' => 1,
            'provider' => 'elevenlabs',
            'products' => ['eleven_v3_conversational', 'scribe_v2'],
            'api_host' => 'api.in.residency.elevenlabs.io',
            'approved_for_learner_content' => false,
            'india_residency_asserted' => true,
            'zero_retention_asserted' => true,
            'key_rotated' => true,
            'deletion_test_evidence_id' => 'el-deletion-test-2026-09-09',
            'agreement_sha256' => hash('sha256', 'el-agreement'),
            'dpa_sha256' => hash('sha256', 'el-dpa'),
            'subprocessor_sha256' => hash('sha256', 'el-sub'),
            'reviewed_at' => '2026-09-09T00:00:00Z',
            'valid_until' => '2026-12-09T00:00:00Z',
        ]);
        $this->writeJson($directory.'/elevenlabs-spend-approval.json', [
            'schema_version' => 1,
            'provider' => 'elevenlabs',
            'approved' => true,
            'cap_inr' => 500,
            'reviewed_at' => '2026-09-09T00:00:00Z',
            'valid_until' => '2026-12-09T00:00:00Z',
            'content_dispatch_authorized' => false,
        ]);
        $this->writeJson($directory.'/elevenlabs-rotation-receipt.json', [
            'schema_version' => 1,
            'provider' => 'elevenlabs',
            'rotated_at' => '2026-09-09T12:00:00Z',
            'previous_key_revoked' => true,
        ]);
        $this->writeJson($directory.'/elevenlabs-deletion-test.json', [
            'schema_version' => 1,
            'provider' => 'elevenlabs',
            'evidence_id' => 'el-deletion-test-2026-09-09',
            'deleted_at' => '2026-09-09T12:05:00Z',
            'content_free' => true,
        ]);
        $this->writeJson($directory.'/sarvam-vendor-evidence.json', [
            'schema_version' => 1,
            'provider' => 'sarvam',
            'executed_dpa_present' => true,
            'dpa_sha256' => hash('sha256', 'sarvam-executed-dpa'),
        ]);
        $this->writeJson($directory.'/sarvam-spend-approval.json', [
            'schema_version' => 1,
            'provider' => 'sarvam',
            'approved' => true,
            'cap_inr' => 500,
            'reviewed_at' => '2026-09-09T00:00:00Z',
            'valid_until' => '2026-12-09T00:00:00Z',
            'content_dispatch_authorized' => false,
        ]);

        $result = $this->app->make(Wave38EvidencePreflight::class)
            ->evaluate($directory.'/wave38-status.json');
        $this->assertSame('ready', $result['status']);
        $this->assertTrue($result['checkpoint_closed']);
        $this->assertSame([], $result['safe_error_codes']);

        $command = $this->artisan(
            'speech-wave38:preflight',
            ['--status-file' => $directory.'/wave38-status.json'],
        );
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('"status":"ready"')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeJson(string $path, array $payload): string
    {
        $this->temporaryFiles[] = $path;
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n");
        chmod($path, 0600);

        return $path;
    }

    private function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir().'/wave38-'.bin2hex(random_bytes(8));
        mkdir($path, 0700);
        $this->temporaryDirectories[] = $path;

        return $path;
    }
}
