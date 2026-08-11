<?php

declare(strict_types=1);

namespace App\Tenancy;

final class PermissionCatalogue
{
    /**
     * The code-owned tenant permission boundary. Database rows outside this
     * catalogue never become delegable authority.
     *
     * @var array<string, string>
     */
    public const array DEFINITIONS = [
        'organization.settings.read' => 'View safe current-organization metadata.',
        'branches.read' => 'View organization branches.',
        'branches.manage' => 'Create, update and archive organization branches.',
        'cohorts.manage' => 'Create and manage organization cohorts.',
        'cohorts.read' => 'View organization cohorts.',
        'enrollments.manage' => 'Create and manage organization enrollments.',
        'enrollments.read' => 'View organization enrollments.',
        'institution_invites.manage' => 'Issue and revoke institution invitations.',
        'learners.read' => 'View safe current-organization learner profiles.',
        'learners.profile.correct' => 'Append governed learner goal corrections.',
        'learners.lifecycle.manage' => 'Manage learner invitation and membership lifecycle.',
        'memberships.manage' => 'Manage organization memberships.',
        'memberships.read' => 'View organization memberships.',
        'roles.manage' => 'Manage organization roles and permission grants.',
        'trainer_profiles.read' => 'View safe derived trainer profiles.',
        'trainer_profiles.manage' => 'Manage trainer cohort assignments.',
        'membership_sessions.revoke' => 'Revoke credentials selected for another staff membership.',
        'curriculum.author' => 'Create organization-owned curriculum drafts and revisions.',
        'curriculum.translate.kn' => 'Create Kannada variants for organization curriculum.',
        'curriculum.review.en' => 'Record appointed English academic review evidence.',
        'curriculum.review.kn' => 'Record appointed Kannada review evidence.',
        'curriculum.publish' => 'Publish and roll back reviewed curriculum releases.',
        'curriculum.share.manage' => 'Issue and withdraw curriculum share grants.',
        'curriculum.shared.read' => 'Preview explicitly shared curriculum releases.',
        'curriculum.shared.clone' => 'Clone an explicitly shared release into an organization draft.',
        'enrollments.curriculum.assign' => 'Pin organization enrollments to an authorized publication.',
        'curriculum.authority.manage' => 'Manage scoped curriculum authority appointments.',
        'media.library.read' => 'View the organization private media library.',
        'media.upload' => 'Upload bounded media into private quarantine.',
        'media.quarantine.review' => 'Accept or reject clean quarantined media.',
        'media.metadata.manage' => 'Manage append-only media accessibility metadata.',
        'media.replace' => 'Record immutable media replacement lineage.',
        'media.retire' => 'Retire media from new attachment and publication.',
        'media.rights.approve' => 'Confirm media-rights evidence under an appointment.',
        'curriculum.suspend' => 'Suspend or resume organization curriculum delivery.',
        'learning.participate' => 'Use assigned learner missions and record self-owned learning evidence.',
        'audit.read' => 'List organization-scoped audit digests for privileged operator actions.',
        'support.diagnostics.read' => 'List organization-scoped support diagnostics digests without learner content bodies.',
        'privacy.export.request' => 'Request organization-scoped learner identifier exports without audio or transcripts.',
        'privacy.erasure.request' => 'Request organization-scoped learner account-erasure and retention workflows.',
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    private function __construct() {}
}
