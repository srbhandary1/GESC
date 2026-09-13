<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Authentication\Otp\OtpCodeGenerator;
use App\Authentication\Otp\OtpDeliveryAdapter;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SpeechEconomicsApiTest extends TenancyIntegrationTestCase
{
    private const string OTP = '246813';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            OtpCodeGenerator::class,
            new SpeechEconomicsOtpCodeGenerator(self::OTP),
        );
        $this->app->instance(
            OtpDeliveryAdapter::class,
            new SpeechEconomicsNullOtpDeliveryAdapter,
        );
        $this->withServerVariables(['HTTPS' => 'on']);
        $this->withCredentials();
    }

    public function test_usage_is_tenant_scoped_and_commercial_terms_stay_platform_only(): void
    {
        $user = $this->signInWeb('+919876543301');
        $organization = $this->createOrganization('Speech Econ Primary');
        $foreign = $this->createOrganization('Speech Econ Foreign');
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        [$foreignMembership, $foreignContext] = $this->createMembership(
            $foreign,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['speech.economics.usage'],
            'speech-econ-primary',
        );
        $this->grantTestPermissions(
            $foreignMembership,
            $foreignContext,
            ['speech.economics.usage'],
            'speech-econ-foreign',
        );

        $this->insertAggregate($organization, 'azure-speech', false, 12);
        $this->insertAggregate($organization, 'suppressed-speech', true, 2);
        $this->insertAggregate($foreign, 'foreign-speech', false, 20);
        $this->insertRateCard();

        $this->selectMembership($membership);
        $overview = $this->getJson('/api/v1/operator/overview')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertContains('operations', $overview->json('navigation'));
        $this->assertContains('speech_economics', $overview->json('panels'));

        $usage = $this->getJson('/api/v1/operator/speech-economics')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->json();
        $this->assertIsArray($usage);
        $this->assertSame($organization->getKey(), $usage['organization_id']);
        $this->assertTrue($usage['partial']);
        $this->assertSame(1, $usage['suppressed_row_count']);
        $this->assertSame(1, $usage['visible_row_count']);
        $this->assertSame(12, $usage['totals']['attempt_count']);
        $providerKeys = array_column($usage['rows'], 'provider_key');
        $this->assertContains('azure-speech', $providerKeys);
        $this->assertContains('suppressed-speech', $providerKeys);
        $this->assertNotContains('foreign-speech', $providerKeys);
        $this->assertArrayNotHasKey('gross_price_microunits', $usage);
        $this->assertArrayNotHasKey('rate_cards', $usage);

        $rows = $usage['rows'] ?? null;
        $this->assertIsArray($rows);
        $suppressed = collect($rows)->firstWhere(
            'provider_key',
            'suppressed-speech',
        );
        $this->assertIsArray($suppressed);
        $this->assertTrue($suppressed['small_cell_suppressed']);
        $this->assertNull($suppressed['attempt_count']);
        $this->assertNull($suppressed['allocated_inr_microunits']);

        $export = $this->get('/api/v1/operator/speech-economics/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('no-store', (string) $export->headers->get('Cache-Control'));
        $csv = $export->streamedContent();
        $this->assertStringContainsString('azure-speech', $csv);
        $this->assertStringNotContainsString('foreign-speech', $csv);
        $this->assertStringNotContainsString('gross_price', $csv);
        $this->assertSame(1, DB::table('audit_events')->where([
            'action' => 'speech.economics.export',
            'organization_id' => $organization->getKey(),
        ])->count());

        $this->getJson('/api/v1/operator/speech-economics/commercial')
            ->assertNotFound();

        config()->set(
            'platform.speech_economics.commercial_actor_user_ids',
            [$user->getKey()],
        );
        $commercial = $this->getJson('/api/v1/operator/speech-economics/commercial')
            ->assertOk()
            ->json();
        $this->assertFalse($commercial['empty']);
        $this->assertSame('USD', $commercial['rate_cards'][0]['billing_currency']);
        $this->assertSame(20_000, $commercial['rate_cards'][0]['gross_price_microunits']);
        $this->assertSame(1, DB::table('audit_events')->where([
            'action' => 'speech.economics.commercial.read',
            'organization_id' => $organization->getKey(),
        ])->count());

        $ignoredOrg = $this->getJson(
            '/api/v1/operator/speech-economics?organization_id='.$foreign->getKey(),
        )
            ->assertOk()
            ->json();
        $this->assertSame($organization->getKey(), $ignoredOrg['organization_id']);
        $this->assertNotContains(
            'foreign-speech',
            array_column($ignoredOrg['rows'], 'provider_key'),
        );

        $this->selectMembership($foreignMembership);
        $foreignUsage = $this->getJson('/api/v1/operator/speech-economics')
            ->assertOk()
            ->json();
        $this->assertSame($foreign->getKey(), $foreignUsage['organization_id']);
        $this->assertSame(['foreign-speech'], array_column($foreignUsage['rows'], 'provider_key'));
    }

    public function test_missing_usage_permission_is_not_found(): void
    {
        $user = $this->signInWeb('+919876543302');
        $organization = $this->createOrganization('Speech Econ Denied');
        [$membership, $context] = $this->createMembership(
            $organization,
            $user,
            Membership::TYPE_ORGANIZATION_ADMIN,
        );
        $this->grantTestPermissions(
            $membership,
            $context,
            ['support.diagnostics.read'],
            'speech-econ-denied',
        );
        $this->selectMembership($membership);

        $this->getJson('/api/v1/operator/speech-economics')->assertNotFound();
        $this->get('/api/v1/operator/speech-economics/export')->assertNotFound();
        $this->getJson('/api/v1/operator/overview')
            ->assertOk()
            ->assertJsonMissing(['speech_economics']);
    }

    public function test_named_platform_actor_can_record_immutable_commercial_evidence_with_safe_replay(): void
    {
        $user = $this->signInWeb('+919876543303');
        $organization = $this->createOrganization('Speech Econ Evidence');
        [$membership, $context] = $this->createMembership($organization, $user, Membership::TYPE_ORGANIZATION_ADMIN);
        $this->grantTestPermissions($membership, $context, ['speech.economics.usage'], 'speech-econ-evidence');
        $this->selectMembership($membership);

        $rateCard = $this->rateCardPayload();
        $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/operator/speech-economics/commercial/rate-cards', $rateCard)
            ->assertNotFound();

        config()->set('platform.speech_economics.commercial_actor_user_ids', [$user->getKey()]);
        $created = $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/operator/speech-economics/commercial/rate-cards', $rateCard)
            ->assertCreated()
            ->assertJson(['replayed' => false]);
        $rateCardId = $created->json('rate_card_id');
        $this->assertIsString($rateCardId);

        $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/operator/speech-economics/commercial/rate-cards', $rateCard)
            ->assertOk()
            ->assertJson(['rate_card_id' => $rateCardId, 'replayed' => true]);
        $conflict = $rateCard;
        $conflict['gross_price_microunits'] = 20_001;
        $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/operator/speech-economics/commercial/rate-cards', $conflict)
            ->assertUnprocessable();

        $statement = $this->statementPayload();
        $createdStatement = $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/operator/speech-economics/commercial/statements', $statement)
            ->assertCreated()
            ->assertJson(['replayed' => false, 'reconciliations' => 1]);
        $statementId = $createdStatement->json('statement_id');
        $this->assertIsString($statementId);
        $this->withHeader('Idempotency-Key', (string) Str::ulid())
            ->postJson('/api/v1/operator/speech-economics/commercial/statements', $statement)
            ->assertOk()
            ->assertJson(['statement_id' => $statementId, 'replayed' => true]);

        $this->assertSame(2, DB::table('audit_events')->where('action', 'speech.economics.rate_card.recorded')->count());
        $this->assertSame(2, DB::table('audit_events')->where('action', 'speech.economics.statement.recorded')->count());
        $this->assertSame(1, DB::table('speech_rate_cards')->where('id', $rateCardId)->count());
        $this->assertSame(1, DB::table('speech_provider_statements')->where('id', $statementId)->count());
    }

    /** @return array<string, int|string|null> */
    private function rateCardPayload(): array
    {
        return [
            'provider_key' => 'synthetic-speech', 'provider_stage' => 'tts', 'model_version' => 'synthetic-v1',
            'api_version' => 'test', 'voice_key' => 'test-voice', 'region' => 'test-region', 'configuration_sha256' => str_repeat('a', 64),
            'rate_version' => 'synthetic-api-v1', 'billable_unit_kind' => 'unicode_characters', 'unit_basis' => 1000,
            'minimum_billable_units' => 0, 'tier_start_units' => 0, 'tier_end_units' => null, 'billing_currency' => 'USD',
            'gross_price_microunits' => 20_000, 'tax_basis_points' => 0, 'discount_basis_points' => 0,
            'credit_microunits_per_basis' => 0, 'inr_per_native_unit_microunits' => 83_000_000,
            'conversion_source' => 'synthetic-test-fixture', 'conversion_observed_at' => '2026-09-09T00:00:00+00:00',
            'source_evidence_sha256' => str_repeat('b', 64), 'approval_evidence' => 'APP-TEST-SPEECH-OBS',
            'effective_from' => '2026-09-09T00:00:00+00:00', 'effective_until' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function statementPayload(): array
    {
        return [
            'provider_key' => 'synthetic-speech', 'statement_reference_sha256' => str_repeat('c', 64),
            'period_start' => '2026-09-01', 'period_end' => '2026-09-09', 'billing_currency' => 'USD',
            'gross_cost_microunits' => 1000, 'tax_microunits' => 0, 'discount_microunits' => 0, 'credit_microunits' => 0,
            'net_cost_microunits' => 1000, 'inr_per_native_unit_microunits' => 83_000_000,
            'conversion_source' => 'synthetic-test-fixture', 'conversion_observed_at' => '2026-09-09T00:00:00+00:00',
            'evidence_sha256' => str_repeat('d', 64), 'approval_evidence' => 'APP-TEST-SPEECH-OBS',
            'lines' => [[
                'provider_stage' => 'tts', 'model_version' => 'synthetic-v1', 'billable_unit_kind' => 'unicode_characters',
                'observed_billable_quantity' => 1000, 'estimated_inr_cost_microunits' => 83_000,
                'allocated_statement_inr_microunits' => 83_000, 'evidence_sha256' => str_repeat('e', 64),
                'approval_evidence' => 'APP-TEST-SPEECH-OBS',
            ]],
        ];
    }

    private function insertAggregate(
        Organization $organization,
        string $providerKey,
        bool $suppressed,
        int $attempts,
    ): void {
        $empty = [
            'le_100' => 0,
            'le_250' => 0,
            'le_500' => 0,
            'le_1000' => $suppressed ? 0 : $attempts,
            'le_2000' => 0,
            'le_4000' => 0,
            'le_8000' => 0,
            'le_16000' => 0,
            'over_16000' => 0,
        ];
        $now = now();
        DB::table('speech_usage_daily_aggregates')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->getKey(),
            'usage_date' => '2026-09-08',
            'environment' => 'soft-launch',
            'aggregate_kind' => 'provider_attempt',
            'provider_stage' => 'tts',
            'provider_key' => $providerKey,
            'model_version' => 'test-v1',
            'route_role' => 'primary',
            'locale' => 'en-IN',
            'conversation_mode' => 'everyday',
            'result_status' => 'succeeded',
            'dispatch_kind' => 'provider_dispatch',
            'billable_unit_kind' => 'unicode_characters',
            'native_currency' => 'INR',
            'reconciliation_status' => 'unreconciled',
            'sample_rate_bps' => 0,
            'attempt_count' => $attempts,
            'dispatch_count' => $attempts,
            'success_count' => $attempts,
            'failure_count' => 0,
            'cancelled_count' => 0,
            'partial_count' => 0,
            'fallback_count' => 0,
            'billable_unit_quantity' => $attempts * 100,
            'estimated_native_cost_microunits' => $attempts * 10,
            'estimated_inr_cost_microunits' => $attempts * 1000,
            'first_result_latency_histogram' => json_encode($empty, JSON_THROW_ON_ERROR),
            'completion_latency_histogram' => json_encode($empty, JSON_THROW_ON_ERROR),
            'first_audible_latency_histogram' => json_encode($empty, JSON_THROW_ON_ERROR),
            'timing_sample_count' => $attempts,
            'source_sample_count' => $attempts,
            'small_cell_suppressed' => $suppressed,
            'first_recorded_at' => $now,
            'last_recorded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertRateCard(): void
    {
        $now = now();
        DB::table('speech_rate_cards')->insert([
            'id' => (string) Str::ulid(),
            'provider_key' => 'synthetic-speech',
            'provider_stage' => 'tts',
            'model_version' => 'synthetic-v1',
            'rate_version' => 'synthetic-admin-v1',
            'billable_unit_kind' => 'unicode_characters',
            'unit_basis' => 1000,
            'billing_currency' => 'USD',
            'gross_price_microunits' => 20_000,
            'inr_per_native_unit_microunits' => 83_000_000,
            'conversion_source' => 'synthetic-test-fixture',
            'conversion_observed_at' => $now,
            'source_evidence_sha256' => str_repeat('c', 64),
            'approval_evidence' => 'APP-TEST-SPEECH-ADMIN',
            'effective_from' => $now->copy()->subDay(),
            'recorded_at' => $now,
        ]);
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
        $this->assertIsString($challengeId);

        $verified = $this->withHeader('Origin', 'https://localhost')
            ->postJson('/api/v1/auth/otp/verifications', [
                'challenge_id' => $challengeId,
                'mobile_number' => $mobileNumber,
                'code' => self::OTP,
            ])
            ->assertOk();
        $cookie = $verified->getCookie((string) config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->withCookie($cookie->getName(), (string) $cookie->getValue());
        $userId = $verified->json('user.id');
        $this->assertIsString($userId);

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
}

final class SpeechEconomicsOtpCodeGenerator implements OtpCodeGenerator
{
    public function __construct(private readonly string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}

final class SpeechEconomicsNullOtpDeliveryAdapter implements OtpDeliveryAdapter
{
    public function send(string $normalizedMobileNumber, string $code): void {}
}
