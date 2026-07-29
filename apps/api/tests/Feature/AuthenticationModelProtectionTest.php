<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuthenticationSession;
use App\Models\MobileRefreshToken;
use App\Models\MobileTokenFamily;
use App\Models\OtpChallenge;
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
                'storage_session_digest',
                'storage_session_ciphertext',
                'is_privileged',
                'started_at',
                'last_seen_at',
                'idle_expires_at',
                'absolute_expires_at',
                'mfa_verified_at',
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
        ];

        $serialized = json_encode($models, JSON_THROW_ON_ERROR);

        foreach ([
            'destination-secret',
            'code-secret',
            'storage-secret',
            'ciphertext-secret',
            'installation-secret',
            'refresh-secret',
        ] as $secret) {
            self::assertStringNotContainsString($secret, $serialized);
        }
    }
}
