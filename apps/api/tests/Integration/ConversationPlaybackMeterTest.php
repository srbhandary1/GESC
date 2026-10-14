<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Conversation\Metering\ConversationPlaybackMeter;
use App\Entitlements\DirectCatalogSeeder;
use App\Entitlements\EntitlementAccess;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConversationPlaybackMeterTest extends TenancyIntegrationTestCase
{
    public function test_played_seconds_are_monotonic_and_replay_safe_without_generation_charge(): void
    {
        [$user, $context] = $this->learner();
        $this->contexts()->run($context, function () use ($user): void {
            $meter = app(ConversationPlaybackMeter::class);
            $speech = $meter->decorate($user, ['model_audio_base64' => base64_encode($this->wav())]);
            $grant = $speech['model_playback_grant'];
            $key = (string) Str::uuid();
            $start = $meter->progress($user, $grant, $key, 0);
            self::assertSame(0, $start['charged_seconds']);
            self::assertSame(0, DB::table('live_ai_meter_charges')->count());
            $this->travel(3)->seconds();
            self::assertSame(3, $meter->progress($user, $grant, $key, 2500)['charged_seconds']);
            self::assertSame(3, $meter->progress($user, $grant, $key, 2500)['charged_seconds']);
            self::assertSame(3, $meter->progress($user, $grant, $key, 1000)['charged_seconds']);
            self::assertSame(3, (int) DB::table('live_ai_meter_charges')->sum('seconds'));
            self::assertSame(3, (int) DB::table('learner_entitlements')->value('live_ai_seconds_used'));
            $this->travel(4)->seconds();
            self::assertSame(7, $meter->progress($user, $grant, $key, 7000)['charged_seconds']);
            self::assertSame(7, (int) DB::table('live_ai_meter_charges')->sum('seconds'));
        });
    }

    public function test_grant_cannot_cross_learner_or_organization_or_be_forged(): void
    {
        [$one, $context] = $this->learner();
        [$two, $other] = $this->learner($this->createOrganization('Other synthetic organization'));
        $meter = app(ConversationPlaybackMeter::class);
        $grant = $this->contexts()->run($context, fn () => $meter->decorate($one, ['model_audio_base64' => base64_encode($this->wav())])['model_playback_grant']);
        foreach ([$grant, $grant.'tampered'] as $candidate) {
            try {
                $this->contexts()->run($other, fn () => $meter->progress($two, $candidate, (string) Str::uuid(), 0));
                self::fail('Foreign or forged grant accepted.');
            } catch (ConversationOperationRejected) {
                self::assertSame(0, DB::table('conversation_playback_meters')->count());
            }
        }
    }

    public function test_playback_grant_is_rejected_for_another_student_in_the_same_organization(): void
    {
        [$owner, $context] = $this->learner();
        [$other, $otherContext] = $this->learner();
        $meter = app(ConversationPlaybackMeter::class);
        $grant = $this->contexts()->run($context, fn () => $meter->decorate($owner, ['model_audio_base64' => base64_encode($this->wav())])['model_playback_grant']);
        $this->expectException(ConversationOperationRejected::class);
        $this->contexts()->run($otherContext, fn () => $meter->progress($other, $grant, (string) Str::uuid(), 0));
    }

    public function test_excess_duration_wall_clock_and_expired_grants_are_rejected(): void
    {
        [$user, $context] = $this->learner();
        $this->contexts()->run($context, function () use ($user): void {
            $meter = app(ConversationPlaybackMeter::class);
            $grant = $meter->decorate($user, ['model_audio_base64' => base64_encode($this->wav())])['model_playback_grant'];
            $key = (string) Str::uuid();
            $meter->progress($user, $grant, $key, 0);
            foreach ([8000, 11000] as $position) {
                try {
                    $meter->progress($user, $grant, $key, $position);
                    self::fail('Unplayed time accepted.');
                } catch (ConversationOperationRejected) {
                    self::assertSame(0, DB::table('live_ai_meter_charges')->count());
                }
            }
            $this->travel(16)->minutes();
            $this->expectException(ConversationOperationRejected::class);
            $meter->progress($user, $grant, $key, 1000);
        });
    }

    public function test_partial_balance_caps_playback_without_overdraft(): void
    {
        [$user, $context] = $this->learner();
        $this->contexts()->run($context, function () use ($user): void {
            $snapshot = app(EntitlementAccess::class)->snapshot($user);
            DB::table('learner_entitlements')->update(['live_ai_seconds_used' => $snapshot['live_ai_cap_seconds'] - 2]);
            $meter = app(ConversationPlaybackMeter::class);
            $grant = $meter->decorate($user, ['model_audio_base64' => base64_encode($this->wav())])['model_playback_grant'];
            $key = (string) Str::uuid();
            self::assertSame(2000, $meter->progress($user, $grant, $key, 0)['allowed_ms']);
            $this->travel(10)->seconds();
            $result = $meter->progress($user, $grant, $key, 9000);
            self::assertSame(2, $result['charged_seconds']);
            self::assertSame(0, $result['remaining_seconds']);
            self::assertSame(2, (int) DB::table('live_ai_meter_charges')->sum('seconds'));
        });
    }

    /** @return array{User, OrganizationContext} */
    private function learner(?Organization $organization = null): array
    {
        $organization ??= Organization::query()->where('system_key', Organization::DIRECT_SYSTEM_KEY)->firstOrFail();
        $user = $this->createUser();
        [$member, $context] = $this->createMembership($organization, $user);
        $role = $this->createRole($context, 'playback-'.strtolower((string) Str::ulid()));
        DB::table('membership_roles')->insert(['organization_id' => $context->organizationId, 'membership_id' => $member->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permissions')->insert(['organization_id' => $context->organizationId, 'role_id' => $role->id, 'permission_id' => Permission::query()->where('key', 'learning.participate')->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        if ($organization->kind === Organization::KIND_PLATFORM_DIRECT) {
            app(DirectCatalogSeeder::class)->seed($context->organizationId, now()->toIso8601String());
            $plan = DB::table('entitlement_plans')->where('organization_id', $context->organizationId)->where('code', 'monthly_modules_roleplay')->firstOrFail();
            app(EntitlementAccess::class)->grantPlan($context->organizationId, (string) $member->id, $plan, 'operator_grant', null, [], CarbonImmutable::now());
        }

        return [$user, $context];
    }

    private function wav(): string
    {
        $audio = str_repeat("\0", 16000 * 2 * 10);

        return 'RIFF'.pack('V', 36 + strlen($audio)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16).'data'.pack('V', strlen($audio)).$audio;
    }
}
