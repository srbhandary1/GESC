<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Identity\IdentityIdentifierDigester;
use App\Identity\VerifiedIdentityProvisioner;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class VerifiedIdentityProvisionerTest extends TenancyIntegrationTestCase
{
    public function test_verified_identity_provisioning_is_replay_safe_and_creates_no_membership(): void
    {
        $provisioner = $this->app->make(VerifiedIdentityProvisioner::class);

        $created = $provisioner->provision(
            type: 'phone',
            normalizedIdentifier: '+919876543210',
            displayHint: '••••3210',
        );
        $replayed = $provisioner->provision(
            type: 'phone',
            normalizedIdentifier: '+919876543210',
            displayHint: 'ignored-on-replay',
        );

        self::assertFalse($created->replayed);
        self::assertTrue($replayed->replayed);
        self::assertSame($created->user->getKey(), $replayed->user->getKey());
        self::assertSame(
            $created->identity->getKey(),
            $replayed->identity->getKey(),
        );
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_identities', 1);
        $this->assertDatabaseCount('memberships', 0);
        $this->assertDatabaseCount('membership_roles', 0);
        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_verified_identity_provisioning_participates_in_the_callers_transaction(): void
    {
        try {
            DB::transaction(function (): never {
                $this->app
                    ->make(VerifiedIdentityProvisioner::class)
                    ->provision(
                        type: 'phone',
                        normalizedIdentifier: '+919876543211',
                    );

                throw new RuntimeException('synthetic caller rollback');
            });
        } catch (RuntimeException) {
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('user_identities', 0);
            $this->assertDatabaseCount('memberships', 0);
        }
    }

    public function test_identity_digest_is_independent_from_application_encryption_key(): void
    {
        config([
            'authentication.identity.digest_key' => str_repeat('i', 32),
            'app.key' => str_repeat('a', 32),
        ]);
        $digester = $this->app->make(IdentityIdentifierDigester::class);
        $beforeRotation = $digester->digest('phone', '+919876543212');

        config(['app.key' => str_repeat('b', 32)]);
        $afterApplicationKeyRotation = $digester->digest(
            'phone',
            '+919876543212',
        );

        self::assertSame($beforeRotation, $afterApplicationKeyRotation);

        config([
            'authentication.identity.digest_key' => str_repeat('j', 32),
        ]);
        self::assertNotSame(
            $beforeRotation,
            $digester->digest('phone', '+919876543212'),
        );
    }

    public function test_identity_digest_configuration_fails_closed_when_missing(): void
    {
        config(['authentication.identity.digest_key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'A dedicated identity digest key is required.',
        );

        $this->app
            ->make(IdentityIdentifierDigester::class)
            ->digest('phone', '+919876543213');
    }

    public function test_global_identity_authority_fields_are_not_mass_assignable_or_serialized(): void
    {
        $provisioned = $this->app
            ->make(VerifiedIdentityProvisioner::class)
            ->provision(
                type: 'phone',
                normalizedIdentifier: '+919876543214',
                displayHint: '••••3214',
            );
        $otherUser = $this->createUser();
        $identity = $provisioned->identity;
        $original = [
            'user_id' => $identity->user_id,
            'type' => $identity->type,
            'identifier_digest' => $identity->identifier_digest,
            'display_hint' => $identity->display_hint,
            'status' => $identity->status,
            'verified_at' => $identity->verified_at,
        ];

        $identity->fill([
            'user_id' => $otherUser->getKey(),
            'type' => 'email',
            'identifier_digest' => str_repeat('f', 64),
            'display_hint' => 'attacker-controlled',
            'status' => UserIdentity::STATUS_DISABLED,
            'verified_at' => now()->subYear(),
        ]);
        $provisioned->user->fill([
            'status' => User::STATUS_SUSPENDED,
        ]);

        self::assertFalse($identity->isDirty());
        self::assertFalse($provisioned->user->isDirty());
        foreach ($original as $attribute => $value) {
            self::assertEquals($value, $identity->getAttribute($attribute));
        }
        self::assertArrayNotHasKey(
            'identifier_digest',
            $identity->toArray(),
        );
        self::assertArrayNotHasKey('display_hint', $identity->toArray());
    }
}
