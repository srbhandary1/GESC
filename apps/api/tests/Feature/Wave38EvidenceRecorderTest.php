<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Conversation\Routing\Wave38EvidenceRecorder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class Wave38EvidenceRecorderTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        foreach (array_reverse($this->temporaryDirectories) as $path) {
            if (! is_dir($path)) {
                continue;
            }
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $child = $path.'/'.$name;
                if (is_file($child)) {
                    unlink($child);
                }
            }
            rmdir($path);
        }

        parent::tearDown();
    }

    public function test_rotation_and_deletion_receipts_do_not_call_http(): void
    {
        Http::preventStrayRequests();
        $directory = $this->temporaryDirectory();
        $this->writeJson($directory.'/elevenlabs-vendor-evidence.json', [
            'schema_version' => 1,
            'provider' => 'elevenlabs',
            'key_rotated' => false,
            'deletion_test_evidence_id' => null,
        ]);

        $recorder = $this->app->make(Wave38EvidenceRecorder::class);
        $rotation = $recorder->recordRotation($directory, '2026-09-09T15:00:00Z');
        $deletion = $recorder->recordDeletion(
            $directory,
            'el-deletion-test-2026-09-09',
            '2026-09-09T15:05:00Z',
        );

        $this->assertSame('recorded', $rotation['status']);
        $this->assertSame('recorded', $deletion['status']);
        $this->assertFileExists($directory.'/elevenlabs-rotation-receipt.json');
        $this->assertFileExists($directory.'/elevenlabs-deletion-test.json');
        $this->assertSame(0600, fileperms($directory.'/elevenlabs-rotation-receipt.json') & 0777);

        $vendor = json_decode(
            (string) file_get_contents($directory.'/elevenlabs-vendor-evidence.json'),
            true,
            16,
            JSON_THROW_ON_ERROR,
        );
        $this->assertTrue($vendor['key_rotated']);
        $this->assertSame('el-deletion-test-2026-09-09', $vendor['deletion_test_evidence_id']);
        Http::assertNothingSent();
    }

    public function test_hash_document_writes_executed_slot_and_rejects_repository_paths(): void
    {
        Http::preventStrayRequests();
        $directory = $this->temporaryDirectory();
        $this->writeJson($directory.'/sarvam-vendor-evidence.json', [
            'schema_version' => 1,
            'provider' => 'sarvam',
            'executed_dpa_present' => false,
            'dpa_sha256' => null,
        ]);
        $document = $directory.'/executed-dpa.txt';
        $this->temporaryFiles[] = $document;
        file_put_contents($document, "executed-dpa-fixture\n");
        chmod($document, 0600);

        $recorder = $this->app->make(Wave38EvidenceRecorder::class);
        $recorded = $recorder->hashDocument($directory, 'sarvam-dpa', $document, true);
        $this->assertSame('recorded', $recorded['status']);
        $this->assertSame(hash_file('sha256', $document), $recorded['sha256'] ?? null);

        $vendor = json_decode(
            (string) file_get_contents($directory.'/sarvam-vendor-evidence.json'),
            true,
            16,
            JSON_THROW_ON_ERROR,
        );
        $this->assertTrue($vendor['executed_dpa_present']);
        $this->assertSame($recorded['sha256'], $vendor['dpa_sha256']);

        $rejected = $recorder->hashDocument(
            $directory,
            'elevenlabs-dpa',
            base_path('composer.json'),
            true,
        );
        $this->assertSame('blocked', $rejected['status']);
        $this->assertContains('wave38_source_document_unusable', $rejected['safe_error_codes']);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $this->temporaryFiles[] = $path;
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n");
        chmod($path, 0600);
    }

    private function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir().'/wave38-rec-'.bin2hex(random_bytes(8));
        mkdir($path, 0700);
        $this->temporaryDirectories[] = $path;

        return $path;
    }
}
