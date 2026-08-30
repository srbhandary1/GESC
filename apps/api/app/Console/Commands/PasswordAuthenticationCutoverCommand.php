<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Password\PasswordPolicy;
use App\Authentication\Password\RecoveryTokenDigester;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final class PasswordAuthenticationCutoverCommand extends Command
{
    protected $signature = 'auth:password-cutover
        {--manifest= : Absolute path to the approved JSON cutover manifest}
        {--dry-run : Validate without changing authentication state}';

    protected $description = 'Validate and atomically cut the global authentication mode over to password-only.';

    public function __construct(
        private readonly PasswordCredentialService $passwords,
        private readonly PasswordPolicy $policy,
        private readonly RecoveryTokenDigester $digester,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (DB::table('password_authentication_cutovers')
            ->where('scope_key', 'global')
            ->where('status', 'password_only')
            ->exists()) {
            $this->info('Password-only cutover is already complete.');

            return self::SUCCESS;
        }

        if ($this->passwords->mode() !== 'credential_migration') {
            $this->error('Cutover requires AUTHENTICATION_MODE=credential_migration.');

            return self::FAILURE;
        }

        try {
            [$manifest, $manifestDigest] = $this->manifest();
            $this->policy->normalizeAndValidatePassword(
                'Cutover validation phrase 2026!',
                'cutover-validation',
            );
            $evidenceDigest = $this->digester->digest(
                'password-authentication-cutover',
                $manifestDigest,
            );
            $inventory = $this->inventory($manifest);
        } catch (RuntimeException|JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Validated %d active users: %d credential-ready, %d approved exclusions.',
            $inventory['active'],
            $inventory['ready'],
            $inventory['excluded'],
        ));

        if ((bool) $this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($evidenceDigest, $inventory, $manifestDigest): void {
            if (DB::table('password_authentication_cutovers')
                ->where('scope_key', 'global')
                ->lockForUpdate()
                ->exists()) {
                return;
            }

            $activeUserIds = User::query()
                ->where('status', User::STATUS_ACTIVE)
                ->lockForUpdate()
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all();
            $now = Date::now();

            User::query()->whereIn('id', $inventory['ready_ids'])->update([
                'credential_cutover_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('authentication_sessions')->whereNull('revoked_at')->update([
                'revoked_at' => $now,
                'revocation_reason' => 'password_cutover',
                'selected_organization_id' => null,
                'selected_membership_id' => null,
                'password_confirmed_at' => null,
                'password_confirmed_credential_version' => null,
                'mfa_verified_at' => null,
                'mfa_factor_id' => null,
                'mfa_verified_method' => null,
                'mfa_verified_scope' => null,
                'updated_at' => $now,
            ]);
            DB::table('sessions')->delete();
            DB::table('mobile_token_families')->whereNull('revoked_at')->update([
                'revoked_at' => $now,
                'revocation_reason' => 'password_cutover',
                'selected_organization_id' => null,
                'selected_membership_id' => null,
                'updated_at' => $now,
            ]);
            $accessTokenIds = DB::table('oauth_access_tokens')
                ->whereIn('user_id', $activeUserIds)
                ->pluck('id')
                ->all();
            DB::table('oauth_access_tokens')->whereIn('id', $accessTokenIds)->update(['revoked' => true]);
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $accessTokenIds)->update(['revoked' => true]);
            DB::table('privileged_mfa_factors')->where('status', 'active')->update([
                'status' => 'revoked',
                'secret_ciphertext' => null,
                'pending_expires_at' => null,
                'revoked_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('privileged_mfa_factors')->where('status', 'pending')->update([
                'status' => 'abandoned',
                'secret_ciphertext' => null,
                'pending_expires_at' => null,
                'updated_at' => $now,
            ]);
            DB::table('privileged_mfa_challenges')
                ->whereNull('consumed_at')->whereNull('superseded_at')
                ->update(['superseded_at' => $now, 'updated_at' => $now]);
            DB::table('privileged_mfa_recovery_codes')
                ->whereNull('consumed_at')->whereNull('invalidated_at')
                ->update(['invalidated_at' => $now, 'updated_at' => $now]);
            DB::table('otp_challenges')
                ->whereNull('consumed_at')->whereNull('superseded_at')
                ->update(['superseded_at' => $now, 'updated_at' => $now]);
            DB::table('password_authentication_cutovers')->insert([
                'scope_key' => 'global',
                'status' => 'password_only',
                'manifest_digest' => $manifestDigest,
                'evidence_digest' => $evidenceDigest,
                'active_user_count' => $inventory['active'],
                'credential_ready_count' => $inventory['ready'],
                'excluded_user_count' => $inventory['excluded'],
                'completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('password_security_events')->insert([
                'id' => (string) Str::ulid(),
                'user_id' => null,
                'event_type' => 'password.cutover',
                'outcome' => 'succeeded',
                'request_digest' => $evidenceDigest,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, 3);

        $this->info('Password-only cutover completed. Legacy OTP/TOTP material is retired.');

        return self::SUCCESS;
    }

    /** @return array{array<string, mixed>, string} */
    private function manifest(): array
    {
        $path = $this->option('manifest');

        if (! is_string($path) || $path === '' || ! str_starts_with($path, '/')) {
            throw new RuntimeException('An absolute --manifest path is required.');
        }

        $raw = file_get_contents($path);

        if (! is_string($raw)) {
            throw new RuntimeException('The cutover manifest is unavailable.');
        }

        $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($manifest) || array_keys($manifest) !== ['exclusions'] || ! is_array($manifest['exclusions'])) {
            throw new RuntimeException('The cutover manifest contract is invalid.');
        }

        return [$manifest, hash('sha256', $raw)];
    }

    /** @param array<string, mixed> $manifest
     * @return array{active: int, ready: int, excluded: int, ready_ids: list<string>}
     */
    private function inventory(array $manifest): array
    {
        $exclusions = [];
        $exclusionOwners = [];
        $allowedOwners = config('platform.account_recovery.manager_user_ids', []);

        if (! is_array($allowedOwners)) {
            throw new RuntimeException('The cutover owner configuration is invalid.');
        }

        foreach ($manifest['exclusions'] as $entry) {
            if (
                ! is_array($entry)
                || array_keys($entry) !== ['user_id', 'reason_key', 'owner_user_id']
                || ! is_string($entry['user_id'])
                || ! Str::isUlid($entry['user_id'])
                || ! is_string($entry['reason_key'])
                || preg_match('/\Acutover_excluded\.[a-z0-9_.-]{3,60}\z/D', $entry['reason_key']) !== 1
                || ! is_string($entry['owner_user_id'])
                || ! Str::isUlid($entry['owner_user_id'])
                || ! in_array($entry['owner_user_id'], $allowedOwners, true)
            ) {
                throw new RuntimeException('A cutover exclusion is invalid or lacks an approved owner.');
            }

            if (isset($exclusions[$entry['user_id']])) {
                throw new RuntimeException('The cutover manifest contains a duplicate exclusion.');
            }

            $exclusions[$entry['user_id']] = true;
            $exclusionOwners[$entry['owner_user_id']] = true;
        }

        $activeIds = User::query()->where('status', User::STATUS_ACTIVE)
            ->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
        $readyIds = DB::table('users')
            ->join('password_credentials', 'password_credentials.user_id', '=', 'users.id')
            ->where('users.status', User::STATUS_ACTIVE)
            ->whereNotNull('users.username')
            ->whereColumn('password_credentials.credential_version', 'users.credential_version')
            ->pluck('users.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
        $ready = array_fill_keys($readyIds, true);

        if (array_diff(array_keys($exclusionOwners), $readyIds) !== []) {
            throw new RuntimeException('Every cutover exclusion owner must be credential-ready.');
        }

        foreach ($activeIds as $userId) {
            if (! isset($ready[$userId]) && ! isset($exclusions[$userId])) {
                throw new RuntimeException('The manifest does not account for every active user.');
            }
        }

        if (array_intersect(array_keys($exclusions), $readyIds) !== []) {
            throw new RuntimeException('A credential-ready user cannot also be excluded.');
        }

        if (array_diff(array_keys($exclusions), $activeIds) !== []) {
            throw new RuntimeException('The manifest contains a non-active exclusion.');
        }

        return [
            'active' => count($activeIds),
            'ready' => count($readyIds),
            'excluded' => count($exclusions),
            'ready_ids' => array_values($readyIds),
        ];
    }
}
