<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Authentication\Password\RecoveryTokenDigester;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final class PasswordCutoverExclusionReconciliationCommand extends Command
{
    protected $signature = 'auth:reconcile-password-cutover-exclusions
        {--manifest= : Absolute path to the exact approved cutover manifest}
        {--dry-run : Validate without writing exclusion evidence}';

    protected $description = 'Reconcile exact reviewed password-cutover exclusions into append-only evidence.';

    public function __construct(private readonly RecoveryTokenDigester $digester)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $entries = $this->validateInventory();
        } catch (RuntimeException|JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Validated %d exact exclusions against the completed password-only cutover.',
            count($entries),
        ));

        if ((bool) $this->option('dry-run')) {
            return self::SUCCESS;
        }

        $manifestDigest = (string) config('authentication.password.cutover_manifest_sha256');
        $eventDigest = $this->digester->digest(
            'password-cutover-exclusion-reconciliation',
            $manifestDigest,
        );

        DB::transaction(function () use ($entries, $eventDigest, $manifestDigest): void {
            $cutover = DB::table('password_authentication_cutovers')
                ->where('scope_key', 'global')->lockForUpdate()->first();

            if (! $cutover instanceof \stdClass
                || ! hash_equals((string) $cutover->manifest_digest, $manifestDigest)) {
                throw new RuntimeException('The cutover marker changed during reconciliation.');
            }

            if (DB::table('password_cutover_exclusions')->exists()) {
                throw new RuntimeException('Password cutover exclusion evidence already exists.');
            }

            $now = Date::now();

            foreach ($entries as $entry) {
                DB::table('password_cutover_exclusions')->insert([
                    'user_id' => $entry['user_id'],
                    'cutover_scope_key' => 'global',
                    'reason_key' => $entry['reason_key'],
                    'owner_user_id' => $entry['owner_user_id'],
                    'manifest_digest' => $manifestDigest,
                    'reconciled_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('password_security_events')->insert([
                'id' => (string) Str::ulid(),
                'user_id' => null,
                'event_type' => 'password.cutover_exclusions_reconciled',
                'outcome' => 'succeeded',
                'request_digest' => $eventDigest,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, 3);

        $this->info('Append-only password cutover exclusion evidence was reconciled.');

        return self::SUCCESS;
    }

    /** @return list<array{user_id: string, reason_key: string, owner_user_id: string}> */
    private function validateInventory(): array
    {
        if (! app()->environment(['local', 'testing'])
            || config('authentication.password.cutover_exclusion_reconciliation_enabled') !== true) {
            throw new RuntimeException('Local cutover exclusion reconciliation is disabled.');
        }

        $path = $this->option('manifest');

        if (! is_string($path) || ! str_starts_with($path, '/') || ! is_file($path)) {
            throw new RuntimeException('An available absolute --manifest path is required.');
        }

        if ((fileperms($path) & 0777) !== 0600) {
            throw new RuntimeException('The cutover manifest must use mode 0600.');
        }

        $raw = file_get_contents($path);

        if (! is_string($raw)) {
            throw new RuntimeException('The cutover manifest is unavailable.');
        }

        $manifestDigest = hash('sha256', $raw);
        $configuredDigest = config('authentication.password.cutover_manifest_sha256');

        if (! is_string($configuredDigest)
            || preg_match('/\A[a-f0-9]{64}\z/D', $configuredDigest) !== 1
            || ! hash_equals($configuredDigest, $manifestDigest)) {
            throw new RuntimeException('The cutover manifest does not match approved configuration.');
        }

        $cutover = DB::table('password_authentication_cutovers')
            ->where('scope_key', 'global')->where('status', 'password_only')->first();

        if (! $cutover instanceof \stdClass
            || ! hash_equals((string) $cutover->manifest_digest, $manifestDigest)) {
            throw new RuntimeException('The approved manifest does not match the cutover marker.');
        }

        $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($manifest)
            || array_keys($manifest) !== ['exclusions']
            || ! is_array($manifest['exclusions'])) {
            throw new RuntimeException('The cutover manifest contract is invalid.');
        }

        $allowedOwners = config('platform.account_recovery.manager_user_ids', []);

        if (! is_array($allowedOwners)
            || array_filter(
                $allowedOwners,
                static fn (mixed $id): bool => ! is_string($id) || ! Str::isUlid($id),
            ) !== []
            || count(array_unique($allowedOwners)) < 1) {
            throw new RuntimeException('A configured account-recovery manager is required.');
        }

        $entries = [];

        foreach ($manifest['exclusions'] as $entry) {
            if (! is_array($entry)
                || array_keys($entry) !== ['user_id', 'reason_key', 'owner_user_id']
                || ! is_string($entry['user_id']) || ! Str::isUlid($entry['user_id'])
                || ! is_string($entry['reason_key'])
                || preg_match('/\Acutover_excluded\.[a-z0-9_.-]{3,60}\z/D', $entry['reason_key']) !== 1
                || ! is_string($entry['owner_user_id']) || ! Str::isUlid($entry['owner_user_id'])
                || ! in_array($entry['owner_user_id'], $allowedOwners, true)) {
                throw new RuntimeException('A cutover exclusion is invalid or lacks an approved owner.');
            }

            if (isset($entries[$entry['user_id']])) {
                throw new RuntimeException('The cutover manifest contains a duplicate exclusion.');
            }

            $entries[$entry['user_id']] = [
                'user_id' => $entry['user_id'],
                'reason_key' => $entry['reason_key'],
                'owner_user_id' => $entry['owner_user_id'],
            ];
        }

        $activeIds = User::query()->where('status', User::STATUS_ACTIVE)
            ->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
        $readyIds = DB::table('users')
            ->join('password_credentials', 'password_credentials.user_id', '=', 'users.id')
            ->where('users.status', User::STATUS_ACTIVE)
            ->whereNotNull('users.username')
            ->whereColumn('password_credentials.credential_version', 'users.credential_version')
            ->pluck('users.id')->map(static fn (mixed $id): string => (string) $id)->all();

        if (count($activeIds) !== (int) $cutover->active_user_count
            || count($readyIds) !== (int) $cutover->credential_ready_count
            || count($entries) !== (int) $cutover->excluded_user_count
            || array_diff($activeIds, [...$readyIds, ...array_keys($entries)]) !== []
            || array_intersect($readyIds, array_keys($entries)) !== []) {
            throw new RuntimeException('Current users do not match the completed cutover inventory.');
        }

        $ready = array_fill_keys($readyIds, true);

        foreach ($allowedOwners as $allowedOwner) {
            if (! isset($ready[$allowedOwner])) {
                throw new RuntimeException(
                    'Every configured account-recovery manager must remain credential-ready.',
                );
            }
        }

        foreach ($entries as $entry) {
            if (! isset($ready[$entry['owner_user_id']])) {
                throw new RuntimeException('Every exclusion owner must remain credential-ready.');
            }
        }

        return array_values($entries);
    }
}
