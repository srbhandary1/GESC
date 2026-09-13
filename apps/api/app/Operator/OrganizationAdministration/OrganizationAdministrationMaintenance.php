<?php

declare(strict_types=1);

namespace App\Operator\OrganizationAdministration;

use App\Authentication\Sessions\AuthenticationSessionCleanupWorker;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class OrganizationAdministrationMaintenance
{
    public function __construct(
        private AuthenticationSessionCleanupWorker $sessionCleanup,
    ) {}

    /**
     * @return array{
     *     invitations_expired: int,
     *     invitation_digests_cleared: int,
     *     session_cleanup_completed: int,
     *     session_cleanup_retried: int,
     *     session_cleanup_exhausted: int,
     *     session_cleanup_exhausted_backlog: int,
     *     session_cleanup_requests_purged: int,
     *     overdue_terminal_digest_backlog: int,
     *     administration_events_purged: int,
     *     administration_commands_purged: int,
     *     terminal_invitations_purged: int
     * }
     */
    public function run(int $perTenantLimit = 100): array
    {
        $perTenantLimit = max(1, min($perTenantLimit, 500));
        $result = [
            'invitations_expired' => 0,
            'invitation_digests_cleared' => 0,
            'session_cleanup_completed' => 0,
            'session_cleanup_retried' => 0,
            'session_cleanup_exhausted' => 0,
            'session_cleanup_exhausted_backlog' => 0,
            'session_cleanup_requests_purged' => 0,
            'overdue_terminal_digest_backlog' => 0,
            'administration_events_purged' => 0,
            'administration_commands_purged' => 0,
            'terminal_invitations_purged' => 0,
        ];
        $organizationIds = DB::table('organizations')
            ->orderBy('id')
            ->pluck('id')
            ->filter(static fn (mixed $id): bool => is_string($id))
            ->values()
            ->all();

        foreach ($organizationIds as $organizationId) {
            $tenantResult = DB::transaction(fn (): array => $this->maintainTenant(
                $organizationId,
                $perTenantLimit,
                CarbonImmutable::now(),
            ));

            foreach ($tenantResult as $key => $count) {
                $result[$key] += $count;
            }
        }

        $cleanup = $this->sessionCleanup->processDue($perTenantLimit);
        $result['session_cleanup_completed'] = $cleanup['completed'];
        $result['session_cleanup_retried'] = $cleanup['retried'];
        $result['session_cleanup_exhausted'] = $cleanup['exhausted'];
        $result['session_cleanup_exhausted_backlog'] = DB::table(
            'authentication_session_cleanup_requests',
        )
            ->where('attempts', '>=', 10)
            ->whereIn('status', ['pending', 'processing'])
            ->count();
        $result['session_cleanup_requests_purged'] = $this
            ->purgeCompletedSessionCleanupRequests(
                $perTenantLimit,
                CarbonImmutable::now(),
            );
        $result['overdue_terminal_digest_backlog'] = $this
            ->overdueTerminalDigestBacklog(CarbonImmutable::now());

        return $result;
    }

    /**
     * @return array{
     *     invitations_expired: int,
     *     invitation_digests_cleared: int,
     *     administration_events_purged: int,
     *     administration_commands_purged: int,
     *     terminal_invitations_purged: int
     * }
     */
    private function maintainTenant(
        string $organizationId,
        int $limit,
        CarbonImmutable $now,
    ): array {
        $organization = DB::table('organizations')
            ->where('id', $organizationId)
            ->lockForUpdate()
            ->first(['id']);

        if (! $organization instanceof stdClass) {
            return [
                'invitations_expired' => 0,
                'invitation_digests_cleared' => 0,
                'administration_events_purged' => 0,
                'administration_commands_purged' => 0,
                'terminal_invitations_purged' => 0,
            ];
        }

        $expired = $this->expireInvitations($organizationId, $limit, $now);
        $digests = $this->clearInviteDigests($organizationId, $limit, $now);
        [$events, $commands] = $this->purgeHistory(
            $organizationId,
            $limit,
            $now,
        );
        $invitations = $this->purgeTerminalInvitations(
            $organizationId,
            $limit,
            $now,
        );

        return [
            'invitations_expired' => $expired,
            'invitation_digests_cleared' => $digests,
            'administration_events_purged' => $events,
            'administration_commands_purged' => $commands,
            'terminal_invitations_purged' => $invitations,
        ];
    }

    private function expireInvitations(
        string $organizationId,
        int $limit,
        CarbonImmutable $now,
    ): int {
        $expired = 0;
        do {
            $rows = DB::table('institution_invites')
                ->where('organization_id', $organizationId)
                ->where('status', 'pending')
                ->where('expires_at', '<=', $now)
                ->orderBy('expires_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get(['id', 'expires_at']);

            foreach ($rows as $row) {
                if (! is_string($row->id ?? null)) {
                    continue;
                }

                $expiresAt = $this->date($row->expires_at ?? null);

                if ($expiresAt === null) {
                    continue;
                }

                $expired += DB::table('institution_invites')
                    ->where('organization_id', $organizationId)
                    ->where('id', $row->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'expired',
                        'version' => DB::raw('version + 1'),
                        'code_discard_after' => $expiresAt,
                        'updated_at' => $now,
                    ]);
            }
        } while ($rows->count() === $limit);

        return $expired;
    }

    private function clearInviteDigests(
        string $organizationId,
        int $limit,
        CarbonImmutable $now,
    ): int {
        $cleared = 0;
        $discardMinutes = (int) config(
            'operator.institution_invite_digest_discard_minutes',
            60,
        );

        do {
            $rows = DB::table('institution_invites')
                ->where('organization_id', $organizationId)
                ->whereIn('status', ['accepted', 'revoked', 'expired'])
                ->whereNotNull('code_digest')
                ->where(function ($query) use ($now, $discardMinutes): void {
                    $query
                        ->where('code_discard_after', '<=', $now)
                        ->orWhere(function ($legacy) use ($now, $discardMinutes): void {
                            $legacy
                                ->whereNull('code_discard_after')
                                ->where(
                                    'updated_at',
                                    '<=',
                                    $now->subMinutes($discardMinutes),
                                );
                        });
                })
                ->orderBy('updated_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get(['id']);
            $ids = $rows->pluck('id')
                ->filter(static fn (mixed $id): bool => is_string($id))
                ->values()
                ->all();

            if ($ids !== []) {
                $cleared += DB::table('institution_invites')
                    ->where('organization_id', $organizationId)
                    ->whereIn('id', $ids)
                    ->whereNotNull('code_digest')
                    ->update([
                        'code_digest' => null,
                        'updated_at' => $now,
                    ]);
            }
        } while ($rows->count() === $limit);

        return $cleared;
    }

    /**
     * @return array{int, int}
     */
    private function purgeHistory(
        string $organizationId,
        int $limit,
        CarbonImmutable $now,
    ): array {
        $historyDays = (int) config(
            'operator.organization_administration_history_days',
            365,
        );
        $cutoff = $now->subDays($historyDays);
        $events = 0;
        $commands = 0;

        do {
            $commandIds = DB::table('organization_administration_commands')
                ->where('organization_id', $organizationId)
                ->whereNotNull('completed_at')
                ->where('expires_at', '<=', $now)
                ->whereNotExists(function ($query) use ($cutoff): void {
                    $query->selectRaw('1')
                        ->from('organization_administration_events')
                        ->whereColumn(
                            'organization_administration_events.command_id',
                            'organization_administration_commands.id',
                        )
                        ->where('organization_administration_events.created_at', '>', $cutoff);
                })
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('learner_goal_corrections')
                        ->whereColumn(
                            'learner_goal_corrections.command_id',
                            'organization_administration_commands.id',
                        );
                })
                ->orderBy('expires_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->pluck('id')
                ->filter(static fn (mixed $id): bool => is_string($id))
                ->values()
                ->all();

            if ($commandIds === []) {
                break;
            }

            $eventIds = DB::table('organization_administration_events')
                ->where('organization_id', $organizationId)
                ->whereIn('command_id', $commandIds)
                ->where('created_at', '<=', $cutoff)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->filter(static fn (mixed $id): bool => is_string($id))
                ->values()
                ->all();

            if ($eventIds !== []) {
                $events += DB::table('organization_administration_events')
                    ->where('organization_id', $organizationId)
                    ->whereIn('id', $eventIds)
                    ->delete();
            }

            $commands += DB::table('organization_administration_commands')
                ->where('organization_id', $organizationId)
                ->whereIn('id', $commandIds)
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('organization_administration_events')
                        ->whereColumn(
                            'organization_administration_events.command_id',
                            'organization_administration_commands.id',
                        );
                })
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('learner_goal_corrections')
                        ->whereColumn(
                            'learner_goal_corrections.command_id',
                            'organization_administration_commands.id',
                        );
                })
                ->delete();
        } while (count($commandIds) === $limit);

        return [$events, $commands];
    }

    private function purgeTerminalInvitations(
        string $organizationId,
        int $limit,
        CarbonImmutable $now,
    ): int {
        $historyDays = (int) config(
            'operator.organization_administration_history_days',
            365,
        );
        $cutoff = $now->subDays($historyDays);
        $purged = 0;

        do {
            $ids = DB::table('institution_invites')
                ->where('organization_id', $organizationId)
                ->whereNull('code_digest')
                ->where(function ($query) use ($cutoff): void {
                    $query
                        ->where(function ($accepted) use ($cutoff): void {
                            $accepted
                                ->where('status', 'accepted')
                                ->where('accepted_at', '<=', $cutoff);
                        })
                        ->orWhere(function ($revoked) use ($cutoff): void {
                            $revoked
                                ->where('status', 'revoked')
                                ->where('revoked_at', '<=', $cutoff);
                        })
                        ->orWhere(function ($expired) use ($cutoff): void {
                            $expired
                                ->where('status', 'expired')
                                ->where('expires_at', '<=', $cutoff);
                        });
                })
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('organization_administration_events')
                        ->whereColumn(
                            'organization_administration_events.organization_id',
                            'institution_invites.organization_id',
                        )
                        ->whereColumn(
                            'organization_administration_events.subject_invitation_id',
                            'institution_invites.id',
                        );
                })
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->pluck('id')
                ->filter(static fn (mixed $id): bool => is_string($id))
                ->values()
                ->all();

            if ($ids !== []) {
                $purged += DB::table('institution_invites')
                    ->where('organization_id', $organizationId)
                    ->whereIn('id', $ids)
                    ->delete();
            }
        } while (count($ids) === $limit);

        return $purged;
    }

    private function purgeCompletedSessionCleanupRequests(
        int $limit,
        CarbonImmutable $now,
    ): int {
        $historyDays = (int) config(
            'operator.organization_administration_history_days',
            365,
        );
        $cutoff = $now->subDays($historyDays);
        $purged = 0;

        do {
            $ids = DB::table('authentication_session_cleanup_requests')
                ->where('status', 'completed')
                ->where('completed_at', '<=', $cutoff)
                ->orderBy('completed_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->pluck('id')
                ->filter(static fn (mixed $id): bool => is_string($id))
                ->values()
                ->all();

            if ($ids !== []) {
                $purged += DB::table('authentication_session_cleanup_requests')
                    ->whereIn('id', $ids)
                    ->delete();
            }
        } while (count($ids) === $limit);

        return $purged;
    }

    private function overdueTerminalDigestBacklog(CarbonImmutable $now): int
    {
        $discardMinutes = (int) config(
            'operator.institution_invite_digest_discard_minutes',
            60,
        );

        return DB::table('institution_invites')
            ->whereIn('status', ['accepted', 'revoked', 'expired'])
            ->whereNotNull('code_digest')
            ->where(function ($query) use ($now, $discardMinutes): void {
                $query
                    ->where('code_discard_after', '<=', $now)
                    ->orWhere(function ($legacy) use ($now, $discardMinutes): void {
                        $legacy
                            ->whereNull('code_discard_after')
                            ->where(
                                'updated_at',
                                '<=',
                                $now->subMinutes($discardMinutes),
                            );
                    });
            })
            ->count();
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }
}
