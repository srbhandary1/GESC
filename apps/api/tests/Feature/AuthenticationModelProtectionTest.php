<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuthenticationSession;
use App\Models\MobileRefreshToken;
use App\Models\MobileTokenFamily;
use App\Models\OtpChallenge;
use App\Models\PrivilegedMfaChallenge;
use App\Models\PrivilegedMfaFactor;
use App\Models\PrivilegedMfaRecoveryCode;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

final class AuthenticationModelProtectionTest extends TestCase
{
    public function test_authoritative_authentication_fields_are_not_mass_assignable(): void
    {
        $models = [
            [new OtpChallenge, [
                'id',
                'destination_digest',
                'code_digest',
                'failed_attempts',
                'expires_at',
                'consumed_at',
                'superseded_at',
            ]],
            [new AuthenticationSession, [
                'id',
                'user_id',
                'selected_organization_id',
                'selected_membership_id',
                'storage_session_digest',
                'storage_session_ciphertext',
                'is_privileged',
                'started_at',
                'last_seen_at',
                'idle_expires_at',
                'absolute_expires_at',
                'mfa_verified_at',
                'mfa_factor_id',
                'mfa_verified_method',
                'mfa_verified_scope',
                'revoked_at',
                'revocation_reason',
            ]],
            [new MobileTokenFamily, [
                'id',
                'user_id',
                'client_id',
                'selected_organization_id',
                'selected_membership_id',
                'installation_digest',
                'last_used_at',
                'absolute_expires_at',
                'revoked_at',
                'revocation_reason',
            ]],
            [new MobileRefreshToken, [
                'id',
                'family_id',
                'token_digest',
                'passport_refresh_token_id',
                'access_token_id',
                'expires_at',
                'rotated_at',
            ]],
            [new PrivilegedMfaFactor, [
                'id',
                'user_id',
                'factor_type',
                'secret_ciphertext',
                'status',
                'pending_expires_at',
                'confirmed_at',
                'replaced_at',
                'revoked_at',
                'last_used_time_step',
            ]],
            [new PrivilegedMfaChallenge, [
                'id',
                'user_id',
                'authentication_session_id',
                'factor_id',
                'purpose',
                'failed_attempts',
                'expires_at',
                'consumed_at',
                'superseded_at',
            ]],
            [new PrivilegedMfaRecoveryCode, [
                'id',
                'user_id',
                'factor_id',
                'code_digest',
                'consumed_at',
                'invalidated_at',
            ]],
        ];

        foreach ($models as [$model, $fields]) {
            self::assertInstanceOf(Model::class, $model);
            self::assertTrue($model->totallyGuarded());

            foreach ($fields as $field) {
                self::assertTrue($model->isGuarded($field));
            }
        }
    }

    public function test_authentication_secrets_are_omitted_from_array_and_json_serialization(): void
    {
        $models = [
            (new OtpChallenge)->forceFill([
                'destination_digest' => 'destination-secret',
                'code_digest' => 'code-secret',
            ]),
            (new AuthenticationSession)->forceFill([
                'storage_session_digest' => 'storage-secret',
                'storage_session_ciphertext' => 'ciphertext-secret',
            ]),
            (new MobileTokenFamily)->forceFill([
                'installation_digest' => 'installation-secret',
            ]),
            (new MobileRefreshToken)->forceFill([
                'token_digest' => 'refresh-secret',
            ]),
            (new PrivilegedMfaFactor)->forceFill([
                'secret_ciphertext' => 'totp-secret-ciphertext',
                'last_used_time_step' => 123456,
            ]),
            (new PrivilegedMfaRecoveryCode)->forceFill([
                'code_digest' => 'recovery-code-digest',
            ]),
        ];

        $serialized = json_encode($models, JSON_THROW_ON_ERROR);

        foreach ([
            'destination-secret',
            'code-secret',
            'storage-secret',
            'ciphertext-secret',
            'installation-secret',
            'refresh-secret',
            'totp-secret-ciphertext',
            '123456',
            'recovery-code-digest',
        ] as $secret) {
            self::assertStringNotContainsString($secret, $serialized);
        }
    }
}
