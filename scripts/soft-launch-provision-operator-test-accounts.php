<?php

declare(strict_types=1);

/**
 * Idempotently provision soft-launch operator test accounts.
 *
 * Usage (from repository root, with soft-launch env loaded):
 *   GE_SOFT_LAUNCH_ENV=~/ge-soft-launch/env/soft-launch.env \
 *     tmp/toolchain/php/php apps/api/artisan tinker \
 *     scripts/soft-launch-provision-operator-test-accounts.php
 *
 * Passwords are read from environment variables and must never be committed:
 *   GE_SOFT_LAUNCH_TRAINER_PASSWORD
 *   GE_SOFT_LAUNCH_REVIEWER_PASSWORD
 */

use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Password\PasswordPolicyViolation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$accounts = [
    [
        'username' => 'trainer',
        'display_name' => 'Soft launch trainer',
        'password_env' => 'GE_SOFT_LAUNCH_TRAINER_PASSWORD',
        'membership_type' => Membership::TYPE_TRAINER,
        'role_key' => Role::DIRECT_TRAINER_KEY,
    ],
    [
        'username' => 'reviewer',
        'display_name' => 'Soft launch reviewer',
        'password_env' => 'GE_SOFT_LAUNCH_REVIEWER_PASSWORD',
        'membership_type' => Membership::TYPE_TRAINER,
        'role_key' => Role::DIRECT_CONTENT_REVIEWER_KEY,
    ],
];

$organization = DB::table('organizations')
    ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
    ->where('status', Organization::STATUS_ACTIVE)
    ->first(['id']);

if (! $organization instanceof stdClass || ! is_string($organization->id)) {
    throw new RuntimeException('Global English Direct organization is unavailable.');
}

$organizationId = $organization->id;

/** @var PasswordCredentialService $passwords */
$passwords = app(PasswordCredentialService::class);
$request = Request::create('/internal/soft-launch-operator-provision', 'POST');
$request->server->set('REMOTE_ADDR', '127.0.0.1');

foreach ($accounts as $account) {
    $password = getenv($account['password_env']);
    if (! is_string($password) || $password === '') {
        throw new RuntimeException("Missing {$account['password_env']}.");
    }

    $role = DB::table('roles')
        ->where('organization_id', $organizationId)
        ->where('key', $account['role_key'])
        ->where('is_system', true)
        ->first(['id']);

    if (! $role instanceof stdClass || ! is_string($role->id)) {
        throw new RuntimeException("Role {$account['role_key']} is unavailable.");
    }

    DB::transaction(function () use (
        $account,
        $organizationId,
        $password,
        $passwords,
        $request,
        $role,
    ): void {
        $now = now();
        /** @var User|null $user */
        $user = User::query()->where('username', $account['username'])->first();

        if (! $user instanceof User) {
            $user = User::query()->forceCreate([
                'display_name' => $account['display_name'],
                'status' => User::STATUS_ACTIVE,
            ]);
        } elseif ($user->display_name !== $account['display_name']) {
            $user->forceFill(['display_name' => $account['display_name']])->save();
        }

        $hasCredential = $user->passwordCredential()->exists();

        if (! $hasCredential) {
            try {
                $passwords->establish($user, $account['username'], $password, $request);
            } catch (PasswordPolicyViolation $exception) {
                throw new RuntimeException(
                    "Could not establish {$account['username']}: {$exception->getMessage()}",
                );
            }
        } else {
            $passwords->resetPassword($user, $password, $request);
        }

        $user = User::query()->whereKey($user->getKey())->firstOrFail();

        $membershipRow = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->getKey())
            ->first(['id', 'membership_type', 'status']);

        if (! $membershipRow instanceof stdClass) {
            $membershipId = (string) Str::ulid();
            DB::table('memberships')->insert([
                'id' => $membershipId,
                'organization_id' => $organizationId,
                'user_id' => $user->getKey(),
                'membership_type' => $account['membership_type'],
                'status' => Membership::STATUS_ACTIVE,
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $membershipId = (string) $membershipRow->id;
            if (
                $membershipRow->membership_type !== $account['membership_type']
                || $membershipRow->status !== Membership::STATUS_ACTIVE
            ) {
                DB::table('memberships')->where('id', $membershipId)->update([
                    'membership_type' => $account['membership_type'],
                    'status' => Membership::STATUS_ACTIVE,
                    'ended_at' => null,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('membership_roles')->insertOrIgnore([
            'organization_id' => $organizationId,
            'membership_id' => $membershipId,
            'role_id' => $role->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        echo "provisioned {$account['username']} membership={$membershipId}\n";
    }, 3);
}

echo "done\n";
