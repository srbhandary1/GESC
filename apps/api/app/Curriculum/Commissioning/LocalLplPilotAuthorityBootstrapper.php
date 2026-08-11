<?php

declare(strict_types=1);

namespace App\Curriculum\Commissioning;

use App\Audit\AuditEventRecorder;
use App\Curriculum\Commissioning\Exceptions\LocalLplPilotAuthorityBootstrapRejected;
use App\Curriculum\Governance\AuthorityAppointmentCommands;
use App\Curriculum\Governance\AuthorityKind;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Operator\OrganizationAdministration\OrganizationAdministrationEventRecorder;
use App\Operator\OrganizationAdministration\OrganizationAdministrationIdempotency;
use App\Outbox\OutboxEventRecorder;
use App\Tenancy\OrganizationContext;
use App\Tenancy\OrganizationContextManager;
use App\Tenancy\OrganizationContextSource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

final readonly class LocalLplPilotAuthorityBootstrapper
{
    public const string APPROVAL_REFERENCE = 'APP-0051';

    public const string OWNER_EVIDENCE_REFERENCE = 'APP-0047';

    public const string CONSOLIDATED_AUTHORITY_REFERENCE = 'APP-0004';

    public const string ORGANIZATION_ID = '01KYS97G7MX374P303XJHDXT72';

    public const string USER_ID = '01KZGE9QRTCQE3PQ3RN31AXQSE';

    public const string MEMBERSHIP_ID = '01KZGE9QRTCQE3PQ3RN31AXQSF';

    public const string PROGRAM_KEY = 'global-spoken-english-lpl-pilot';

    public const string PROGRAM_NAME = 'Global Spoken English LPL Pilot';

    public const string ROLE_KEY = 'lpl-pilot-commissioner';

    public const string ROLE_NAME = 'LPL Pilot Commissioner';

    private const string OPERATION = 'lpl01.pilot_authority.bootstrap';

    private const string PROGRAM_ATTRIBUTION = 'lpl01_pilot_authority_bootstrap';

    /** @var list<string> */
    private const array PERMISSION_KEYS = [
        'curriculum.author',
        'media.rights.approve',
    ];

    /**
     * @var array<string, array{hash: string, bytes: int}>
     */
    private const array ASSETS = [
        '01KZNFYHCJSDWSPK08ZAC7G3AJ' => [
            'hash' => '6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa',
            'bytes' => 4_968_408,
        ],
        '01KZNFYJ1M911SB8YVH9P4EZZ0' => [
            'hash' => '75909e9baf340356d7ecf66435977ee1cce1fe93a597da58a24b5b9ea1833dd7',
            'bytes' => 7_357_881,
        ],
        '01KZNFYJVSZRPZCEPTTQ4QTMNT' => [
            'hash' => '8124b3bc6f71b65258358b0121cc3d72f1543948ca3ef3a1bb419f1fa0d65c70',
            'bytes' => 2_157_215,
        ],
        '01KZNFYK75V9WF8R5VCWRRZHNN' => [
            'hash' => '4862e927078ecb0ea56b7240f6d5b52356365115a6512836357bf340fdcf895e',
            'bytes' => 4_276_686,
        ],
    ];

    public function __construct(
        private OrganizationContextManager $contexts,
        private OrganizationAdministrationIdempotency $idempotency,
        private OrganizationAdministrationEventRecorder $administrationEvents,
        private AuthorityAppointmentCommands $appointments,
        private AuditEventRecorder $audit,
        private OutboxEventRecorder $outbox,
    ) {}

    public function bootstrap(
        string $expectedOrganizationId,
        string $expectedUserId,
        string $expectedMembershipId,
        string $approvalReference,
        string $ownerEvidenceReference,
        string $idempotencyKey,
    ): LocalLplPilotAuthorityBootstrapResult {
        $this->assertExecutionAllowed(
            $expectedOrganizationId,
            $expectedUserId,
            $expectedMembershipId,
            $approvalReference,
            $ownerEvidenceReference,
            $idempotencyKey,
        );

        $actor = User::query()->find($expectedUserId);

        if (! $actor instanceof User) {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }

        $context = new OrganizationContext(
            organizationId: $expectedOrganizationId,
            actorUserId: $expectedUserId,
            membershipId: $expectedMembershipId,
            source: OrganizationContextSource::Membership,
        );

        try {
            return $this->contexts->run(
                $context,
                fn (): LocalLplPilotAuthorityBootstrapResult => DB::transaction(
                    fn (): LocalLplPilotAuthorityBootstrapResult => $this->execute(
                        $actor,
                        $expectedOrganizationId,
                        $expectedUserId,
                        $expectedMembershipId,
                        $approvalReference,
                        $ownerEvidenceReference,
                        $idempotencyKey,
                    ),
                    3,
                ),
            );
        } catch (LocalLplPilotAuthorityBootstrapRejected $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new LocalLplPilotAuthorityBootstrapRejected($exception);
        }
    }

    private function execute(
        User $actor,
        string $organizationId,
        string $userId,
        string $membershipId,
        string $approvalReference,
        string $ownerEvidenceReference,
        string $idempotencyKey,
    ): LocalLplPilotAuthorityBootstrapResult {
        $membership = $this->lockAndValidateAuthorityBase(
            $actor,
            $organizationId,
            $userId,
            $membershipId,
        );
        $this->lockAndValidateAssets($organizationId);

        $fingerprint = $this->idempotency->fingerprint(
            self::OPERATION,
            'organization',
            $organizationId,
            [
                'approval_reference' => $approvalReference,
                'asset_hashes' => array_column(self::ASSETS, 'hash'),
                'membership_id' => $membershipId,
                'owner_evidence_reference' => $ownerEvidenceReference,
                'program_key' => self::PROGRAM_KEY,
                'program_name' => self::PROGRAM_NAME,
                'role_key' => self::ROLE_KEY,
                'role_permission_keys' => self::PERMISSION_KEYS,
                'user_id' => $userId,
            ],
        );
        $claim = $this->idempotency->claim(
            $actor,
            $idempotencyKey,
            self::OPERATION,
            'organization',
            $organizationId,
            $fingerprint,
        );

        if ($claim->replayed) {
            [$program, $role, $appointment] = $this->exactExistingState(
                $organizationId,
                $membershipId,
            );

            if (
                $claim->resultType !== 'lpl_pilot_authority_bootstrap'
                || $claim->resultId === null
                || ! hash_equals($claim->resultId, $this->string($program, 'id'))
                || $claim->completedAt === null
            ) {
                throw new LocalLplPilotAuthorityBootstrapRejected;
            }

            return new LocalLplPilotAuthorityBootstrapResult(
                programId: $this->string($program, 'id'),
                roleId: $this->string($role, 'id'),
                membershipId: $membershipId,
                appointmentId: $this->string($appointment, 'id'),
                replayed: true,
                environment: 'local',
                approvalReference: $approvalReference,
                ownerEvidenceReference: $ownerEvidenceReference,
                completedAt: $claim->completedAt,
            );
        }

        $this->assertTargetStateAbsent($organizationId);
        $now = Date::now()->toImmutable();
        $programId = (string) Str::ulid();
        $roleId = (string) Str::ulid();
        $permissions = $this->lockedPermissions();

        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'key' => self::PROGRAM_KEY,
            'internal_name' => self::PROGRAM_NAME,
            'descriptor_version' => 1,
            'created_by_membership_id' => $membershipId,
            'created_by_attribution' => self::PROGRAM_ATTRIBUTION,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('roles')->insert([
            'id' => $roleId,
            'organization_id' => $organizationId,
            'key' => self::ROLE_KEY,
            'name' => self::ROLE_NAME,
            'is_system' => false,
            'status' => Role::STATUS_ACTIVE,
            'version' => 1,
            'retired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->administrationEvents->record(
            $organizationId,
            $membershipId,
            $claim->id,
            'role.created',
            ['role_id' => $roleId],
            $now,
        );

        foreach ($permissions as $permission) {
            $permissionId = $this->string($permission, 'id');
            DB::table('role_permissions')->insert([
                'organization_id' => $organizationId,
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->administrationEvents->record(
                $organizationId,
                $membershipId,
                $claim->id,
                'role.permission_granted',
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                $now,
            );
        }

        DB::table('membership_roles')->insert([
            'organization_id' => $organizationId,
            'membership_id' => $membershipId,
            'role_id' => $roleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $membershipVersion = $this->integer($membership, 'version');
        $updated = DB::table('memberships')
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->where('version', $membershipVersion)
            ->update([
                'version' => $membershipVersion + 1,
                'updated_at' => $now,
            ]);

        if ($updated !== 1) {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }

        $this->administrationEvents->record(
            $organizationId,
            $membershipId,
            $claim->id,
            'membership.role_assigned',
            ['membership_id' => $membershipId, 'role_id' => $roleId],
            $now,
        );
        $appointmentId = $this->appointments->appoint(
            actor: $actor,
            appointeeUserId: $userId,
            appointeeMembershipId: $membershipId,
            authorityKind: AuthorityKind::RightsConfirmer,
            scopeType: 'program',
            programId: $programId,
            validFrom: $now,
            validUntil: null,
            qualificationBasis: self::OWNER_EVIDENCE_REFERENCE,
            approvalEvidenceKey: self::CONSOLIDATED_AUTHORITY_REFERENCE,
            idempotencyKey: $idempotencyKey,
        );

        $this->recordBootstrapEvidence(
            $organizationId,
            $programId,
            $roleId,
            $membershipId,
            $appointmentId,
        );
        $this->idempotency->complete(
            $claim,
            'lpl_pilot_authority_bootstrap',
            $programId,
            $now,
        );

        return new LocalLplPilotAuthorityBootstrapResult(
            programId: $programId,
            roleId: $roleId,
            membershipId: $membershipId,
            appointmentId: $appointmentId,
            replayed: false,
            environment: 'local',
            approvalReference: $approvalReference,
            ownerEvidenceReference: $ownerEvidenceReference,
            completedAt: $now,
        );
    }

    private function assertExecutionAllowed(
        string $organizationId,
        string $userId,
        string $membershipId,
        string $approvalReference,
        string $ownerEvidenceReference,
        string $idempotencyKey,
    ): void {
        if (
            ! app()->environment('local')
            || config('authentication.otp.driver') !== 'local'
            || config('content_governance.local_lpl_pilot_authority_bootstrap_enabled')
                !== true
            || ! hash_equals(self::ORGANIZATION_ID, $organizationId)
            || ! hash_equals(self::USER_ID, $userId)
            || ! hash_equals(self::MEMBERSHIP_ID, $membershipId)
            || ! hash_equals(self::APPROVAL_REFERENCE, $approvalReference)
            || ! hash_equals(
                self::OWNER_EVIDENCE_REFERENCE,
                $ownerEvidenceReference,
            )
            || ! in_array(
                (string) config(
                    'content_governance.consolidated_self_appointments.APP-0004.actor_user_id',
                ),
                [self::USER_ID],
                true,
            )
            || (! Str::isUuid($idempotencyKey) && ! Str::isUlid($idempotencyKey))
        ) {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }
    }

    private function lockAndValidateAuthorityBase(
        User $actor,
        string $organizationId,
        string $userId,
        string $membershipId,
    ): stdClass {
        $organization = DB::table('organizations')
            ->where('id', $organizationId)
            ->lockForUpdate()
            ->first();
        $user = DB::table('users')
            ->where('id', $userId)
            ->lockForUpdate()
            ->first();
        $membership = DB::table('memberships')
            ->where('id', $membershipId)
            ->lockForUpdate()
            ->first();

        if (
            ! $organization instanceof stdClass
            || $this->string($organization, 'system_key')
                !== Organization::DIRECT_SYSTEM_KEY
            || $this->string($organization, 'kind')
                !== Organization::KIND_PLATFORM_DIRECT
            || $this->string($organization, 'status')
                !== Organization::STATUS_ACTIVE
            || ! $user instanceof stdClass
            || ! hash_equals((string) $actor->getKey(), $userId)
            || $this->string($user, 'status') !== User::STATUS_ACTIVE
            || ! $membership instanceof stdClass
            || ! hash_equals(
                $this->string($membership, 'organization_id'),
                $organizationId,
            )
            || ! hash_equals($this->string($membership, 'user_id'), $userId)
            || $this->string($membership, 'membership_type')
                !== Membership::TYPE_ORGANIZATION_ADMIN
            || $this->string($membership, 'status') !== Membership::STATUS_ACTIVE
        ) {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }

        $administratorRole = DB::table('roles')
            ->where('organization_id', $organizationId)
            ->where('key', Role::DIRECT_ORGANIZATION_ADMIN_KEY)
            ->lockForUpdate()
            ->first();

        if (
            ! $administratorRole instanceof stdClass
            || ! $this->boolean($administratorRole, 'is_system')
            || $this->string($administratorRole, 'status') !== Role::STATUS_ACTIVE
            || ! DB::table('membership_roles')
                ->where('organization_id', $organizationId)
                ->where('membership_id', $membershipId)
                ->where('role_id', $this->string($administratorRole, 'id'))
                ->lockForUpdate()
                ->exists()
        ) {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }

        return $membership;
    }

    private function lockAndValidateAssets(string $organizationId): void
    {
        foreach (self::ASSETS as $assetId => $expected) {
            $asset = DB::table('media_assets')
                ->where('organization_id', $organizationId)
                ->where('id', $assetId)
                ->lockForUpdate()
                ->first();
            $lifecycle = DB::table('media_asset_lifecycles')
                ->where('organization_id', $organizationId)
                ->where('media_asset_id', $assetId)
                ->lockForUpdate()
                ->first();
            $scanId = $lifecycle instanceof stdClass
                ? $lifecycle->selected_scan_id ?? null
                : null;
            $scan = is_string($scanId)
                ? DB::table('media_security_scans')
                    ->where('organization_id', $organizationId)
                    ->where('id', $scanId)
                    ->lockForUpdate()
                    ->first()
                : null;

            if (
                ! $asset instanceof stdClass
                || $this->string($asset, 'asset_kind') !== 'lesson_audio'
                || ! hash_equals(
                    $this->string($asset, 'asset_hash'),
                    $expected['hash'],
                )
                || $this->integer($asset, 'byte_size') !== $expected['bytes']
                || ! $lifecycle instanceof stdClass
                || $this->string($lifecycle, 'asset_kind') !== 'lesson_audio'
                || $this->string($lifecycle, 'state') !== 'active'
                || ! $scan instanceof stdClass
                || (
                    $scan->media_asset_id !== null
                    && ! hash_equals(
                        $this->string($scan, 'media_asset_id'),
                        $assetId,
                    )
                )
                || $this->string($scan, 'asset_kind') !== 'lesson_audio'
                || ! hash_equals(
                    $this->string($scan, 'object_hash'),
                    $expected['hash'],
                )
                || $this->integer($scan, 'byte_size') !== $expected['bytes']
                || $this->string($scan, 'inspection_outcome') !== 'passed'
                || $this->string($scan, 'malware_outcome') !== 'clean'
            ) {
                throw new LocalLplPilotAuthorityBootstrapRejected;
            }
        }
    }

    /** @return list<stdClass> */
    private function lockedPermissions(): array
    {
        $permissionRows = DB::table('permissions')
            ->whereIn('key', self::PERMISSION_KEYS)
            ->orderBy('key')
            ->lockForUpdate()
            ->get(['id', 'key'])
            ->all();
        $permissions = array_values($permissionRows);
        $keys = array_map(
            fn (stdClass $permission): string => $this->string($permission, 'key'),
            $permissions,
        );
        $expected = self::PERMISSION_KEYS;
        sort($expected);

        if ($keys !== $expected) {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }

        return $permissions;
    }

    private function assertTargetStateAbsent(string $organizationId): void
    {
        if (
            DB::table('programs')
                ->where('organization_id', $organizationId)
                ->where('key', self::PROGRAM_KEY)
                ->lockForUpdate()
                ->exists()
            || DB::table('roles')
                ->where('organization_id', $organizationId)
                ->where('key', self::ROLE_KEY)
                ->lockForUpdate()
                ->exists()
        ) {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }
    }

    /** @return array{stdClass, stdClass, stdClass} */
    private function exactExistingState(
        string $organizationId,
        string $membershipId,
    ): array {
        $program = DB::table('programs')
            ->where('organization_id', $organizationId)
            ->where('key', self::PROGRAM_KEY)
            ->lockForUpdate()
            ->first();
        $role = DB::table('roles')
            ->where('organization_id', $organizationId)
            ->where('key', self::ROLE_KEY)
            ->lockForUpdate()
            ->first();

        if (
            ! $program instanceof stdClass
            || $this->string($program, 'internal_name') !== self::PROGRAM_NAME
            || $this->integer($program, 'descriptor_version') !== 1
            || ! hash_equals(
                $this->string($program, 'created_by_membership_id'),
                $membershipId,
            )
            || $this->string($program, 'created_by_attribution')
                !== self::PROGRAM_ATTRIBUTION
            || DB::table('curriculum_versions')
                ->where('organization_id', $organizationId)
                ->where('program_id', $this->string($program, 'id'))
                ->lockForUpdate()
                ->exists()
            || ! $role instanceof stdClass
            || $this->string($role, 'name') !== self::ROLE_NAME
            || $this->boolean($role, 'is_system')
            || $this->string($role, 'status') !== Role::STATUS_ACTIVE
            || $this->integer($role, 'version') !== 1
        ) {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }

        $permissionKeys = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.organization_id', $organizationId)
            ->where('role_permissions.role_id', $this->string($role, 'id'))
            ->orderBy('permissions.key')
            ->lockForUpdate()
            ->pluck('permissions.key')
            ->all();
        $expectedPermissions = self::PERMISSION_KEYS;
        sort($expectedPermissions);
        $assignments = DB::table('membership_roles')
            ->where('organization_id', $organizationId)
            ->where('role_id', $this->string($role, 'id'))
            ->orderBy('membership_id')
            ->lockForUpdate()
            ->pluck('membership_id')
            ->all();
        $appointments = DB::table('authority_appointments')
            ->where('organization_id', $organizationId)
            ->where('program_id', $this->string($program, 'id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->all();

        if (
            $permissionKeys !== $expectedPermissions
            || $assignments !== [$membershipId]
            || count($appointments) !== 1
        ) {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }

        $appointment = $appointments[0];
        $events = DB::table('authority_appointment_events')
            ->where('organization_id', $organizationId)
            ->where('authority_appointment_id', $this->string($appointment, 'id'))
            ->orderBy('effective_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('event_type')
            ->all();

        if (
            ! hash_equals(
                $this->string($appointment, 'appointee_user_id'),
                self::USER_ID,
            )
            || ! hash_equals(
                $this->string($appointment, 'appointee_membership_id'),
                $membershipId,
            )
            || $this->string($appointment, 'authority_kind')
                !== AuthorityKind::RightsConfirmer->value
            || $this->string($appointment, 'scope_type') !== 'program'
            || $appointment->valid_until !== null
            || $this->string($appointment, 'qualification_basis')
                !== self::OWNER_EVIDENCE_REFERENCE
            || $this->string($appointment, 'approval_evidence_key')
                !== self::CONSOLIDATED_AUTHORITY_REFERENCE
            || ! in_array($events, [['appointed'], ['appointed', 'accepted']], true)
        ) {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }

        return [$program, $role, $appointment];
    }

    private function recordBootstrapEvidence(
        string $organizationId,
        string $programId,
        string $roleId,
        string $membershipId,
        string $appointmentId,
    ): void {
        $this->audit->recordTenant(
            'lpl01.pilot_authority_bootstrapped',
            'program',
            $programId,
        );
        $this->outbox->recordTenant(
            'lpl01.pilot_authority_bootstrapped',
            'program',
            $programId,
            [
                'organization_id' => $organizationId,
                'program_id' => $programId,
                'role_id' => $roleId,
                'membership_id' => $membershipId,
                'authority_appointment_id' => $appointmentId,
            ],
        );
    }

    private function string(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (! is_string($value) || $value === '') {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }

        return $value;
    }

    private function integer(stdClass $row, string $property): int
    {
        $value = $row->{$property} ?? null;

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new LocalLplPilotAuthorityBootstrapRejected;
        }

        return (int) $value;
    }

    private function boolean(stdClass $row, string $property): bool
    {
        return in_array($row->{$property} ?? null, [true, 1, '1'], true);
    }
}
