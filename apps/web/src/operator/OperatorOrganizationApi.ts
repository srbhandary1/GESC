import {
  defaultAuthenticationRuntime,
  type ApiCsrfProtection,
} from '../auth/defaultAuthentication'

export type OperatorOrganizationPanelKey =
  | 'organization_settings'
  | 'branches'
  | 'cohorts'
  | 'staff_memberships'
  | 'institution_invites'
  | 'roles'
  | 'trainer_profiles'
  | 'authority_appointments'
  | 'membership_sessions'

export type OperatorOrganizationAction =
  | 'branch.create'
  | 'branch.update'
  | 'branch.archive'
  | 'cohort.create'
  | 'cohort.update'
  | 'cohort.archive'
  | 'membership.assign_role'
  | 'membership.revoke_role'
  | 'membership.deactivate'
  | 'membership.reactivate'
  | 'invitation.issue'
  | 'invitation.revoke'
  | 'role.create'
  | 'role.update'
  | 'role.retire'
  | 'trainer.assign_cohort'
  | 'trainer.revoke_cohort'
  | 'appointment.create'
  | 'appointment.accept'
  | 'appointment.surrender'
  | 'appointment.revoke'
  | 'membership_session.revoke'

export interface OperatorCollection<T> {
  readonly data: readonly T[]
  readonly next_cursor: string | null
  readonly allowed_actions: readonly OperatorOrganizationAction[]
}

export interface OperatorStaffDirectoryFilters {
  readonly display_name_prefix?: string
  readonly status?: OperatorStaffMembership['status']
}

export interface OperatorResource<T> {
  readonly data: T
}

export interface OperatorCommandResponse<T> {
  readonly data: T
  readonly replayed: boolean
}

export interface OperatorOrganization {
  readonly id: string
  readonly name: string
  readonly kind: 'institution' | 'platform_direct'
  readonly status: 'active' | 'closed' | 'suspended'
  readonly timezone: string
  readonly allowed_actions: readonly OperatorOrganizationAction[]
}

export interface OperatorBranch {
  readonly id: string
  readonly key: string
  readonly name: string
  readonly status: 'active' | 'archived'
  readonly version: number
  readonly created_at: string
  readonly updated_at: string
  readonly allowed_actions: readonly OperatorOrganizationAction[]
}

export interface OperatorBranchSummary {
  readonly id: string
  readonly key: string
  readonly name: string
}

export interface OperatorCohort {
  readonly id: string
  readonly key: string
  readonly name: string
  readonly status: 'active' | 'archived'
  readonly branch: OperatorBranchSummary | null
  readonly version: number
  readonly created_at: string
  readonly updated_at: string
  readonly allowed_actions: readonly OperatorOrganizationAction[]
}

export interface OperatorRoleSummary {
  readonly id: string
  readonly key: string
  readonly name: string
  readonly is_system: boolean
}

export interface OperatorStaffMembership {
  readonly membership_id: string
  readonly display_name: string | null
  readonly membership_type: 'organization_admin' | 'trainer'
  readonly status: 'active' | 'ended' | 'suspended'
  readonly joined_at: string
  readonly ended_at: string | null
  readonly roles: readonly OperatorRoleSummary[]
  readonly version: number
  readonly allowed_actions: readonly OperatorOrganizationAction[]
}

export interface OperatorInvitation {
  readonly id: string
  readonly membership_type: 'organization_admin' | 'trainer'
  readonly status: 'accepted' | 'expired' | 'pending' | 'revoked'
  readonly expires_at: string
  readonly accepted_at: string | null
  readonly accepted_membership_id?: string | null
  readonly created_at: string
  readonly updated_at: string
  readonly version: number
  readonly allowed_actions: readonly OperatorOrganizationAction[]
}

export interface OperatorInvitationIssuance
  extends OperatorCommandResponse<OperatorInvitation> {
  readonly activation_code: string | null
}

export interface OperatorDelegablePermission {
  readonly key: string
  readonly description: string
}

export interface OperatorRole {
  readonly id: string
  readonly key: string
  readonly name: string
  readonly is_system: boolean
  readonly status: 'active' | 'retired'
  readonly permission_keys: readonly string[]
  readonly assignment_count: number
  readonly version: number
  readonly allowed_actions: readonly OperatorOrganizationAction[]
}

export interface OperatorRoleCollection extends OperatorCollection<OperatorRole> {
  readonly delegable_permissions: readonly OperatorDelegablePermission[]
}

export type OperatorAuthorityKind =
  | 'english_academic_reviewer'
  | 'kannada_reviewer'
  | 'rights_confirmer'

export interface OperatorAuthorityAppointment {
  readonly id: string
  readonly authority_kind: OperatorAuthorityKind
  readonly scope_type: 'organization' | 'program'
  readonly program_id: string | null
  readonly valid_from: string
  readonly valid_until: string | null
  readonly state: 'active' | 'expired' | 'pending' | 'revoked' | 'surrendered'
  readonly state_fingerprint: string
  readonly allowed_actions: readonly OperatorOrganizationAction[]
}

export interface OperatorAuthorityAppointmentDetail
  extends OperatorAuthorityAppointment {
  readonly qualification_basis: string
  readonly approval_evidence_key: string
}

export interface OperatorCohortSummary {
  readonly id: string
  readonly key: string
  readonly name: string
  readonly status: 'active' | 'archived'
  readonly version: number
}

export interface OperatorTrainerProfile {
  readonly membership_id: string
  readonly display_name: string | null
  readonly membership_type: 'trainer'
  readonly status: 'active' | 'ended' | 'suspended'
  readonly joined_at: string
  readonly ended_at: string | null
  readonly roles: readonly OperatorRoleSummary[]
  readonly cohorts: readonly OperatorCohortSummary[]
  readonly appointments: readonly OperatorAuthorityAppointment[]
  readonly version: number
  readonly allowed_actions: readonly OperatorOrganizationAction[]
}

export interface OperatorMembershipSessions {
  readonly membership_id: string
  readonly active_web_session_count: number
  readonly active_android_family_count: number
  readonly latest_activity_at: string | null
  readonly state_fingerprint: string
  readonly allowed_actions: readonly OperatorOrganizationAction[]
}

export interface OperatorMembershipSessionRevocation
  extends OperatorMembershipSessions {
  readonly revoked_web_session_count: number
  readonly revoked_android_family_count: number
}

export interface OperatorOrganizationApi {
  getOrganization(signal?: AbortSignal): Promise<OperatorOrganization>
  listBranches(
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorCollection<OperatorBranch>>
  listCohorts(
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorCollection<OperatorCohort>>
  listMemberships(
    filters?: OperatorStaffDirectoryFilters,
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorCollection<OperatorStaffMembership>>
  listInvitations(
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorCollection<OperatorInvitation>>
  listRoles(cursor?: string, signal?: AbortSignal): Promise<OperatorRoleCollection>
  listTrainerProfiles(
    filters?: OperatorStaffDirectoryFilters,
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorCollection<OperatorTrainerProfile>>
  listAuthorityAppointments(
    membershipId: string,
    cursor?: string,
    signal?: AbortSignal,
  ): Promise<OperatorCollection<OperatorAuthorityAppointment>>
  getAuthorityAppointment(
    membershipId: string,
    appointmentId: string,
    signal?: AbortSignal,
  ): Promise<OperatorAuthorityAppointmentDetail>
  getMembershipSessions(
    membershipId: string,
    signal?: AbortSignal,
  ): Promise<OperatorMembershipSessions>
  createBranch(
    body: Readonly<{ key: string; name: string; expected_version: 0 }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorBranch>>
  updateBranch(
    branchId: string,
    body: Readonly<{ name: string; expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorBranch>>
  archiveBranch(
    branchId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorBranch>>
  createCohort(
    body: Readonly<{
      key: string
      name: string
      branch_id: string | null
      expected_version: 0
    }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorCohort>>
  updateCohort(
    cohortId: string,
    body: Readonly<{
      name: string
      branch_id: string | null
      expected_version: number
    }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorCohort>>
  archiveCohort(
    cohortId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorCohort>>
  issueInvitation(
    body: Readonly<{
      membership_type: 'organization_admin' | 'trainer'
      expires_in_hours: 24 | 72 | 168
      expected_version: 0
    }>,
    idempotencyKey: string,
  ): Promise<OperatorInvitationIssuance>
  revokeInvitation(
    invitationId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorInvitation>>
  createRole(
    body: Readonly<{
      key: string
      name: string
      permission_keys: readonly string[]
      expected_version: 0
    }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorRole>>
  updateRole(
    roleId: string,
    body: Readonly<{
      name: string
      permission_keys: readonly string[]
      expected_version: number
    }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorRole>>
  retireRole(
    roleId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorRole>>
  assignMembershipRole(
    membershipId: string,
    body: Readonly<{
      role_id: string
      expected_membership_version: number
      expected_role_version: number
    }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorStaffMembership>>
  createStaffAccount?(
    body: Readonly<{
      username: string
      password: string
      display_name?: string | null
      membership_type: 'organization_admin' | 'trainer'
      role_id?: string | null
      expected_version: 0
    }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorStaffMembership>>
  revokeMembershipRole(
    membershipId: string,
    body: Readonly<{
      role_id: string
      expected_membership_version: number
      expected_role_version: number
    }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorStaffMembership>>
  deactivateMembership(
    membershipId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorStaffMembership>>
  reactivateMembership(
    membershipId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorStaffMembership>>
  assignTrainerCohort(
    membershipId: string,
    body: Readonly<{
      cohort_id: string
      expected_membership_version: number
      expected_cohort_version: number
    }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorTrainerProfile>>
  revokeTrainerCohort(
    membershipId: string,
    body: Readonly<{
      cohort_id: string
      expected_membership_version: number
      expected_cohort_version: number
    }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorTrainerProfile>>
  createAuthorityAppointment(
    membershipId: string,
    body: Readonly<{
      authority_kind: OperatorAuthorityKind
      scope_type: 'organization' | 'program'
      program_id: string | null
      valid_from: string
      valid_until: string | null
      qualification_basis: string
      approval_evidence_key: string
      expected_membership_version: number
    }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorAuthorityAppointmentDetail>>
  acceptAuthorityAppointment(
    membershipId: string,
    appointmentId: string,
    body: Readonly<{ state_fingerprint: string }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorAuthorityAppointmentDetail>>
  surrenderAuthorityAppointment(
    membershipId: string,
    appointmentId: string,
    body: Readonly<{ state_fingerprint: string }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorAuthorityAppointmentDetail>>
  revokeAuthorityAppointment(
    membershipId: string,
    appointmentId: string,
    body: Readonly<{
      reason_key: 'operator_revoked'
      state_fingerprint: string
    }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorAuthorityAppointmentDetail>>
  revokeMembershipSessions(
    membershipId: string,
    body: Readonly<{ state_fingerprint: string }>,
    idempotencyKey: string,
  ): Promise<OperatorCommandResponse<OperatorMembershipSessionRevocation>>
}

export type OperatorOrganizationErrorKind =
  | 'active_dependency'
  | 'forbidden'
  | 'fresh_mfa'
  | 'idempotency_conflict'
  | 'last_manager_required'
  | 'self_target_forbidden'
  | 'stale_state'
  | 'unavailable'
  | 'validation'

export class OperatorOrganizationApiError extends Error {
  readonly kind: OperatorOrganizationErrorKind

  constructor(kind: OperatorOrganizationErrorKind) {
    super('The organization administration request could not be completed.')
    this.name = 'OperatorOrganizationApiError'
    this.kind = kind
  }
}

export interface WebOperatorOrganizationApiOptions {
  readonly baseUrl?: string
  readonly csrfProtection?: ApiCsrfProtection
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

export class WebOperatorOrganizationApi implements OperatorOrganizationApi {
  readonly #baseUrl: string
  readonly #csrfProtection: ApiCsrfProtection
  readonly #fetch: typeof fetch
  readonly #readCookie: () => string

  constructor(options: WebOperatorOrganizationApiOptions = {}) {
    this.#baseUrl = options.baseUrl ?? '/api/v1'
    this.#csrfProtection = options.csrfProtection ?? 'sanctum-cookie'
    this.#fetch = options.fetch ?? globalThis.fetch.bind(globalThis)
    this.#readCookie =
      options.readCookie ??
      (() => (typeof document === 'undefined' ? '' : document.cookie))
  }

  async getOrganization(signal?: AbortSignal): Promise<OperatorOrganization> {
    return (await this.#get<OperatorResource<OperatorOrganization>>(
      '/operator/organization',
      signal,
    )).data
  }

  listBranches(cursor?: string, signal?: AbortSignal) {
    return this.#get<OperatorCollection<OperatorBranch>>(
      this.#collectionPath('/operator/branches', cursor),
      signal,
    )
  }

  listCohorts(cursor?: string, signal?: AbortSignal) {
    return this.#get<OperatorCollection<OperatorCohort>>(
      this.#collectionPath('/operator/cohorts', cursor),
      signal,
    )
  }

  listMemberships(
    filters: OperatorStaffDirectoryFilters = {},
    cursor?: string,
    signal?: AbortSignal,
  ) {
    return this.#get<OperatorCollection<OperatorStaffMembership>>(
      this.#collectionPath(
        '/operator/memberships',
        cursor,
        filters as Readonly<Record<string, string | undefined>>,
      ),
      signal,
    )
  }

  listInvitations(cursor?: string, signal?: AbortSignal) {
    return this.#get<OperatorCollection<OperatorInvitation>>(
      this.#collectionPath('/operator/institution-invites', cursor),
      signal,
    )
  }

  listRoles(cursor?: string, signal?: AbortSignal) {
    return this.#get<OperatorRoleCollection>(
      this.#collectionPath('/operator/roles', cursor),
      signal,
    )
  }

  listTrainerProfiles(
    filters: OperatorStaffDirectoryFilters = {},
    cursor?: string,
    signal?: AbortSignal,
  ) {
    return this.#get<OperatorCollection<OperatorTrainerProfile>>(
      this.#collectionPath(
        '/operator/trainer-profiles',
        cursor,
        filters as Readonly<Record<string, string | undefined>>,
      ),
      signal,
    )
  }

  listAuthorityAppointments(
    membershipId: string,
    cursor?: string,
    signal?: AbortSignal,
  ) {
    return this.#get<OperatorCollection<OperatorAuthorityAppointment>>(
      this.#collectionPath(
        `/operator/trainer-profiles/${this.#segment(membershipId)}/authority-appointments`,
        cursor,
      ),
      signal,
    )
  }

  async getAuthorityAppointment(
    membershipId: string,
    appointmentId: string,
    signal?: AbortSignal,
  ): Promise<OperatorAuthorityAppointmentDetail> {
    return (
      await this.#get<OperatorResource<OperatorAuthorityAppointmentDetail>>(
        `/operator/trainer-profiles/${this.#segment(membershipId)}/authority-appointments/${this.#segment(appointmentId)}`,
        signal,
      )
    ).data
  }

  async getMembershipSessions(
    membershipId: string,
    signal?: AbortSignal,
  ): Promise<OperatorMembershipSessions> {
    return (
      await this.#get<OperatorResource<OperatorMembershipSessions>>(
        `/operator/membership-sessions/${this.#segment(membershipId)}`,
        signal,
      )
    ).data
  }

  createBranch(
    body: Readonly<{ key: string; name: string; expected_version: 0 }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorBranch>>(
      '/operator/branches',
      body,
      idempotencyKey,
    )
  }

  updateBranch(
    branchId: string,
    body: Readonly<{ name: string; expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorBranch>>(
      `/operator/branches/${this.#segment(branchId)}/updates`,
      body,
      idempotencyKey,
    )
  }

  archiveBranch(
    branchId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorBranch>>(
      `/operator/branches/${this.#segment(branchId)}/archivals`,
      body,
      idempotencyKey,
    )
  }

  createCohort(
    body: Readonly<{
      key: string
      name: string
      branch_id: string | null
      expected_version: 0
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorCohort>>(
      '/operator/cohorts',
      body,
      idempotencyKey,
    )
  }

  updateCohort(
    cohortId: string,
    body: Readonly<{
      name: string
      branch_id: string | null
      expected_version: number
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorCohort>>(
      `/operator/cohorts/${this.#segment(cohortId)}/updates`,
      body,
      idempotencyKey,
    )
  }

  archiveCohort(
    cohortId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorCohort>>(
      `/operator/cohorts/${this.#segment(cohortId)}/archivals`,
      body,
      idempotencyKey,
    )
  }

  issueInvitation(
    body: Readonly<{
      membership_type: 'organization_admin' | 'trainer'
      expires_in_hours: 24 | 72 | 168
      expected_version: 0
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorInvitationIssuance>(
      '/operator/institution-invites',
      body,
      idempotencyKey,
    )
  }

  revokeInvitation(
    invitationId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorInvitation>>(
      `/operator/institution-invites/${this.#segment(invitationId)}/revocations`,
      body,
      idempotencyKey,
    )
  }

  createRole(
    body: Readonly<{
      key: string
      name: string
      permission_keys: readonly string[]
      expected_version: 0
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorRole>>(
      '/operator/roles',
      body,
      idempotencyKey,
    )
  }

  updateRole(
    roleId: string,
    body: Readonly<{
      name: string
      permission_keys: readonly string[]
      expected_version: number
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorRole>>(
      `/operator/roles/${this.#segment(roleId)}/updates`,
      body,
      idempotencyKey,
    )
  }

  retireRole(
    roleId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorRole>>(
      `/operator/roles/${this.#segment(roleId)}/retirements`,
      body,
      idempotencyKey,
    )
  }

  assignMembershipRole(
    membershipId: string,
    body: Readonly<{
      role_id: string
      expected_membership_version: number
      expected_role_version: number
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorStaffMembership>>(
      `/operator/memberships/${this.#segment(membershipId)}/role-assignments`,
      body,
      idempotencyKey,
    )
  }

  createStaffAccount(
    body: Readonly<{
      username: string
      password: string
      display_name?: string | null
      membership_type: 'organization_admin' | 'trainer'
      role_id?: string | null
      expected_version: 0
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorStaffMembership>>(
      '/operator/memberships',
      body,
      idempotencyKey,
    )
  }

  revokeMembershipRole(
    membershipId: string,
    body: Readonly<{
      role_id: string
      expected_membership_version: number
      expected_role_version: number
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorStaffMembership>>(
      `/operator/memberships/${this.#segment(membershipId)}/role-revocations`,
      body,
      idempotencyKey,
    )
  }

  deactivateMembership(
    membershipId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorStaffMembership>>(
      `/operator/memberships/${this.#segment(membershipId)}/deactivations`,
      body,
      idempotencyKey,
    )
  }

  reactivateMembership(
    membershipId: string,
    body: Readonly<{ expected_version: number }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorStaffMembership>>(
      `/operator/memberships/${this.#segment(membershipId)}/reactivations`,
      body,
      idempotencyKey,
    )
  }

  assignTrainerCohort(
    membershipId: string,
    body: Readonly<{
      cohort_id: string
      expected_membership_version: number
      expected_cohort_version: number
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorTrainerProfile>>(
      `/operator/trainer-profiles/${this.#segment(membershipId)}/cohort-assignments`,
      body,
      idempotencyKey,
    )
  }

  revokeTrainerCohort(
    membershipId: string,
    body: Readonly<{
      cohort_id: string
      expected_membership_version: number
      expected_cohort_version: number
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<OperatorCommandResponse<OperatorTrainerProfile>>(
      `/operator/trainer-profiles/${this.#segment(membershipId)}/cohort-revocations`,
      body,
      idempotencyKey,
    )
  }

  createAuthorityAppointment(
    membershipId: string,
    body: Readonly<{
      authority_kind: OperatorAuthorityKind
      scope_type: 'organization' | 'program'
      program_id: string | null
      valid_from: string
      valid_until: string | null
      qualification_basis: string
      approval_evidence_key: string
      expected_membership_version: number
    }>,
    idempotencyKey: string,
  ) {
    return this.#post<
      OperatorCommandResponse<OperatorAuthorityAppointmentDetail>
    >(
      `/operator/trainer-profiles/${this.#segment(membershipId)}/authority-appointments`,
      body,
      idempotencyKey,
    )
  }

  acceptAuthorityAppointment(
    membershipId: string,
    appointmentId: string,
    body: Readonly<{ state_fingerprint: string }>,
    idempotencyKey: string,
  ) {
    return this.#appointmentStateCommand(
      membershipId,
      appointmentId,
      'acceptances',
      body,
      idempotencyKey,
    )
  }

  surrenderAuthorityAppointment(
    membershipId: string,
    appointmentId: string,
    body: Readonly<{ state_fingerprint: string }>,
    idempotencyKey: string,
  ) {
    return this.#appointmentStateCommand(
      membershipId,
      appointmentId,
      'surrenders',
      body,
      idempotencyKey,
    )
  }

  revokeAuthorityAppointment(
    membershipId: string,
    appointmentId: string,
    body: Readonly<{
      reason_key: 'operator_revoked'
      state_fingerprint: string
    }>,
    idempotencyKey: string,
  ) {
    return this.#appointmentStateCommand(
      membershipId,
      appointmentId,
      'revocations',
      body,
      idempotencyKey,
    )
  }

  revokeMembershipSessions(
    membershipId: string,
    body: Readonly<{ state_fingerprint: string }>,
    idempotencyKey: string,
  ) {
    return this.#post<
      OperatorCommandResponse<OperatorMembershipSessionRevocation>
    >(
      `/operator/membership-sessions/${this.#segment(membershipId)}/revocations`,
      body,
      idempotencyKey,
    )
  }

  #appointmentStateCommand(
    membershipId: string,
    appointmentId: string,
    statePath: 'acceptances' | 'revocations' | 'surrenders',
    body: Readonly<Record<string, string>>,
    idempotencyKey: string,
  ) {
    return this.#post<
      OperatorCommandResponse<OperatorAuthorityAppointmentDetail>
    >(
      `/operator/trainer-profiles/${this.#segment(membershipId)}/authority-appointments/${this.#segment(appointmentId)}/${statePath}`,
      body,
      idempotencyKey,
    )
  }

  #collectionPath(
    path: string,
    cursor?: string,
    filters?: Readonly<Record<string, string | undefined>>,
  ): string {
    const parameters = new URLSearchParams({ limit: '25' })

    if (filters !== undefined) {
      for (const [key, value] of Object.entries(filters)) {
        if (value !== undefined && value !== '') {
          parameters.set(key, value)
        }
      }
    }

    if (cursor !== undefined) {
      parameters.set('cursor', cursor)
    }

    return `${path}?${parameters.toString()}`
  }

  #segment(value: string): string {
    return encodeURIComponent(value)
  }

  async #get<T>(path: string, signal?: AbortSignal): Promise<T> {
    const response = await this.#fetch(`${this.#baseUrl}${path}`, {
      credentials: 'include',
      headers: this.#headers(),
      method: 'GET',
      signal,
    })

    return this.#response<T>(response)
  }

  async #post<T>(
    path: string,
    body: Readonly<object>,
    idempotencyKey: string,
  ): Promise<T> {
    const response = await this.#fetch(`${this.#baseUrl}${path}`, {
      body: JSON.stringify(body),
      credentials: 'include',
      headers: {
        ...this.#headers(),
        'Content-Type': 'application/json',
        'Idempotency-Key': idempotencyKey,
      },
      method: 'POST',
    })

    return this.#response<T>(response)
  }

  #headers(): Readonly<Record<string, string>> {
    return {
      Accept: 'application/json',
      ...(this.#csrfProtection === 'sanctum-cookie'
        ? { 'X-XSRF-TOKEN': this.#xsrfToken() }
        : {}),
    }
  }

  async #response<T>(response: Response): Promise<T> {
    if (response.ok) {
      try {
        return (await response.json()) as T
      } catch {
        throw new OperatorOrganizationApiError('unavailable')
      }
    }

    let messageKey: string | null = null

    try {
      const problem = (await response.json()) as { message_key?: unknown }
      messageKey =
        typeof problem.message_key === 'string' ? problem.message_key : null
    } catch {
      // Deliberately discard reflected and malformed failure bodies.
    }

    if (
      response.status === 403 &&
      (messageKey === 'problem.fresh_mfa_required'
        || messageKey === 'problem.fresh_password_confirmation_required')
    ) {
      throw new OperatorOrganizationApiError('fresh_mfa')
    }

    if (response.status === 403 && messageKey === 'problem.forbidden') {
      throw new OperatorOrganizationApiError('forbidden')
    }

    if (response.status === 409) {
      const conflictKinds = {
        'problem.organization_administration.active_dependency':
          'active_dependency',
        'problem.organization_administration.idempotency_conflict':
          'idempotency_conflict',
        'problem.organization_administration.last_manager_required':
          'last_manager_required',
        'problem.organization_administration.stale_state': 'stale_state',
      } as const satisfies Readonly<Record<string, OperatorOrganizationErrorKind>>
      const kind = messageKey === null ? undefined : conflictKinds[messageKey as keyof typeof conflictKinds]

      if (kind !== undefined) {
        throw new OperatorOrganizationApiError(kind)
      }
    }

    if (
      response.status === 422 &&
      messageKey ===
        'problem.organization_administration.self_target_forbidden'
    ) {
      throw new OperatorOrganizationApiError('self_target_forbidden')
    }

    if (
      response.status === 422 &&
      messageKey === 'problem.validation_failed'
    ) {
      throw new OperatorOrganizationApiError('validation')
    }

    throw new OperatorOrganizationApiError('unavailable')
  }

  #xsrfToken(): string {
    const token = this.#readCookie()
      .split(';')
      .map((part) => part.trim())
      .find((part) => part.startsWith('XSRF-TOKEN='))
      ?.slice('XSRF-TOKEN='.length)

    return token === undefined ? '' : decodeURIComponent(token)
  }
}

export const defaultOperatorOrganizationApi =
  new WebOperatorOrganizationApi({
    baseUrl: defaultAuthenticationRuntime.apiBaseUrl,
    csrfProtection: defaultAuthenticationRuntime.csrfProtection,
    fetch: defaultAuthenticationRuntime.apiFetch,
  })
