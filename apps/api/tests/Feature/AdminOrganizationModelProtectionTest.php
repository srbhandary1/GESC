<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuthenticationSession;
use App\Models\AuthenticationSessionCleanupRequest;
use App\Models\Branch;
use App\Models\Cohort;
use App\Models\InstitutionInvite;
use App\Models\Membership;
use App\Models\OrganizationAdministrationCommand;
use App\Models\OrganizationAdministrationEvent;
use App\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

final class AdminOrganizationModelProtectionTest extends TestCase
{
    public function test_authoritative_admin_organization_fields_are_guarded(): void
    {
        $models = [
            [new Branch, [
                'id',
                'organization_id',
                'key',
                'status',
                'version',
                'archived_at',
            ]],
            [new Cohort, [
                'id',
                'organization_id',
                'branch_id',
                'key',
                'status',
                'version',
                'archived_at',
            ]],
            [new Role, [
                'id',
                'organization_id',
                'key',
                'is_system',
                'status',
                'version',
                'retired_at',
            ]],
            [new Membership, [
                'id',
                'organization_id',
                'user_id',
                'membership_type',
                'status',
                'version',
            ]],
            [new InstitutionInvite, [
                'id',
                'organization_id',
                'code_digest',
                'membership_type',
                'issued_by_membership_id',
                'revoked_by_membership_id',
                'revoked_at',
                'code_discard_after',
                'version',
            ]],
            [new AuthenticationSession, [
                'selected_organization_id',
                'selected_membership_id',
            ]],
        ];

        foreach ($models as [$model, $fields]) {
            self::assertInstanceOf(Model::class, $model);

            foreach ($fields as $field) {
                self::assertTrue($model->isGuarded($field));
            }
        }

        foreach ([
            new OrganizationAdministrationCommand,
            new OrganizationAdministrationEvent,
            new AuthenticationSessionCleanupRequest,
        ] as $model) {
            self::assertTrue($model->totallyGuarded());
        }
    }

    public function test_admin_organization_secrets_are_hidden_from_serialization(): void
    {
        $invite = (new InstitutionInvite)->forceFill([
            'code_digest' => 'invite-code-digest',
            'accepted_idempotency_key' => 'invite-idempotency-key',
            'accepted_request_fingerprint' => 'invite-fingerprint',
        ]);
        $command = (new OrganizationAdministrationCommand)->forceFill([
            'idempotency_key' => 'command-idempotency-key',
            'request_fingerprint' => 'command-fingerprint',
        ]);

        $serialized = json_encode([$invite, $command], JSON_THROW_ON_ERROR);

        foreach ([
            'invite-code-digest',
            'invite-idempotency-key',
            'invite-fingerprint',
            'command-idempotency-key',
            'command-fingerprint',
        ] as $secret) {
            self::assertStringNotContainsString($secret, $serialized);
        }
    }
}
