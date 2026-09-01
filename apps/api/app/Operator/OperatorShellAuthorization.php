<?php

declare(strict_types=1);

namespace App\Operator;

use App\Models\User;
use App\Operator\Exceptions\OperatorShellRejected;
use App\Operator\LearnerAdministration\LearnerAdministrationAuthorization;
use App\Operator\OrganizationAdministration\OrganizationAdministrationAuthorization;
use App\Tenancy\OrganizationAuthorization;

final readonly class OperatorShellAuthorization
{
    public function __construct(
        private OrganizationAuthorization $authorization,
        private OrganizationAdministrationAuthorization $organizationAdministration,
    ) {}

    /**
     * @return array{
     *     content: bool,
     *     media: bool,
     *     audit: bool,
     *     support: bool,
     *     people_panels: list<string>,
     *     learner_panels: list<string>,
     *     entitlement_panels: list<string>,
     *     search_available: bool
     * }
     */
    public function access(User $actor): array
    {
        $content = false;

        foreach (OperatorContentAuthorization::CONTENT_READ_PERMISSIONS as $permission) {
            if ($this->authorization->allows($actor, $permission)) {
                $content = true;

                break;
            }
        }

        $audit = $this->authorization->allows(
            $actor,
            OperatorContentAuthorization::AUDIT_READ_PERMISSION,
        );
        $media = false;

        foreach ([
            'media.library.read',
            'media.upload',
            'media.quarantine.review',
            'media.metadata.manage',
            'media.replace',
            'media.retire',
        ] as $permission) {
            if ($this->authorization->allows($actor, $permission)) {
                $media = true;

                break;
            }
        }
        $support = $this->authorization->allows(
            $actor,
            OperatorPrivacyAuthorization::SUPPORT_DIAGNOSTICS_READ,
        );
        $peoplePanels = [];
        $panelPermissions = [
            'organization_settings' => OrganizationAdministrationAuthorization::ORGANIZATION_READ,
            'branches' => OrganizationAdministrationAuthorization::BRANCHES_READ,
            'cohorts' => OrganizationAdministrationAuthorization::COHORTS_READ,
            'staff_memberships' => OrganizationAdministrationAuthorization::MEMBERSHIPS_READ,
            'institution_invites' => OrganizationAdministrationAuthorization::INVITATIONS_MANAGE,
            'roles' => OrganizationAdministrationAuthorization::ROLES_MANAGE,
            'trainer_profiles' => OrganizationAdministrationAuthorization::TRAINERS_READ,
        ];

        foreach ($panelPermissions as $panel => $permission) {
            if ($this->authorization->allows($actor, $permission)) {
                $peoplePanels[] = $panel;
            }
        }

        if (
            $this->authorization->allows(
                $actor,
                OrganizationAdministrationAuthorization::APPOINTMENTS_MANAGE,
            )
            || $this->organizationAdministration->hasSelfAppointment($actor)
        ) {
            $peoplePanels[] = 'authority_appointments';
        }

        if (
            $this->authorization->allows(
                $actor,
                OrganizationAdministrationAuthorization::MEMBERSHIPS_READ,
            )
            && $this->authorization->allows(
                $actor,
                OrganizationAdministrationAuthorization::MEMBERSHIP_SESSIONS_REVOKE,
            )
        ) {
            $peoplePanels[] = 'membership_sessions';
        }

        $learnerPanels = [];
        $mayReadLearners = $this->authorization->allows(
            $actor,
            LearnerAdministrationAuthorization::LEARNERS_READ,
        );

        if ($mayReadLearners) {
            $learnerPanels[] = 'learner_directory';

            if ($this->authorization->allows(
                $actor,
                LearnerAdministrationAuthorization::ENROLLMENTS_READ,
            )) {
                $learnerPanels[] = 'learner_enrollments';
            }
        }

        $mayManageLearnerInvitations = true;

        foreach (LearnerAdministrationAuthorization::INVITATION_PERMISSIONS as $permission) {
            if (! $this->authorization->allows($actor, $permission)) {
                $mayManageLearnerInvitations = false;

                break;
            }
        }

        if ($mayManageLearnerInvitations) {
            $learnerPanels[] = 'learner_invitations';
        }

        $entitlementPanels = [];

        if ($this->authorization->allows(
            $actor,
            'entitlement_plans.read',
        )) {
            $entitlementPanels[] = 'entitlement_plans';
        }

        if ($this->authorization->allows(
            $actor,
            'entitlements.read',
        )) {
            $entitlementPanels[] = 'entitlement_directory';
        }

        if (
            ! $content
            && ! $media
            && ! $audit
            && ! $support
            && $peoplePanels === []
            && $learnerPanels === []
            && $entitlementPanels === []
        ) {
            throw new OperatorShellRejected;
        }

        return [
            'content' => $content,
            'media' => $media,
            'audit' => $audit,
            'support' => $support,
            'people_panels' => $peoplePanels,
            'learner_panels' => $learnerPanels,
            'entitlement_panels' => $entitlementPanels,
            'search_available' => $content || $audit || $support,
        ];
    }
}
