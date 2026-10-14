<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Entitlements\Exceptions\EntitlementDenied;
use App\Learning\Exceptions\LearningOperationRejected;
use App\Learning\LevelSessions\LevelSessionDeliveryService;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReconcilePracticeCompletionsCommand extends Command
{
    protected $signature = 'learning:reconcile-practice-completions {--organization= : Exact organization ID} {--apply : Record eligible completions; otherwise report only}';

    protected $description = 'Complete Practice from genuine matching passed checks, without changing scores.';

    public function handle(LevelSessionDeliveryService $sessions, OrganizationContextManager $contexts): int
    {
        $organization = $this->option('organization');
        if (! is_string($organization) || ! DB::table('organizations')->where('id', $organization)->exists()) {
            $this->error('An existing organization is required.');

            return self::FAILURE;
        }
        $eligible = 0;
        $skipped = 0;
        $rows = DB::table('level_session_check_submissions')->where('organization_id', $organization)
            ->where('part_id', 'practice')->where('passed', true)->select(['user_id', 'membership_id', 'level_number'])->distinct()->get();
        foreach ($rows as $row) {
            $user = User::query()->whereKey((string) $row->user_id)->first();
            if ($user === null) {
                $skipped++;

                continue;
            }
            $context = new OrganizationContext(organizationId: $organization, actorUserId: (string) $row->user_id,
                membershipId: (string) $row->membership_id, source: OrganizationContextSource::Membership);
            try {
                $changed = $contexts->run($context, fn () => $sessions->reconcilePracticeCompletion($context, $user, (int) $row->level_number, (bool) $this->option('apply')));
                $eligible += $changed ? 1 : 0;
            } catch (LearningOperationRejected|EntitlementDenied) {
                $skipped++;
            }
        }
        $this->info(json_encode(['apply' => (bool) $this->option('apply'), 'eligible' => $eligible, 'skipped' => $skipped], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
