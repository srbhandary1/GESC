<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Models\Membership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

final class AdminContentWorkspaceApiTest extends TenancyIntegrationTestCase
{
    private const string OTP = '864209';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new AdminContentWorkspaceOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new AdminContentWorkspaceNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_author_workspace_is_strict_replay_safe_tenant_scoped_and_previewable(): void
    {
        CarbonImmutable::setTestNow('2026-08-09T12:00:00+00:00');
        $actor = $this->signInWeb('+919300000001');
        $organization = $this->createOrganization('Content Studio Primary');
        [$membership, $context] = $this->createMembership(
            $organization,
            $actor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['curriculum.author'],
            'content-author',
        );
        $this->selectMembership($membership);
        $createBody = [
            'program_key' => 'spoken-confidence',
            'internal_name' => 'Spoken confidence',
            'version_key' => 'draft-1',
            'expected_version' => 0,
        ];

        $createKey = (string) Str::ulid();
        $created = $this->postCommand(
            '/api/v1/operator/content/programs',
            $createBody,
            $createKey,
        )
            ->assertCreated()
            ->assertJsonPath('result_type', 'curriculum_workspace')
            ->assertJsonPath('workspace_version', 1)
            ->assertJsonPath('replayed', false);
        $programId = $created->json('program_id');
        $versionId = $created->json('curriculum_version_id');
        self::assertIsString($programId);
        self::assertIsString($versionId);

        $this->postCommand(
            '/api/v1/operator/content/programs',
            $createBody,
            $createKey,
        )
            ->assertOk()
            ->assertJsonPath('curriculum_version_id', $versionId)
            ->assertJsonPath('replayed', true);
        $this->postCommand(
            '/api/v1/operator/content/programs',
            [...$createBody, 'internal_name' => 'Changed replay'],
            $createKey,
        )
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.content_workspace.idempotency_conflict',
            );
        $this->postCommand(
            '/api/v1/operator/content/programs',
            [...$createBody, 'organization_id' => $organization->getKey()],
            (string) Str::ulid(),
        )
            ->assertUnprocessable()
            ->assertJsonPath('message_key', 'problem.validation_failed');

        $this->getJson('/api/v1/operator/content/workspaces')
            ->assertOk()
            ->assertJsonPath('data.0.curriculum_version_id', $versionId)
            ->assertJsonPath('data.0.state', 'draft')
            ->assertJsonPath('data.0.workspace_version', 1)
            ->assertJsonPath('allowed_actions.0', 'create_program');
        $workspace = $this->getJson("/api/v1/operator/content/workspaces/{$versionId}")
            ->assertOk()
            ->assertJsonPath('data.curriculum_version_id', $versionId)
            ->assertJsonPath('data.graph.is_complete', false);
        self::assertContains(
            'workspace_incomplete',
            $workspace->json('data.readiness.blocker_codes'),
        );

        $documentKey = (string) Str::ulid();
        $documentBody = [
            'document_key' => 'greeting-text',
            'content_type' => 'model_text',
            'learner_visible' => true,
            'payload' => [
                'schema' => 'global-english.governed-text.v1',
                'text' => 'Hello. It is good to meet you.',
            ],
            'expected_workspace_version' => 1,
        ];
        $document = $this->postCommand(
            "/api/v1/operator/content/workspaces/{$versionId}/documents",
            $documentBody,
            $documentKey,
        )
            ->assertCreated()
            ->assertJsonPath('document_key', 'greeting-text')
            ->assertJsonPath('source_revision_number', 1)
            ->assertJsonPath('workspace_version', 2);
        $documentId = $document->json('content_document_id');
        $sourceRevisionId = $document->json('source_revision_id');
        self::assertIsString($documentId);
        self::assertIsString($sourceRevisionId);

        $graph = [
            'schema' => 'global-english.curriculum-draft-graph.v2',
            'levels' => [['key' => 'a1', 'sequence' => 1]],
            'objectives' => [[
                'key' => 'greet',
                'outcome_code' => 'CAN_GREET',
                'sequence' => 1,
                'prerequisite_keys' => [],
            ]],
            'modules' => [[
                'key' => 'introductions',
                'level_key' => 'a1',
                'sequence' => 1,
                'estimated_minutes' => 30,
            ]],
            'lessons' => [[
                'key' => 'greetings',
                'module_key' => 'introductions',
                'sequence' => 1,
                'objective_keys' => ['greet'],
            ]],
            'items' => [[
                'key' => 'greeting-model',
                'lesson_key' => 'greetings',
                'content_document_key' => 'greeting-text',
                'item_type' => 'model_text',
                'sequence' => 1,
            ]],
            'delivery_bundles' => [[
                'key' => 'greeting-practice',
                'instruction_document_key' => 'greeting-text',
                'sequence' => 1,
                'item_keys' => ['greeting-model'],
            ]],
        ];
        $saved = $this->postCommand(
            "/api/v1/operator/content/workspaces/{$versionId}/graph-revisions",
            [
                'expected_workspace_version' => 2,
                'graph' => $graph,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('workspace_version', 3)
            ->assertJsonPath('is_complete', true)
            ->assertJsonPath('blocker_codes', []);
        self::assertIsString($saved->json('graph_hash'));

        $preview = $this->getJson(
            "/api/v1/operator/content/workspaces/{$versionId}/preview?document_key=greeting-text",
        )
            ->assertOk()
            ->assertJsonPath(
                'data.english.payload.text',
                'Hello. It is good to meet you.',
            )
            ->assertJsonPath('data.english.revision_number', 1)
            ->assertJsonPath('data.kannada', null);
        $cacheControl = $preview->headers->get('Cache-Control');
        self::assertIsString($cacheControl);
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);

        $this->postCommand(
            "/api/v1/operator/content/documents/{$documentId}/english-revisions",
            [
                'expected_head_revision_id' => $sourceRevisionId,
                'expected_revision_number' => 1,
                'content_type' => 'model_text',
                'learner_visible' => true,
                'payload' => [
                    'schema' => 'global-english.governed-text.v1',
                    'text' => 'Hello. I am pleased to meet you.',
                ],
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('source_revision_number', 2);
        $this->postCommand(
            "/api/v1/operator/content/workspaces/{$versionId}/documents",
            $documentBody,
            $documentKey,
        )
            ->assertOk()
            ->assertJsonPath('content_document_id', $documentId)
            ->assertJsonPath('source_revision_id', $sourceRevisionId)
            ->assertJsonPath('source_revision_number', 1)
            ->assertJsonPath('workspace_version', 2)
            ->assertJsonPath('replayed', true);

        $submitted = $this->postCommand(
            "/api/v1/operator/content/workspaces/{$versionId}/review-submissions",
            ['expected_workspace_version' => 3],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('curriculum_version_id', $versionId)
            ->assertJsonPath('workspace_version', 4)
            ->assertJsonPath('state', 'in_review');
        self::assertIsString($submitted->json('graph_revision_id'));
        $this->postCommand(
            "/api/v1/operator/content/workspaces/{$versionId}/documents",
            [
                ...$documentBody,
                'document_key' => 'late-document',
                'expected_workspace_version' => 4,
            ],
            (string) Str::ulid(),
        )
            ->assertConflict()
            ->assertJsonPath(
                'message_key',
                'problem.content_workspace.state_conflict',
            );

        $this->postCommand(
            "/api/v1/operator/content/workspaces/{$versionId}/abandonments",
            [
                'expected_workspace_version' => 4,
                'reason_key' => 'superseded',
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('workspace_version', 5)
            ->assertJsonPath('state', 'abandoned');
        $successor = $this->postCommand(
            "/api/v1/operator/content/versions/{$versionId}/successors",
            [
                'successor_version_key' => 'draft-2',
                'expected_source_version' => 5,
            ],
            (string) Str::ulid(),
        )
            ->assertCreated()
            ->assertJsonPath('source_curriculum_version_id', $versionId)
            ->assertJsonPath('workspace_version', 1);
        $successorVersionId = $successor->json('curriculum_version_id');
        self::assertIsString($successorVersionId);
        self::assertNotSame($versionId, $successorVersionId);
        $this->getJson("/api/v1/operator/content/workspaces/{$successorVersionId}")
            ->assertOk()
            ->assertJsonPath('data.source_curriculum_version_id', $versionId)
            ->assertJsonPath('data.documents.0.document_key', 'greeting-text')
            ->assertJsonPath('data.documents.0.source_revision_number', 1);

        $foreignOrganization = $this->createOrganization('Content Studio Foreign');
        $foreignActor = $this->createUser();
        [$foreignMembership, $foreignContext] = $this->createMembership(
            $foreignOrganization,
            $foreignActor,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $foreignMembership,
            $foreignContext,
            ['curriculum.author'],
            'foreign-content-author',
        );
        $foreignVersionId = (string) Str::ulid();
        $foreignProgramId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $foreignProgramId,
            'organization_id' => $foreignOrganization->getKey(),
            'key' => 'foreign-content',
            'internal_name' => 'Foreign private content',
            'created_by_membership_id' => $foreignMembership->getKey(),
            'created_by_attribution' => 'synthetic_test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('curriculum_versions')->insert([
            'id' => $foreignVersionId,
            'organization_id' => $foreignOrganization->getKey(),
            'program_id' => $foreignProgramId,
            'key' => 'v1',
            'created_by_membership_id' => $foreignMembership->getKey(),
            'created_by_attribution' => 'synthetic_test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/operator/content/workspaces/{$foreignVersionId}")
            ->assertNotFound()
            ->assertJsonMissing(['Foreign private content']);
        $this->getJson('/api/v1/operator/content/workspaces?unexpected=1')
            ->assertUnprocessable();
    }

    private function signInWeb(string $mobileNumber): User
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        Auth::forgetGuards();

        $issued = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/requests', [
                'mobile_number' => $mobileNumber,
            ])
            ->assertAccepted();
        $challengeId = $issued->json('challenge_id');
        self::assertIsString($challengeId);
        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => $mobileNumber,
                'code' => self::OTP,
            ])
            ->assertOk();
        $cookie = $verified->getCookie((string) config('session.cookie'));
        self::assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
        $userId = $verified->json('user.id');
        self::assertIsString($userId);

        return User::query()->whereKey($userId)->sole();
    }

    private function selectMembership(Membership $membership): void
    {
        $this->withHeader('Origin', 'https://localhost')
            ->putJson('/api/v1/me/organization-context', [
                'membership_id' => $membership->getKey(),
            ])
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return TestResponse<Response>
     */
    private function postCommand(
        string $uri,
        array $body,
        string $idempotencyKey,
    ): TestResponse {
        return $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->withHeader('Origin', 'https://localhost')
            ->postJson($uri, $body);
    }
}

final class AdminContentWorkspaceOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class AdminContentWorkspaceNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(string $normalizedMobileNumber, string $code): void {}
}
