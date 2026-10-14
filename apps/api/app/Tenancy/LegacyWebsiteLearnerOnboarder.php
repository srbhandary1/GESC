<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Authentication\Password\PasswordAuthenticationFailed;
use App\Authentication\Password\PasswordCredentialService;
use App\Authentication\Password\PasswordPolicy;
use App\Authentication\Password\PasswordPolicyViolation;
use App\Entitlements\DirectCatalogSeeder;
use App\Entitlements\DirectPlanDefaults;
use App\Entitlements\EntitlementAccess;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\Exceptions\IdentityUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

final readonly class LegacyWebsiteLearnerOnboarder
{
    public const string IDENTITY_TYPE = 'legacy_user';

    public const string GRANT_REASON = 'legacy_website_basic';

    public const string PLAN_CODE = DirectPlanDefaults::MONTHLY_MODULES;

    public function __construct(
        private DirectLearnerProvisioner $directLearners,
        private PasswordCredentialService $passwords,
        private PasswordPolicy $policy,
        private DirectCatalogSeeder $catalog,
        private EntitlementAccess $access,
    ) {}

    /**
     * @param  list<array{legacy_user_id?: mixed, phone?: mixed, name?: mixed, password_hash?: mixed}>  $learners
     */
    public function onboard(array $learners, bool $dryRun = false): LegacyWebsiteOnboardingSummary
    {
        $organization = Organization::query()
            ->where('system_key', Organization::DIRECT_SYSTEM_KEY)
            ->where('kind', Organization::KIND_PLATFORM_DIRECT)
            ->where('status', Organization::STATUS_ACTIVE)
            ->first();

        if (! $organization instanceof Organization) {
            throw new RuntimeException('The active Global English Direct organization is not provisioned.');
        }

        $now = Date::now()->toIso8601String();
        $this->catalog->seed((string) $organization->getKey(), $now);
        $plan = DB::table('entitlement_plans')
            ->where('organization_id', $organization->getKey())
            ->where('code', self::PLAN_CODE)
            ->where('status', 'active')
            ->first();

        if (! $plan instanceof stdClass) {
            throw new RuntimeException('The Direct basic membership plan is not available.');
        }

        $considered = 0;
        $provisioned = 0;
        $replayed = 0;
        $credentialsImported = 0;
        $credentialsReplayed = 0;
        $granted = 0;
        $grantSkipped = 0;
        $skippedInvalid = 0;
        $skippedConflict = 0;
        $seenPhones = [];
        $request = Request::create('/internal/legacy-website-onboard', 'POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        foreach ($learners as $row) {
            $considered++;
            $legacyId = $this->string($row['legacy_user_id'] ?? null);
            $phoneRaw = $this->string($row['phone'] ?? null);
            $hash = $this->string($row['password_hash'] ?? null);
            $name = $this->string($row['name'] ?? null);

            if ($legacyId === '' || ! ctype_digit($legacyId) || $phoneRaw === '' || $hash === '') {
                $skippedInvalid++;

                continue;
            }

            try {
                $phone = $this->policy->normalizeUsername($phoneRaw);
            } catch (PasswordPolicyViolation) {
                $skippedInvalid++;

                continue;
            }

            if (! $this->policy->isImportablePasswordHash($hash)) {
                $skippedInvalid++;

                continue;
            }

            if (isset($seenPhones[$phone])) {
                $skippedConflict++;

                continue;
            }

            $seenPhones[$phone] = true;

            if ($dryRun) {
                $provisioned++;
                $credentialsImported++;
                $granted++;

                continue;
            }

            try {
                /** @var array{replayed: bool, credential_replayed: bool, grant_skipped: bool} $outcome */
                $outcome = DB::transaction(function () use (
                    $legacyId,
                    $phone,
                    $hash,
                    $name,
                    $request,
                    $organization,
                    $plan,
                ): array {
                    $result = $this->directLearners->provisionVerifiedIdentity(
                        self::IDENTITY_TYPE,
                        'gse-web:'.$legacyId,
                        $this->hint('u'.$legacyId),
                    );
                    $this->assignDisplayName($result->user, $name);
                    $credentialReplayed = $this->passwords->importLegacyHash(
                        $result->user,
                        $phone,
                        $hash,
                        $request,
                    );
                    $alreadyPaid = $this->hasPaidMembership(
                        (string) $organization->getKey(),
                        (string) $result->membership->getKey(),
                    );

                    if (! $alreadyPaid) {
                        $this->access->grantPlan(
                            (string) $organization->getKey(),
                            (string) $result->membership->getKey(),
                            $plan,
                            'operator_grant',
                            null,
                            ['reason' => self::GRANT_REASON],
                            CarbonImmutable::now('Asia/Kolkata'),
                        );
                    }

                    return [
                        'replayed' => $result->replayed,
                        'credential_replayed' => $credentialReplayed,
                        'grant_skipped' => $alreadyPaid,
                    ];
                }, 3);
            } catch (IdentityUnavailable|PasswordPolicyViolation|PasswordAuthenticationFailed) {
                $skippedConflict++;

                continue;
            }

            if ($outcome['replayed'] === true) {
                $replayed++;
            } else {
                $provisioned++;
            }

            if ($outcome['credential_replayed'] === true) {
                $credentialsReplayed++;
            } else {
                $credentialsImported++;
            }

            if ($outcome['grant_skipped'] === true) {
                $grantSkipped++;
            } else {
                $granted++;
            }
        }

        return new LegacyWebsiteOnboardingSummary(
            considered: $considered,
            provisioned: $provisioned,
            replayed: $replayed,
            credentialsImported: $credentialsImported,
            credentialsReplayed: $credentialsReplayed,
            granted: $granted,
            grantSkipped: $grantSkipped,
            skippedInvalid: $skippedInvalid,
            skippedConflict: $skippedConflict,
            dryRun: $dryRun,
        );
    }

    private function assignDisplayName(User $user, string $name): void
    {
        if ($name === '' || (is_string($user->display_name) && $user->display_name !== '')) {
            return;
        }

        $display = mb_substr($name, 0, 160, 'UTF-8');
        $user->forceFill(['display_name' => $display])->save();
    }

    private function hasPaidMembership(string $organizationId, string $membershipId): bool
    {
        $active = DB::table('learner_entitlements')
            ->where('organization_id', $organizationId)
            ->where('learner_membership_id', $membershipId)
            ->where('status', 'active')
            ->first();

        if (! $active instanceof stdClass) {
            return false;
        }

        $raw = $active->plan_snapshot ?? null;
        $snapshot = is_array($raw)
            ? $raw
            : json_decode(is_string($raw) ? $raw : '', true);
        $code = is_array($snapshot) && is_string($snapshot['code'] ?? null)
            ? $snapshot['code']
            : '';

        return in_array($code, [
            DirectPlanDefaults::MONTHLY_MODULES,
            DirectPlanDefaults::MONTHLY_ROLEPLAY,
        ], true);
    }

    private function hint(string $value): string
    {
        return mb_substr($value, 0, 40, 'UTF-8');
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
