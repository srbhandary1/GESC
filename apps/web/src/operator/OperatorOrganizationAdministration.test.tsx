import { cleanup, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { OperatorOrganizationAdministration } from './OperatorOrganizationAdministration'
import {
  OperatorOrganizationApiError,
  type OperatorBranch,
  type OperatorOrganizationApi,
  type OperatorRole,
  type OperatorStaffMembership,
} from './OperatorOrganizationApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('operator organization administration', () => {
  it('calls only manifest-authorized resources and keeps a successful panel available while another retries', async () => {
    const listBranches = vi
      .fn()
      .mockRejectedValueOnce(new Error('temporary'))
      .mockResolvedValueOnce(collection([branch()], ['branch.create']))
    const listInvitations = vi.fn(async () => collection([], ['invitation.issue']))
    const api = organizationApi({ listBranches, listInvitations })
    const user = userEvent.setup()

    renderAdministration(api, ['branches', 'institution_invites'])

    const invitations = await screen.findByRole('article', {
      name: 'Staff invitations',
    })
    expect(within(invitations).getByRole('button', { name: 'Issue invitation' }))
      .toBeInTheDocument()
    const branches = screen.getByRole('article', { name: 'Branches' })
    expect(within(branches).getByRole('alert')).toHaveTextContent(
      'This panel could not be loaded.',
    )
    expect(api.listCohorts).not.toHaveBeenCalled()
    expect(api.listMemberships).not.toHaveBeenCalled()
    expect(api.listRoles).not.toHaveBeenCalled()
    expect(api.listTrainerProfiles).not.toHaveBeenCalled()

    await user.click(within(branches).getByRole('button', { name: 'Try again' }))

    expect(await within(branches).findByText('Central Branch')).toBeInTheDocument()
    expect(within(invitations).getByRole('button', { name: 'Issue invitation' }))
      .toBeInTheDocument()
    expect(listBranches).toHaveBeenCalledTimes(2)
  })

  it('shows an initial invitation code once, never auto-copies or persists it, and hides it after acknowledgement', async () => {
    const user = userEvent.setup()
    const writeText = vi.fn(async () => undefined)
    Object.defineProperty(globalThis.navigator, 'clipboard', {
      configurable: true,
      value: { writeText },
    })
    const localStorageWrite = vi.spyOn(Storage.prototype, 'setItem')
    const issueInvitation = vi.fn(async () => ({
      activation_code: 'GE-ONE-TIME-CODE',
      data: invitation(),
      replayed: false,
    }))
    const api = organizationApi({
      issueInvitation,
      listInvitations: vi.fn(async () => collection([], ['invitation.issue'])),
    })

    renderAdministration(api, ['institution_invites'])

    await user.selectOptions(
      await screen.findByLabelText('Staff membership type'),
      'organization_admin',
    )
    await user.selectOptions(screen.getByLabelText('Invitation validity'), '24')
    await user.click(screen.getByRole('button', { name: 'Issue invitation' }))

    const code = await screen.findByText('GE-ONE-TIME-CODE')
    expect(code).toHaveAttribute('dir', 'ltr')
    expect(code.closest('[aria-live]')).toBeNull()
    expect(writeText).not.toHaveBeenCalled()
    expect(issueInvitation).toHaveBeenCalledWith(
      {
        expected_version: 0,
        expires_in_hours: 24,
        membership_type: 'organization_admin',
      },
      '11111111-2222-4333-8444-555555555555',
    )
    expect(localStorageWrite).not.toHaveBeenCalled()

    await user.click(screen.getByRole('button', { name: 'Copy code' }))
    expect(writeText).toHaveBeenCalledWith('GE-ONE-TIME-CODE')
    expect(await screen.findByText('The activation code was copied.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'I saved the code' }))
    expect(screen.queryByText('GE-ONE-TIME-CODE')).not.toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Issue invitation' }),
    ).toBeInTheDocument()
  })

  it('explains exact invitation replay without displaying a replacement secret', async () => {
    const user = userEvent.setup()
    const api = organizationApi({
      issueInvitation: vi.fn(async () => ({
        activation_code: null,
        data: invitation(),
        replayed: true,
      })),
      listInvitations: vi.fn(async () => collection([], ['invitation.issue'])),
    })

    renderAdministration(api, ['institution_invites'])
    await user.click(
      await screen.findByRole('button', { name: 'Issue invitation' }),
    )

    expect(
      await screen.findByRole('heading', { name: 'Invitation already issued' }),
    ).toBeInTheDocument()
    expect(
      screen.getByText(/activation code is no longer available/u),
    ).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Copy code' })).not.toBeInTheDocument()
  })

  it('restores focus after cancelling lifecycle confirmation and offers conflict reload', async () => {
    const user = userEvent.setup()
    const listBranches = vi.fn(async () =>
      collection([branch()], ['branch.create']),
    )
    const archiveBranch = vi.fn(async () => {
      throw new OperatorOrganizationApiError('stale_state')
    })
    const api = organizationApi({ archiveBranch, listBranches })

    renderAdministration(api, ['branches'])

    const archive = await screen.findByRole('button', { name: 'Archive' })
    await user.click(archive)
    await user.click(screen.getByRole('button', { name: 'Cancel' }))
    await waitFor(() => expect(archive).toHaveFocus())

    await user.click(archive)
    await user.click(screen.getByRole('button', { name: 'Confirm' }))

    expect(
      await screen.findByText(/record changed after it was loaded/u),
    ).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reload current data' }))
    expect(listBranches).toHaveBeenCalledTimes(2)
  })

  it('appends a bounded cursor page without replacing records already shown', async () => {
    const user = userEvent.setup()
    const listBranches = vi
      .fn()
      .mockResolvedValueOnce({
        ...collection([branch()], []),
        next_cursor: 'tenant-bound-next',
      })
      .mockResolvedValueOnce(
        collection([
          {
            ...branch(),
            id: '01J00000000000000000000012',
            key: 'south',
            name: 'South Branch',
          },
        ], []),
      )
    const api = organizationApi({ listBranches })

    renderAdministration(api, ['branches'])
    expect(await screen.findByText('Central Branch')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Load more' }))

    expect(await screen.findByText('South Branch')).toBeInTheDocument()
    expect(screen.getByText('Central Branch')).toBeInTheDocument()
    expect(listBranches).toHaveBeenLastCalledWith(
      'tenant-bound-next',
      expect.any(AbortSignal),
    )
  })

  it('joins minimized role summaries to the authorized role resource before revocation', async () => {
    const user = userEvent.setup()
    const membership = staffMembership()
    const currentRole = role()
    const revokeMembershipRole = vi.fn(async () => ({
      data: membership,
      replayed: false,
    }))
    const api = organizationApi({
      listMemberships: vi.fn(async () => collection([membership], [])),
      listRoles: vi.fn(async () => ({
        ...collection([currentRole], []),
        delegable_permissions: [],
      })),
      revokeMembershipRole,
    })

    renderAdministration(api, ['staff_memberships', 'roles'])

    const staff = await screen.findByRole('article', {
      name: 'Staff memberships',
    })
    await user.click(
      await within(staff).findByRole('button', { name: 'Nandini Rao' }),
    )
    await user.click(
      await within(staff).findByRole('button', { name: 'Revoke role' }),
    )
    await user.click(within(staff).getByRole('button', { name: 'Confirm' }))

    expect(revokeMembershipRole).toHaveBeenCalledWith(
      membership.membership_id,
      {
        expected_membership_version: membership.version,
        expected_role_version: currentRole.version,
        role_id: currentRole.id,
      },
      '11111111-2222-4333-8444-555555555555',
    )
  })

  it('fails closed on role mutation when the roles panel is not authorized', async () => {
    const membership = staffMembership()
    const listRoles = vi.fn()
    const api = organizationApi({
      listMemberships: vi.fn(async () => collection([membership], [])),
      listRoles,
    })

    renderAdministration(api, ['staff_memberships'])

    const staff = await screen.findByRole('article', {
      name: 'Staff memberships',
    })
    expect(within(staff).queryByRole('button', { name: 'Revoke role' }))
      .not.toBeInTheDocument()
    expect(listRoles).not.toHaveBeenCalled()
  })
})

function renderAdministration(
  api: OperatorOrganizationApi,
  panels: Parameters<typeof OperatorOrganizationAdministration>[0]['panels'],
) {
  return render(
    <OperatorOrganizationAdministration
      api={api}
      createCommandId={() => '11111111-2222-4333-8444-555555555555'}
      currentMembershipId="01J00000000000000000000099"
      locale="en-IN"
      localizationPolicy="development-preview"
      panels={panels}
    />,
  )
}

function collection<T>(
  data: readonly T[],
  allowedActions: Parameters<typeof actionList>[0],
) {
  return {
    allowed_actions: actionList(allowedActions),
    data,
    next_cursor: null,
  }
}

function actionList(
  actions: readonly import('./OperatorOrganizationApi').OperatorOrganizationAction[],
) {
  return actions
}

function branch(): OperatorBranch {
  return {
    allowed_actions: ['branch.update', 'branch.archive'],
    created_at: '2026-08-09T09:00:00Z',
    id: '01J00000000000000000000011',
    key: 'central',
    name: 'Central Branch',
    status: 'active',
    updated_at: '2026-08-09T10:00:00Z',
    version: 1,
  }
}

function invitation() {
  return {
    accepted_at: null,
    allowed_actions: ['invitation.revoke'] as const,
    created_at: '2026-08-09T10:00:00Z',
    expires_at: '2026-08-12T10:00:00Z',
    id: '01J00000000000000000000021',
    membership_type: 'trainer' as const,
    status: 'pending' as const,
    updated_at: '2026-08-09T10:00:00Z',
    version: 1,
  }
}

function staffMembership(): OperatorStaffMembership {
  return {
    allowed_actions: ['membership.revoke_role'],
    display_name: 'Nandini Rao',
    ended_at: null,
    joined_at: '2026-08-09T09:00:00Z',
    membership_id: '01J00000000000000000000031',
    membership_type: 'organization_admin',
    roles: [
      {
        id: '01J00000000000000000000041',
        is_system: false,
        key: 'branch-manager',
        name: 'Branch manager',
      },
    ],
    status: 'active',
    version: 3,
  }
}

function role(): OperatorRole {
  return {
    allowed_actions: [],
    assignment_count: 1,
    id: '01J00000000000000000000041',
    is_system: false,
    key: 'branch-manager',
    name: 'Branch manager',
    permission_keys: ['branches.manage'],
    status: 'active',
    version: 7,
  }
}

function organizationApi(
  overrides: Partial<OperatorOrganizationApi> = {},
): OperatorOrganizationApi {
  const unavailable = vi.fn(async () => {
    throw new Error('Unexpected organization API call')
  })

  return new Proxy(overrides as OperatorOrganizationApi, {
    get(target, property, receiver) {
      return Reflect.has(target, property)
        ? Reflect.get(target, property, receiver)
        : unavailable
    },
  })
}
