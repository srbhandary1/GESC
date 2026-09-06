import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type FormEvent,
  type ReactNode,
} from 'react'
import {
  resolveInterfaceMessage,
  type InterfaceMessageResolution,
} from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import {
  OperatorOrganizationApiError,
  type OperatorAuthorityAppointment,
  type OperatorAuthorityAppointmentDetail,
  type OperatorAuthorityKind,
  type OperatorBranch,
  type OperatorCohort,
  type OperatorCollection,
  type OperatorInvitationIssuance,
  type OperatorOrganization,
  type OperatorOrganizationAction,
  type OperatorOrganizationApi,
  type OperatorOrganizationErrorKind,
  type OperatorOrganizationPanelKey,
  type OperatorRole,
  type OperatorRoleCollection,
  type OperatorRoleSummary,
  type OperatorStaffDirectoryFilters,
  type OperatorStaffMembership,
  type OperatorTrainerProfile,
} from './OperatorOrganizationApi'

type MessageResolver = (key: MessageKey) => InterfaceMessageResolution

type ResourceState<T> =
  | { readonly status: 'loading' }
  | { readonly status: 'error' }
  | { readonly status: 'ready'; readonly value: T }

type CommandState =
  | { readonly status: 'idle' }
  | { readonly status: 'busy' }
  | { readonly status: 'success' }
  | {
      readonly status: 'error'
      readonly kind: OperatorOrganizationErrorKind
    }

interface CommonPanelProps {
  readonly api: OperatorOrganizationApi
  readonly createCommandId: () => string
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly message: MessageResolver
}

export interface OperatorOrganizationAdministrationProps {
  readonly api: OperatorOrganizationApi
  readonly createCommandId?: () => string
  readonly currentMembershipId: string
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly panels: readonly OperatorOrganizationPanelKey[]
}

export function OperatorOrganizationAdministration({
  api,
  createCommandId = createRandomClientId,
  currentMembershipId,
  locale,
  localizationPolicy,
  panels,
}: OperatorOrganizationAdministrationProps) {
  const message: MessageResolver = (key) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const common = { api, createCommandId, locale, localizationPolicy, message }

  return (
    <section
      aria-labelledby="operator-people-title"
      className="operator-task-group operator-people"
      id="operator-people"
    >
      <div className="operator-task-group__heading">
        <p>
          <LocalizedMessage resolution={message('operator.navigation.people')} />
        </p>
        <h3 id="operator-people-title">
          <LocalizedMessage resolution={message('operator.people.title')} />
        </h3>
        <p className="operator-people__lead">
          <LocalizedMessage resolution={message('operator.people.lead')} />
        </p>
      </div>

      <div className="operator-task-group__grid operator-people__grid">
        {panels.includes('branches') ? <BranchesPanel {...common} /> : null}
        {panels.includes('cohorts') ? <CohortsPanel {...common} /> : null}
        {panels.includes('staff_memberships') ? (
          <StaffMembershipsPanel
            {...common}
            canReadRoles={panels.includes('roles')}
          />
        ) : null}
        {panels.includes('institution_invites') ? (
          <InvitationsPanel {...common} />
        ) : null}
        {panels.includes('roles') ? <RolesPanel {...common} /> : null}
        {panels.includes('trainer_profiles') ? (
          <TrainerProfilesPanel
            {...common}
            canReadCohorts={panels.includes('cohorts')}
          />
        ) : null}
        {panels.includes('authority_appointments') ? (
          <AuthorityAppointmentsPanel
            {...common}
            canReadTrainers={panels.includes('trainer_profiles')}
            currentMembershipId={currentMembershipId}
          />
        ) : null}
        {panels.includes('membership_sessions') ? (
          <MembershipSessionsPanel {...common} />
        ) : null}
      </div>
    </section>
  )
}

export function OperatorOrganizationSettingsPanel({
  api,
  locale,
  localizationPolicy,
}: Readonly<{
  api: OperatorOrganizationApi
  locale: SupportedLocale
  localizationPolicy: ResolutionPolicy
}>) {
  const message: MessageResolver = (key) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const load = useCallback(
    (signal: AbortSignal) => api.getOrganization(signal),
    [api],
  )
  const resource = useResource(load)

  return (
    <article
      aria-labelledby="operator-organization-settings-title"
      className="operator-panel operator-panel--wide"
    >
      <h4 id="operator-organization-settings-title">
        <LocalizedMessage
          resolution={message('operator.people.organization.title')}
        />
      </h4>
      <p>
        <LocalizedMessage
          resolution={message('operator.people.organization.lead')}
        />
      </p>
      {resource.state.status === 'loading' ? (
        <PanelLoading message={message} />
      ) : null}
      {resource.state.status === 'error' ? (
        <PanelFailure message={message} onRetry={resource.reload} />
      ) : null}
      {resource.state.status === 'ready' ? (
        <OrganizationDetails
          locale={locale}
          message={message}
          organization={resource.state.value}
        />
      ) : null}
    </article>
  )
}

function OrganizationDetails({
  message,
  organization,
}: Readonly<{
  locale: SupportedLocale
  message: MessageResolver
  organization: OperatorOrganization
}>) {
  return (
    <dl className="operator-people__details">
      <Detail label={message('operator.people.field.name').text}>
        <SafeText>{organization.name}</SafeText>
      </Detail>
      <Detail label={message('operator.people.field.kind').text}>
        <code>{organization.kind}</code>
      </Detail>
      <Detail label={message('operator.people.field.status').text}>
        <code>{organization.status}</code>
      </Detail>
      <Detail label={message('operator.people.field.timezone').text}>
        <bdi>{organization.timezone}</bdi>
      </Detail>
      <Detail label={message('operator.people.field.identifier').text}>
        <code>{organization.id}</code>
      </Detail>
    </dl>
  )
}

function BranchesPanel(props: CommonPanelProps) {
  const { api, createCommandId, locale, message } = props
  const load = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listBranches(cursor, signal),
    [api],
  )
  const resource = usePagedResource<OperatorBranch, OperatorCollection<OperatorBranch>>(load)
  const command = useCommandRunner(createCommandId, resource.reload)
  const [key, setKey] = useState('')
  const [name, setName] = useState('')

  const create = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const body = {
      expected_version: 0 as const,
      key: key.trim(),
      name: name.trim(),
    }

    void command
      .run(`branch.create:${JSON.stringify(body)}`, (idempotencyKey) =>
        api.createBranch(body, idempotencyKey),
      )
      .then((completed) => {
        if (completed) {
          setKey('')
          setName('')
        }
      })
  }

  return (
    <PeoplePanel
      headingId="operator-branches-title"
      leadKey="operator.people.branches.lead"
      message={message}
      titleKey="operator.people.branches.title"
    >
      <CollectionState resource={resource} message={message}>
        {(page) => (
          <>
            {hasAction(page.allowed_actions, 'branch.create') ? (
              <form className="operator-people__form" onSubmit={create}>
                <h5>
                  <LocalizedMessage
                    resolution={message('operator.people.branches.create')}
                  />
                </h5>
                <Field
                  hint={message('operator.people.field.key_hint').text}
                  id="operator-branch-key"
                  label={message('operator.people.field.key').text}
                >
                  <input
                    aria-describedby="operator-branch-key-hint"
                    autoCapitalize="none"
                    autoComplete="off"
                    id="operator-branch-key"
                    maxLength={100}
                    onChange={(event) => setKey(event.currentTarget.value)}
                    pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                    required
                    spellCheck={false}
                    value={key}
                  />
                </Field>
                <Field
                  id="operator-branch-name"
                  label={message('operator.people.field.name').text}
                >
                  <input
                    autoComplete="off"
                    id="operator-branch-name"
                    maxLength={160}
                    onChange={(event) => setName(event.currentTarget.value)}
                    required
                    value={name}
                  />
                </Field>
                <button disabled={command.busy} type="submit">
                  <LocalizedMessage
                    resolution={message('operator.people.action.create')}
                  />
                </button>
              </form>
            ) : null}

            {page.data.length === 0 ? <PanelEmpty message={message} /> : null}
            <ul className="operator-people__cards">
              {page.data.map((branch) => (
                <li key={branch.id}>
                  <ResourceHeading
                    code={branch.key}
                    name={branch.name}
                    status={branch.status}
                  />
                  <ResourceMeta
                    id={branch.id}
                    locale={locale}
                    message={message}
                    updatedAt={branch.updated_at}
                    version={branch.version}
                  />
                  {hasAction(branch.allowed_actions, 'branch.update') ? (
                    <BranchUpdateForm
                      branch={branch}
                      command={command}
                      message={message}
                      onUpdate={(body, idempotencyKey) =>
                        api.updateBranch(branch.id, body, idempotencyKey)
                      }
                    />
                  ) : null}
                  {hasAction(branch.allowed_actions, 'branch.archive') ? (
                    <ConfirmAction
                      busy={command.busy}
                      confirmText={message(
                        'operator.people.confirm.archive_branch',
                      ).text}
                      label={message('operator.people.action.archive').text}
                      message={message}
                      onConfirm={() =>
                        command.run(
                          `branch.archive:${branch.id}:${String(branch.version)}`,
                          (idempotencyKey) =>
                            api.archiveBranch(
                              branch.id,
                              { expected_version: branch.version },
                              idempotencyKey,
                            ),
                        )
                      }
                    />
                  ) : null}
                </li>
              ))}
            </ul>
            <PageControls message={message} resource={resource} />
          </>
        )}
      </CollectionState>
      <CommandFeedback
        message={message}
        onReload={resource.reload}
        state={command.state}
      />
    </PeoplePanel>
  )
}

function BranchUpdateForm({
  branch,
  command,
  message,
  onUpdate,
}: Readonly<{
  branch: OperatorBranch
  command: CommandRunner
  message: MessageResolver
  onUpdate: (
    body: Readonly<{ name: string; expected_version: number }>,
    idempotencyKey: string,
  ) => Promise<unknown>
}>) {
  const [name, setName] = useState(branch.name)

  return (
    <details className="operator-people__editor">
      <summary>{message('operator.people.action.edit').text}</summary>
      <form
        className="operator-people__form"
        onSubmit={(event) => {
          event.preventDefault()
          const body = {
            expected_version: branch.version,
            name: name.trim(),
          }
          void command.run(
            `branch.update:${branch.id}:${JSON.stringify(body)}`,
            (idempotencyKey) => onUpdate(body, idempotencyKey),
          )
        }}
      >
        <Field
          id={`operator-branch-name-${branch.id}`}
          label={message('operator.people.field.name').text}
        >
          <input
            id={`operator-branch-name-${branch.id}`}
            maxLength={160}
            onChange={(event) => setName(event.currentTarget.value)}
            required
            value={name}
          />
        </Field>
        <button disabled={command.busy} type="submit">
          {message('operator.people.action.save').text}
        </button>
      </form>
    </details>
  )
}

function CohortsPanel(props: CommonPanelProps) {
  const { api, createCommandId, locale, message } = props
  const load = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listCohorts(cursor, signal),
    [api],
  )
  const resource = usePagedResource<OperatorCohort, OperatorCollection<OperatorCohort>>(load)
  const command = useCommandRunner(createCommandId, resource.reload)
  const [key, setKey] = useState('')
  const [name, setName] = useState('')
  const [branchId, setBranchId] = useState('')

  const create = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const body = {
      branch_id: emptyToNull(branchId),
      expected_version: 0 as const,
      key: key.trim(),
      name: name.trim(),
    }

    void command
      .run(`cohort.create:${JSON.stringify(body)}`, (idempotencyKey) =>
        api.createCohort(body, idempotencyKey),
      )
      .then((completed) => {
        if (completed) {
          setBranchId('')
          setKey('')
          setName('')
        }
      })
  }

  return (
    <PeoplePanel
      headingId="operator-cohorts-title"
      leadKey="operator.people.cohorts.lead"
      message={message}
      titleKey="operator.people.cohorts.title"
    >
      <CollectionState resource={resource} message={message}>
        {(page) => (
          <>
            {hasAction(page.allowed_actions, 'cohort.create') ? (
              <form className="operator-people__form" onSubmit={create}>
                <h5>{message('operator.people.cohorts.create').text}</h5>
                <Field
                  hint={message('operator.people.field.key_hint').text}
                  id="operator-cohort-key"
                  label={message('operator.people.field.key').text}
                >
                  <input
                    aria-describedby="operator-cohort-key-hint"
                    autoCapitalize="none"
                    autoComplete="off"
                    id="operator-cohort-key"
                    maxLength={100}
                    onChange={(event) => setKey(event.currentTarget.value)}
                    pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                    required
                    spellCheck={false}
                    value={key}
                  />
                </Field>
                <Field
                  id="operator-cohort-name"
                  label={message('operator.people.field.name').text}
                >
                  <input
                    autoComplete="off"
                    id="operator-cohort-name"
                    maxLength={160}
                    onChange={(event) => setName(event.currentTarget.value)}
                    required
                    value={name}
                  />
                </Field>
                <Field
                  hint={message('operator.people.field.branch_hint').text}
                  id="operator-cohort-branch"
                  label={message('operator.people.field.branch').text}
                >
                  <input
                    aria-describedby="operator-cohort-branch-hint"
                    autoCapitalize="none"
                    autoComplete="off"
                    id="operator-cohort-branch"
                    onChange={(event) => setBranchId(event.currentTarget.value)}
                    spellCheck={false}
                    value={branchId}
                  />
                </Field>
                <button disabled={command.busy} type="submit">
                  {message('operator.people.action.create').text}
                </button>
              </form>
            ) : null}

            {page.data.length === 0 ? <PanelEmpty message={message} /> : null}
            <ul className="operator-people__cards">
              {page.data.map((cohort) => (
                <li key={cohort.id}>
                  <ResourceHeading
                    code={cohort.key}
                    name={cohort.name}
                    status={cohort.status}
                  />
                  <p className="operator-people__relationship">
                    <strong>{message('operator.people.field.branch').text}</strong>
                    {': '}
                    {cohort.branch === null ? (
                      message('operator.people.unassigned').text
                    ) : (
                      <>
                        <SafeText>{cohort.branch.name}</SafeText>{' '}
                        <code>{cohort.branch.id}</code>
                      </>
                    )}
                  </p>
                  <ResourceMeta
                    id={cohort.id}
                    locale={locale}
                    message={message}
                    updatedAt={cohort.updated_at}
                    version={cohort.version}
                  />
                  {hasAction(cohort.allowed_actions, 'cohort.update') ? (
                    <CohortUpdateForm
                      cohort={cohort}
                      command={command}
                      message={message}
                      onUpdate={(body, idempotencyKey) =>
                        api.updateCohort(cohort.id, body, idempotencyKey)
                      }
                    />
                  ) : null}
                  {hasAction(cohort.allowed_actions, 'cohort.archive') ? (
                    <ConfirmAction
                      busy={command.busy}
                      confirmText={message(
                        'operator.people.confirm.archive_cohort',
                      ).text}
                      label={message('operator.people.action.archive').text}
                      message={message}
                      onConfirm={() =>
                        command.run(
                          `cohort.archive:${cohort.id}:${String(cohort.version)}`,
                          (idempotencyKey) =>
                            api.archiveCohort(
                              cohort.id,
                              { expected_version: cohort.version },
                              idempotencyKey,
                            ),
                        )
                      }
                    />
                  ) : null}
                </li>
              ))}
            </ul>
            <PageControls message={message} resource={resource} />
          </>
        )}
      </CollectionState>
      <CommandFeedback
        message={message}
        onReload={resource.reload}
        state={command.state}
      />
    </PeoplePanel>
  )
}

function CohortUpdateForm({
  cohort,
  command,
  message,
  onUpdate,
}: Readonly<{
  cohort: OperatorCohort
  command: CommandRunner
  message: MessageResolver
  onUpdate: (
    body: Readonly<{
      name: string
      branch_id: string | null
      expected_version: number
    }>,
    idempotencyKey: string,
  ) => Promise<unknown>
}>) {
  const [name, setName] = useState(cohort.name)
  const [branchId, setBranchId] = useState(cohort.branch?.id ?? '')

  return (
    <details className="operator-people__editor">
      <summary>{message('operator.people.action.edit').text}</summary>
      <form
        className="operator-people__form"
        onSubmit={(event) => {
          event.preventDefault()
          const body = {
            branch_id: emptyToNull(branchId),
            expected_version: cohort.version,
            name: name.trim(),
          }
          void command.run(
            `cohort.update:${cohort.id}:${JSON.stringify(body)}`,
            (idempotencyKey) => onUpdate(body, idempotencyKey),
          )
        }}
      >
        <Field
          id={`operator-cohort-name-${cohort.id}`}
          label={message('operator.people.field.name').text}
        >
          <input
            id={`operator-cohort-name-${cohort.id}`}
            maxLength={160}
            onChange={(event) => setName(event.currentTarget.value)}
            required
            value={name}
          />
        </Field>
        <Field
          hint={message('operator.people.field.branch_hint').text}
          id={`operator-cohort-branch-${cohort.id}`}
          label={message('operator.people.field.branch').text}
        >
          <input
            aria-describedby={`operator-cohort-branch-${cohort.id}-hint`}
            autoCapitalize="none"
            autoComplete="off"
            id={`operator-cohort-branch-${cohort.id}`}
            onChange={(event) => setBranchId(event.currentTarget.value)}
            spellCheck={false}
            value={branchId}
          />
        </Field>
        <button disabled={command.busy} type="submit">
          {message('operator.people.action.save').text}
        </button>
      </form>
    </details>
  )
}

function StaffMembershipsPanel({
  api,
  canReadRoles,
  createCommandId,
  locale,
  localizationPolicy,
  message,
}: CommonPanelProps & Readonly<{ canReadRoles: boolean }>) {
  const [filters, setFilters] = useState<OperatorStaffDirectoryFilters>({})
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [namePrefix, setNamePrefix] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const loadMemberships = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listMemberships(filters, cursor, signal),
    [api, filters],
  )
  const memberships = usePagedResource<
    OperatorStaffMembership,
    OperatorCollection<OperatorStaffMembership>
  >(loadMemberships)
  const loadRoles = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listRoles(cursor, signal),
    [api],
  )
  const roles = usePagedResource<OperatorRole, OperatorRoleCollection>(
    loadRoles,
    canReadRoles,
  )
  const reload = useCallback(() => {
    memberships.reload()
    if (canReadRoles) {
      roles.reload()
    }
  }, [canReadRoles, memberships, roles])
  const command = useCommandRunner(createCommandId, reload)

  const applyFilters = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setSelectedId(null)
    setFilters({
      ...(namePrefix.trim() === ''
        ? {}
        : { display_name_prefix: namePrefix.trim() }),
      ...(statusFilter === ''
        ? {}
        : { status: statusFilter as OperatorStaffMembership['status'] }),
    })
  }

  const resetFilters = () => {
    setNamePrefix('')
    setStatusFilter('')
    setSelectedId(null)
    setFilters({})
  }

  return (
    <PeoplePanel
      headingId="operator-staff-memberships-title"
      leadKey="operator.people.memberships.lead"
      message={message}
      titleKey="operator.people.memberships.title"
      wide
    >
      <form
        className="operator-directory__toolbar operator-people__form"
        onSubmit={applyFilters}
        role="search"
      >
        <Field
          id="operator-staff-name-prefix"
          label={message('operator.people.field.name_prefix').text}
        >
          <input
            autoComplete="off"
            id="operator-staff-name-prefix"
            maxLength={80}
            onChange={(event) => setNamePrefix(event.currentTarget.value)}
            placeholder={message('operator.directory.prefix_hint').text}
            value={namePrefix}
          />
        </Field>
        <Field
          id="operator-staff-status-filter"
          label={message('operator.people.field.status').text}
        >
          <select
            id="operator-staff-status-filter"
            onChange={(event) => setStatusFilter(event.currentTarget.value)}
            value={statusFilter}
          >
            <option value="">{message('operator.learners.filter.all').text}</option>
            <option value="active">active</option>
            <option value="suspended">suspended</option>
            <option value="ended">ended</option>
          </select>
        </Field>
        <div className="operator-directory__toolbar-actions">
          <button type="submit">
            {message('operator.learners.filter.apply').text}
          </button>
          <button onClick={resetFilters} type="button">
            {message('operator.directory.reset_filters').text}
          </button>
        </div>
      </form>

      <CollectionState resource={memberships} message={message}>
        {(page) => {
          const selectedMembership =
            page.data.find(
              (membership) => membership.membership_id === selectedId,
            ) ?? null

          return (
            <>
              <p className="operator-directory__summary">
                {
                  resolveInterfaceMessage(
                    'operator.directory.page_summary',
                    locale,
                    localizationPolicy,
                    { count: page.data.length },
                  ).text
                }
              </p>
              {page.data.length === 0 ? <PanelEmpty message={message} /> : null}
              <ul
                aria-label={message('operator.people.memberships.title').text}
                className="operator-directory__list"
              >
                {page.data.map((membership) => (
                  <li key={membership.membership_id}>
                    <button
                      aria-label={
                        membership.display_name ??
                        message('operator.people.name_unavailable').text
                      }
                      aria-pressed={selectedId === membership.membership_id}
                      className="operator-directory__row"
                      onClick={() => {
                        setSelectedId(membership.membership_id)
                      }}
                      type="button"
                    >
                      <span className="operator-directory__primary">
                        {membership.display_name ??
                          message('operator.people.name_unavailable').text}
                      </span>
                      <span className="operator-directory__meta">
                        {membership.membership_type}
                      </span>
                      <span className="operator-directory__meta">
                        {membership.status}
                      </span>
                      <span className="operator-directory__meta">
                        {membership.roles.length === 0
                          ? message('operator.people.unassigned').text
                          : membership.roles
                              .map((role) => role.name)
                              .join(', ')}
                      </span>
                      <span className="operator-directory__meta">
                        <code>{membership.membership_id}</code>
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
              <PageControls message={message} resource={memberships} />
              {selectedMembership === null ? (
                <p className="operator-directory__select-hint">
                  {message('operator.people.memberships.select_prompt').text}
                </p>
              ) : (
                <StaffMembershipDetail
                  command={command}
                  locale={locale}
                  membership={selectedMembership}
                  message={message}
                  onAssign={(role, idempotencyKey) =>
                    api.assignMembershipRole(
                      selectedMembership.membership_id,
                      {
                        expected_membership_version:
                          selectedMembership.version,
                        expected_role_version: role.version,
                        role_id: role.id,
                      },
                      idempotencyKey,
                    )
                  }
                  onDeactivate={(idempotencyKey) =>
                    api.deactivateMembership(
                      selectedMembership.membership_id,
                      { expected_version: selectedMembership.version },
                      idempotencyKey,
                    )
                  }
                  onReactivate={(idempotencyKey) =>
                    api.reactivateMembership(
                      selectedMembership.membership_id,
                      { expected_version: selectedMembership.version },
                      idempotencyKey,
                    )
                  }
                  onRevokeRole={(role, currentRole, idempotencyKey) =>
                    api.revokeMembershipRole(
                      selectedMembership.membership_id,
                      {
                        expected_membership_version:
                          selectedMembership.version,
                        expected_role_version: currentRole.version,
                        role_id: role.id,
                      },
                      idempotencyKey,
                    )
                  }
                  roles={roles}
                />
              )}
            </>
          )
        }}
      </CollectionState>
      {roles.state.status === 'loading' ? (
        <PanelLoading message={message} />
      ) : null}
      {roles.state.status === 'error' ? (
        <PanelFailure message={message} onRetry={roles.reload} />
      ) : null}
      {roles.state.status === 'ready' ? (
        <PageControls
          contextLabel={message('operator.people.roles.title').text}
          message={message}
          resource={roles}
        />
      ) : null}
      <CommandFeedback
        message={message}
        onReload={reload}
        state={command.state}
      />
    </PeoplePanel>
  )
}

function StaffMembershipDetail({
  command,
  locale,
  membership,
  message,
  onAssign,
  onDeactivate,
  onReactivate,
  onRevokeRole,
  roles,
}: Readonly<{
  command: CommandRunner
  locale: SupportedLocale
  membership: OperatorStaffMembership
  message: MessageResolver
  onAssign: (role: OperatorRole, idempotencyKey: string) => Promise<unknown>
  onDeactivate: (idempotencyKey: string) => Promise<unknown>
  onReactivate: (idempotencyKey: string) => Promise<unknown>
  onRevokeRole: (
    role: OperatorRoleSummary,
    currentRole: OperatorRole,
    idempotencyKey: string,
  ) => Promise<unknown>
  roles: PagedResource<OperatorRole, OperatorRoleCollection>
}>) {
  return (
    <section
      aria-labelledby="operator-staff-membership-detail-title"
      className="operator-directory__detail-panel operator-people__relationships"
    >
      <h5 id="operator-staff-membership-detail-title">
        {membership.display_name ??
          message('operator.people.name_unavailable').text}
      </h5>
      <ResourceMeta
        id={membership.membership_id}
        locale={locale}
        message={message}
        updatedAt={membership.ended_at ?? membership.joined_at}
        version={membership.version}
      />
      <div className="operator-people__relationships">
        <h5>{message('operator.people.memberships.roles').text}</h5>
        {membership.roles.length === 0 ? (
          <p>{message('operator.people.unassigned').text}</p>
        ) : (
          <ul className="operator-people__chips">
            {membership.roles.map((role) => {
              const currentRole =
                roles.state.status === 'ready'
                  ? roles.state.value.data.find(
                      (candidate) => candidate.id === role.id,
                    )
                  : undefined

              return (
                <li key={role.id}>
                  <SafeText>{role.name}</SafeText>
                  <code>{role.key}</code>
                  {hasAction(
                    membership.allowed_actions,
                    'membership.revoke_role',
                  ) && currentRole !== undefined ? (
                    <ConfirmAction
                      busy={command.busy}
                      confirmText={message(
                        'operator.people.confirm.revoke_role',
                      ).text}
                      label={message('operator.people.action.revoke_role').text}
                      message={message}
                      onConfirm={() =>
                        command.run(
                          `membership.revoke_role:${membership.membership_id}:${role.id}:${String(membership.version)}:${String(currentRole.version)}`,
                          (idempotencyKey) =>
                            onRevokeRole(role, currentRole, idempotencyKey),
                        )
                      }
                    />
                  ) : null}
                </li>
              )
            })}
          </ul>
        )}
      </div>

      {hasAction(membership.allowed_actions, 'membership.assign_role') ? (
        <MembershipRoleAssignmentForm
          command={command}
          membership={membership}
          message={message}
          roles={roles}
          onAssign={onAssign}
        />
      ) : null}

      <div className="operator-people__actions">
        {hasAction(membership.allowed_actions, 'membership.deactivate') ? (
          <ConfirmAction
            busy={command.busy}
            confirmText={message(
              'operator.people.confirm.deactivate_membership',
            ).text}
            label={message('operator.people.action.deactivate').text}
            message={message}
            onConfirm={() =>
              command.run(
                `membership.deactivate:${membership.membership_id}:${String(membership.version)}`,
                onDeactivate,
              )
            }
          />
        ) : null}
        {hasAction(membership.allowed_actions, 'membership.reactivate') ? (
          <button
            disabled={command.busy}
            onClick={() => {
              void command.run(
                `membership.reactivate:${membership.membership_id}:${String(membership.version)}`,
                onReactivate,
              )
            }}
            type="button"
          >
            {message('operator.people.action.reactivate').text}
          </button>
        ) : null}
      </div>
    </section>
  )
}

function MembershipRoleAssignmentForm({
  command,
  membership,
  message,
  onAssign,
  roles,
}: Readonly<{
  command: CommandRunner
  membership: OperatorStaffMembership
  message: MessageResolver
  onAssign: (role: OperatorRole, idempotencyKey: string) => Promise<unknown>
  roles: PagedResource<OperatorRole, OperatorRoleCollection>
}>) {
  const [roleId, setRoleId] = useState('')

  if (roles.state.status !== 'ready') {
    return null
  }

  const assigned = new Set(membership.roles.map((role) => role.id))
  const choices = roles.state.value.data.filter(
    (role) => role.status === 'active' && !assigned.has(role.id),
  )

  if (choices.length === 0) {
    return null
  }

  return (
    <form
      className="operator-people__form operator-people__form--inline"
      onSubmit={(event) => {
        event.preventDefault()
        const role = choices.find((candidate) => candidate.id === roleId)

        if (role === undefined) {
          return
        }

        void command
          .run(
            `membership.assign_role:${membership.membership_id}:${role.id}:${String(membership.version)}:${String(role.version)}`,
            (idempotencyKey) => onAssign(role, idempotencyKey),
          )
          .then((completed) => {
            if (completed) {
              setRoleId('')
            }
          })
      }}
    >
      <Field
        id={`operator-membership-role-${membership.membership_id}`}
        label={message('operator.people.memberships.assign_role').text}
      >
        <select
          id={`operator-membership-role-${membership.membership_id}`}
          onChange={(event) => setRoleId(event.currentTarget.value)}
          required
          value={roleId}
        >
          <option value="">{message('operator.people.select').text}</option>
          {choices.map((role) => (
            <option key={role.id} value={role.id}>
              {role.name} ({role.key})
            </option>
          ))}
        </select>
      </Field>
      <button disabled={command.busy || roleId === ''} type="submit">
        {message('operator.people.action.assign').text}
      </button>
    </form>
  )
}

function InvitationsPanel({
  api,
  createCommandId,
  locale,
  message,
}: CommonPanelProps) {
  const load = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listInvitations(cursor, signal),
    [api],
  )
  const resource = usePagedResource(load)
  const command = useCommandRunner(createCommandId, resource.reload)
  const [membershipType, setMembershipType] = useState<
    'organization_admin' | 'trainer'
  >('trainer')
  const [expiry, setExpiry] = useState<24 | 72 | 168>(72)
  const [issuance, setIssuance] =
    useState<OperatorInvitationIssuance | null>(null)

  const issue = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const body = {
      expected_version: 0 as const,
      expires_in_hours: expiry,
      membership_type: membershipType,
    }

    void command.run(
      `invitation.issue:${JSON.stringify(body)}`,
      async (idempotencyKey) => {
        const result = await api.issueInvitation(body, idempotencyKey)
        setIssuance(result)

        return result
      },
    )
  }

  return (
    <PeoplePanel
      headingId="operator-invitations-title"
      leadKey="operator.people.invitations.lead"
      message={message}
      titleKey="operator.people.invitations.title"
    >
      <CollectionState resource={resource} message={message}>
        {(page) => (
          <>
            {issuance !== null ? (
              <OneTimeInvitationCode
                issuance={issuance}
                message={message}
                onAcknowledge={() => setIssuance(null)}
              />
            ) : hasAction(page.allowed_actions, 'invitation.issue') ? (
              <form className="operator-people__form" onSubmit={issue}>
                <h5>{message('operator.people.invitations.issue').text}</h5>
                <Field
                  id="operator-invitation-membership-type"
                  label={message('operator.people.field.membership_type').text}
                >
                  <select
                    id="operator-invitation-membership-type"
                    onChange={(event) =>
                      setMembershipType(
                        event.currentTarget.value === 'organization_admin'
                          ? 'organization_admin'
                          : 'trainer',
                      )
                    }
                    value={membershipType}
                  >
                    <option value="trainer">
                      {message('operator.people.membership_type.trainer').text}
                    </option>
                    <option value="organization_admin">
                      {
                        message(
                          'operator.people.membership_type.organization_admin',
                        ).text
                      }
                    </option>
                  </select>
                </Field>
                <Field
                  id="operator-invitation-expiry"
                  label={message('operator.people.field.expiry').text}
                >
                  <select
                    id="operator-invitation-expiry"
                    onChange={(event) => {
                      const value = Number(event.currentTarget.value)
                      setExpiry(value === 24 || value === 168 ? value : 72)
                    }}
                    value={expiry}
                  >
                    <option value={24}>24 hours</option>
                    <option value={72}>72 hours</option>
                    <option value={168}>168 hours</option>
                  </select>
                </Field>
                <button disabled={command.busy} type="submit">
                  {message('operator.people.action.issue').text}
                </button>
              </form>
            ) : null}

            {page.data.length === 0 ? <PanelEmpty message={message} /> : null}
            <ul className="operator-people__cards">
              {page.data.map((invitation) => (
                <li key={invitation.id}>
                  <ResourceHeading
                    code={invitation.membership_type}
                    name={message('operator.people.invitations.invitation').text}
                    status={invitation.status}
                  />
                  <dl className="operator-people__details">
                    <Detail label={message('operator.people.field.expires').text}>
                      <time dateTime={invitation.expires_at}>
                        {formatDateTime(invitation.expires_at, locale)}
                      </time>
                    </Detail>
                    <Detail label={message('operator.people.field.identifier').text}>
                      <code>{invitation.id}</code>
                    </Detail>
                    <Detail label={message('operator.people.field.version').text}>
                      {invitation.version}
                    </Detail>
                  </dl>
                  {hasAction(invitation.allowed_actions, 'invitation.revoke') ? (
                    <ConfirmAction
                      busy={command.busy}
                      confirmText={message(
                        'operator.people.confirm.revoke_invitation',
                      ).text}
                      label={message('operator.people.action.revoke').text}
                      message={message}
                      onConfirm={() =>
                        command.run(
                          `invitation.revoke:${invitation.id}:${String(invitation.version)}`,
                          (idempotencyKey) =>
                            api.revokeInvitation(
                              invitation.id,
                              { expected_version: invitation.version },
                              idempotencyKey,
                            ),
                        )
                      }
                    />
                  ) : null}
                </li>
              ))}
            </ul>
            <PageControls message={message} resource={resource} />
          </>
        )}
      </CollectionState>
      <CommandFeedback
        message={message}
        onReload={resource.reload}
        state={command.state}
      />
    </PeoplePanel>
  )
}

function OneTimeInvitationCode({
  issuance,
  message,
  onAcknowledge,
}: Readonly<{
  issuance: OperatorInvitationIssuance
  message: MessageResolver
  onAcknowledge: () => void
}>) {
  const heading = useRef<HTMLHeadingElement>(null)
  const [copied, setCopied] = useState(false)
  const [copyFailed, setCopyFailed] = useState(false)

  useEffect(() => {
    heading.current?.focus()
  }, [])

  if (issuance.activation_code === null) {
    return (
      <section
        aria-labelledby="operator-invitation-replay-title"
        className="operator-invitation-code"
      >
        <h5 id="operator-invitation-replay-title" ref={heading} tabIndex={-1}>
          {message('operator.people.invitation.replay_title').text}
        </h5>
        <p>{message('operator.people.invitation.replay_notice').text}</p>
        <button onClick={onAcknowledge} type="button">
          {message('operator.people.action.dismiss').text}
        </button>
      </section>
    )
  }

  const activationCode = issuance.activation_code

  return (
    <section
      aria-labelledby="operator-invitation-code-title"
      className="operator-invitation-code"
    >
      <h5 id="operator-invitation-code-title" ref={heading} tabIndex={-1}>
        {message('operator.people.invitation.code_title').text}
      </h5>
      <p>{message('operator.people.invitation.code_notice').text}</p>
      <code className="operator-invitation-code__value" dir="ltr">
        {activationCode}
      </code>
      <div className="operator-people__actions">
        <button
          onClick={() => {
            setCopied(false)
            setCopyFailed(false)
            const clipboard = globalThis.navigator.clipboard

            if (clipboard === undefined) {
              setCopyFailed(true)
              return
            }

            void clipboard
              .writeText(activationCode)
              .then(() => setCopied(true))
              .catch(() => setCopyFailed(true))
          }}
          type="button"
        >
          {message('operator.people.action.copy').text}
        </button>
        <button onClick={onAcknowledge} type="button">
          {message('operator.people.invitation.saved').text}
        </button>
      </div>
      {copied ? (
        <p aria-live="polite" role="status">
          {message('operator.people.invitation.copied').text}
        </p>
      ) : null}
      {copyFailed ? (
        <p role="alert">
          {message('operator.people.invitation.copy_failed').text}
        </p>
      ) : null}
    </section>
  )
}

function RolesPanel({
  api,
  createCommandId,
  message,
}: CommonPanelProps) {
  const load = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listRoles(cursor, signal),
    [api],
  )
  const resource = usePagedResource<OperatorRole, OperatorRoleCollection>(load)
  const command = useCommandRunner(createCommandId, resource.reload)
  const [key, setKey] = useState('')
  const [name, setName] = useState('')
  const [permissionKeys, setPermissionKeys] = useState<readonly string[]>([])

  return (
    <PeoplePanel
      headingId="operator-roles-title"
      leadKey="operator.people.roles.lead"
      message={message}
      titleKey="operator.people.roles.title"
      wide
    >
      <CollectionState resource={resource} message={message}>
        {(page) => (
          <>
            {hasAction(page.allowed_actions, 'role.create') ? (
              <form
                className="operator-people__form"
                onSubmit={(event) => {
                  event.preventDefault()
                  const body = {
                    expected_version: 0 as const,
                    key: key.trim(),
                    name: name.trim(),
                    permission_keys: sorted(permissionKeys),
                  }
                  void command
                    .run(`role.create:${JSON.stringify(body)}`, (idempotencyKey) =>
                      api.createRole(body, idempotencyKey),
                    )
                    .then((completed) => {
                      if (completed) {
                        setKey('')
                        setName('')
                        setPermissionKeys([])
                      }
                    })
                }}
              >
                <h5>{message('operator.people.roles.create').text}</h5>
                <Field
                  hint={message('operator.people.field.key_hint').text}
                  id="operator-role-key"
                  label={message('operator.people.field.key').text}
                >
                  <input
                    aria-describedby="operator-role-key-hint"
                    autoCapitalize="none"
                    autoComplete="off"
                    id="operator-role-key"
                    maxLength={100}
                    onChange={(event) => setKey(event.currentTarget.value)}
                    pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                    required
                    spellCheck={false}
                    value={key}
                  />
                </Field>
                <Field
                  id="operator-role-name"
                  label={message('operator.people.field.name').text}
                >
                  <input
                    id="operator-role-name"
                    maxLength={160}
                    onChange={(event) => setName(event.currentTarget.value)}
                    required
                    value={name}
                  />
                </Field>
                <PermissionChoices
                  legend={message('operator.people.roles.permissions').text}
                  message={message}
                  onChange={setPermissionKeys}
                  options={page.delegable_permissions}
                  selected={permissionKeys}
                  suffix="create"
                />
                <button
                  disabled={command.busy || permissionKeys.length === 0}
                  type="submit"
                >
                  {message('operator.people.action.create').text}
                </button>
              </form>
            ) : null}

            {page.data.length === 0 ? <PanelEmpty message={message} /> : null}
            <ul className="operator-people__cards operator-people__cards--wide">
              {page.data.map((role) => (
                <li key={role.id}>
                  <ResourceHeading
                    code={role.key}
                    name={role.name}
                    status={role.status}
                  />
                  <p>
                    {role.is_system
                      ? message('operator.people.roles.system').text
                      : message('operator.people.roles.custom').text}
                    {' · '}
                    {message('operator.people.roles.assignments').text}:{' '}
                    {role.assignment_count}
                  </p>
                  <ul className="operator-people__permission-list">
                    {role.permission_keys.map((permissionKey) => (
                      <li key={permissionKey}>
                        <code>{permissionKey}</code>
                      </li>
                    ))}
                  </ul>
                  {hasAction(role.allowed_actions, 'role.update') ? (
                    <RoleUpdateForm
                      command={command}
                      message={message}
                      options={page.delegable_permissions}
                      role={role}
                      onUpdate={(body, idempotencyKey) =>
                        api.updateRole(role.id, body, idempotencyKey)
                      }
                    />
                  ) : null}
                  {hasAction(role.allowed_actions, 'role.retire') ? (
                    <ConfirmAction
                      busy={command.busy}
                      confirmText={message(
                        'operator.people.confirm.retire_role',
                      ).text}
                      label={message('operator.people.action.retire').text}
                      message={message}
                      onConfirm={() =>
                        command.run(
                          `role.retire:${role.id}:${String(role.version)}`,
                          (idempotencyKey) =>
                            api.retireRole(
                              role.id,
                              { expected_version: role.version },
                              idempotencyKey,
                            ),
                        )
                      }
                    />
                  ) : null}
                </li>
              ))}
            </ul>
            <PageControls message={message} resource={resource} />
          </>
        )}
      </CollectionState>
      <CommandFeedback
        message={message}
        onReload={resource.reload}
        state={command.state}
      />
    </PeoplePanel>
  )
}

function RoleUpdateForm({
  command,
  message,
  onUpdate,
  options,
  role,
}: Readonly<{
  command: CommandRunner
  message: MessageResolver
  onUpdate: (
    body: Readonly<{
      name: string
      permission_keys: readonly string[]
      expected_version: number
    }>,
    idempotencyKey: string,
  ) => Promise<unknown>
  options: OperatorRoleCollection['delegable_permissions']
  role: OperatorRole
}>) {
  const [name, setName] = useState(role.name)
  const [permissionKeys, setPermissionKeys] = useState(role.permission_keys)

  return (
    <details className="operator-people__editor">
      <summary>{message('operator.people.action.edit').text}</summary>
      <form
        className="operator-people__form"
        onSubmit={(event) => {
          event.preventDefault()
          const body = {
            expected_version: role.version,
            name: name.trim(),
            permission_keys: sorted(permissionKeys),
          }
          void command.run(
            `role.update:${role.id}:${JSON.stringify(body)}`,
            (idempotencyKey) => onUpdate(body, idempotencyKey),
          )
        }}
      >
        <Field
          id={`operator-role-name-${role.id}`}
          label={message('operator.people.field.name').text}
        >
          <input
            id={`operator-role-name-${role.id}`}
            maxLength={160}
            onChange={(event) => setName(event.currentTarget.value)}
            required
            value={name}
          />
        </Field>
        <PermissionChoices
          legend={message('operator.people.roles.permissions').text}
          message={message}
          onChange={setPermissionKeys}
          options={options}
          selected={permissionKeys}
          suffix={role.id}
        />
        <button
          disabled={command.busy || permissionKeys.length === 0}
          type="submit"
        >
          {message('operator.people.action.save').text}
        </button>
      </form>
    </details>
  )
}

function PermissionChoices({
  legend,
  message,
  onChange,
  options,
  selected,
  suffix,
}: Readonly<{
  legend: string
  message: MessageResolver
  onChange: (keys: readonly string[]) => void
  options: OperatorRoleCollection['delegable_permissions']
  selected: readonly string[]
  suffix: string
}>) {
  const selectedSet = new Set(selected)

  return (
    <fieldset
      aria-describedby={
        selected.length === 0
          ? `operator-role-permission-${suffix}-required`
          : undefined
      }
      className="operator-people__permissions"
    >
      <legend>{legend}</legend>
      {selected.length === 0 ? (
        <p id={`operator-role-permission-${suffix}-required`}>
          {message('operator.people.roles.permission_required').text}
        </p>
      ) : null}
      {options.map((option) => {
        const id = `operator-role-permission-${suffix}-${option.key}`

        return (
          <label htmlFor={id} key={option.key}>
            <input
              checked={selectedSet.has(option.key)}
              id={id}
              onChange={(event) => {
                onChange(
                  event.currentTarget.checked
                    ? sorted([...selected, option.key])
                    : selected.filter((key) => key !== option.key),
                )
              }}
              type="checkbox"
            />
            <span>
              <code>{option.key}</code>
              <small>{option.description}</small>
            </span>
          </label>
        )
      })}
    </fieldset>
  )
}

function TrainerProfilesPanel({
  api,
  canReadCohorts,
  createCommandId,
  locale,
  localizationPolicy,
  message,
}: CommonPanelProps & Readonly<{ canReadCohorts: boolean }>) {
  const [filters, setFilters] = useState<OperatorStaffDirectoryFilters>({})
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [namePrefix, setNamePrefix] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const loadTrainers = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listTrainerProfiles(filters, cursor, signal),
    [api, filters],
  )
  const trainers = usePagedResource<
    OperatorTrainerProfile,
    OperatorCollection<OperatorTrainerProfile>
  >(loadTrainers)
  const loadCohorts = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listCohorts(cursor, signal),
    [api],
  )
  const cohorts = usePagedResource<OperatorCohort, OperatorCollection<OperatorCohort>>(
    loadCohorts,
    canReadCohorts,
  )
  const reload = useCallback(() => {
    trainers.reload()
    if (canReadCohorts) {
      cohorts.reload()
    }
  }, [canReadCohorts, cohorts, trainers])
  const command = useCommandRunner(createCommandId, reload)

  const applyFilters = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setSelectedId(null)
    setFilters({
      ...(namePrefix.trim() === ''
        ? {}
        : { display_name_prefix: namePrefix.trim() }),
      ...(statusFilter === ''
        ? {}
        : { status: statusFilter as OperatorStaffMembership['status'] }),
    })
  }

  const resetFilters = () => {
    setNamePrefix('')
    setStatusFilter('')
    setSelectedId(null)
    setFilters({})
  }

  return (
    <PeoplePanel
      headingId="operator-trainer-profiles-title"
      leadKey="operator.people.trainers.lead"
      message={message}
      titleKey="operator.people.trainers.title"
      wide
    >
      <form
        className="operator-directory__toolbar operator-people__form"
        onSubmit={applyFilters}
        role="search"
      >
        <Field
          id="operator-trainer-name-prefix"
          label={message('operator.people.field.name_prefix').text}
        >
          <input
            autoComplete="off"
            id="operator-trainer-name-prefix"
            maxLength={80}
            onChange={(event) => setNamePrefix(event.currentTarget.value)}
            placeholder={message('operator.directory.prefix_hint').text}
            value={namePrefix}
          />
        </Field>
        <Field
          id="operator-trainer-status-filter"
          label={message('operator.people.field.status').text}
        >
          <select
            id="operator-trainer-status-filter"
            onChange={(event) => setStatusFilter(event.currentTarget.value)}
            value={statusFilter}
          >
            <option value="">{message('operator.learners.filter.all').text}</option>
            <option value="active">active</option>
            <option value="suspended">suspended</option>
            <option value="ended">ended</option>
          </select>
        </Field>
        <div className="operator-directory__toolbar-actions">
          <button type="submit">
            {message('operator.learners.filter.apply').text}
          </button>
          <button onClick={resetFilters} type="button">
            {message('operator.directory.reset_filters').text}
          </button>
        </div>
      </form>

      <CollectionState resource={trainers} message={message}>
        {(page) => {
          const selectedTrainer =
            page.data.find((trainer) => trainer.membership_id === selectedId) ??
            null

          return (
            <>
              <p className="operator-directory__summary">
                {
                  resolveInterfaceMessage(
                    'operator.directory.page_summary',
                    locale,
                    localizationPolicy,
                    { count: page.data.length },
                  ).text
                }
              </p>
              {page.data.length === 0 ? <PanelEmpty message={message} /> : null}
              <ul
                aria-label={message('operator.people.trainers.title').text}
                className="operator-directory__list"
              >
                {page.data.map((trainer) => (
                  <li key={trainer.membership_id}>
                    <button
                      aria-pressed={selectedId === trainer.membership_id}
                      className="operator-directory__row"
                      onClick={() => {
                        setSelectedId(trainer.membership_id)
                      }}
                      type="button"
                    >
                      <span className="operator-directory__primary">
                        {trainer.display_name ??
                          message('operator.people.name_unavailable').text}
                      </span>
                      <span className="operator-directory__meta">
                        {trainer.status}
                      </span>
                      <span className="operator-directory__meta">
                        {trainer.cohorts.length === 0
                          ? message('operator.people.unassigned').text
                          : trainer.cohorts.map((cohort) => cohort.name).join(', ')}
                      </span>
                      <span className="operator-directory__meta">
                        <code>{trainer.membership_id}</code>
                      </span>
                    </button>
                  </li>
                ))}
              </ul>
              <PageControls message={message} resource={trainers} />
              {selectedTrainer === null ? (
                <p className="operator-directory__select-hint">
                  {message('operator.people.trainers.select_prompt').text}
                </p>
              ) : (
                <div className="operator-directory__detail-panel">
                  <ResourceMeta
                    id={selectedTrainer.membership_id}
                    locale={locale}
                    message={message}
                    updatedAt={
                      selectedTrainer.ended_at ?? selectedTrainer.joined_at
                    }
                    version={selectedTrainer.version}
                  />
                  <div className="operator-people__relationships">
                    <h5>{message('operator.people.trainers.cohorts').text}</h5>
                    {selectedTrainer.cohorts.length === 0 ? (
                      <p>{message('operator.people.unassigned').text}</p>
                    ) : (
                      <ul className="operator-people__chips">
                        {selectedTrainer.cohorts.map((cohort) => (
                          <li key={cohort.id}>
                            <SafeText>{cohort.name}</SafeText>
                            <code>{cohort.key}</code>
                            {hasAction(
                              selectedTrainer.allowed_actions,
                              'trainer.revoke_cohort',
                            ) ? (
                              <ConfirmAction
                                busy={command.busy}
                                confirmText={message(
                                  'operator.people.confirm.revoke_cohort',
                                ).text}
                                label={message(
                                  'operator.people.action.revoke',
                                ).text}
                                message={message}
                                onConfirm={() =>
                                  command.run(
                                    `trainer.revoke_cohort:${selectedTrainer.membership_id}:${cohort.id}:${String(selectedTrainer.version)}:${String(cohort.version)}`,
                                    (idempotencyKey) =>
                                      api.revokeTrainerCohort(
                                        selectedTrainer.membership_id,
                                        {
                                          cohort_id: cohort.id,
                                          expected_cohort_version:
                                            cohort.version,
                                          expected_membership_version:
                                            selectedTrainer.version,
                                        },
                                        idempotencyKey,
                                      ),
                                  )
                                }
                              />
                            ) : null}
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                  {hasAction(
                    selectedTrainer.allowed_actions,
                    'trainer.assign_cohort',
                  ) ? (
                    <TrainerCohortAssignmentForm
                      cohorts={cohorts}
                      command={command}
                      message={message}
                      onAssign={(cohort, idempotencyKey) =>
                        api.assignTrainerCohort(
                          selectedTrainer.membership_id,
                          {
                            cohort_id: cohort.id,
                            expected_cohort_version: cohort.version,
                            expected_membership_version: selectedTrainer.version,
                          },
                          idempotencyKey,
                        )
                      }
                      trainer={selectedTrainer}
                    />
                  ) : null}
                  <SafeAppointmentList
                    appointments={selectedTrainer.appointments}
                    locale={locale}
                    message={message}
                  />
                </div>
              )}
            </>
          )
        }}
      </CollectionState>
      {cohorts.state.status === 'ready' ? (
        <PageControls
          contextLabel={message('operator.people.cohorts.title').text}
          message={message}
          resource={cohorts}
        />
      ) : null}
      <CommandFeedback
        message={message}
        onReload={reload}
        state={command.state}
      />
    </PeoplePanel>
  )
}

function TrainerCohortAssignmentForm({
  cohorts,
  command,
  message,
  onAssign,
  trainer,
}: Readonly<{
  cohorts: PagedResource<OperatorCohort, OperatorCollection<OperatorCohort>>
  command: CommandRunner
  message: MessageResolver
  onAssign: (cohort: OperatorCohort, idempotencyKey: string) => Promise<unknown>
  trainer: OperatorTrainerProfile
}>) {
  const [cohortId, setCohortId] = useState('')

  if (cohorts.state.status === 'loading') {
    return <PanelLoading message={message} />
  }

  if (cohorts.state.status === 'error') {
    return <PanelFailure message={message} onRetry={cohorts.reload} />
  }

  if (cohorts.state.status === 'disabled') {
    return null
  }

  const assigned = new Set(trainer.cohorts.map((cohort) => cohort.id))
  const choices = cohorts.state.value.data.filter(
    (cohort) => cohort.status === 'active' && !assigned.has(cohort.id),
  )

  if (choices.length === 0) {
    return null
  }

  return (
    <form
      className="operator-people__form operator-people__form--inline"
      onSubmit={(event) => {
        event.preventDefault()
        const cohort = choices.find((candidate) => candidate.id === cohortId)

        if (cohort === undefined) {
          return
        }

        void command
          .run(
            `trainer.assign_cohort:${trainer.membership_id}:${cohort.id}:${String(trainer.version)}:${String(cohort.version)}`,
            (idempotencyKey) => onAssign(cohort, idempotencyKey),
          )
          .then((completed) => {
            if (completed) {
              setCohortId('')
            }
          })
      }}
    >
      <Field
        id={`operator-trainer-cohort-${trainer.membership_id}`}
        label={message('operator.people.trainers.assign_cohort').text}
      >
        <select
          id={`operator-trainer-cohort-${trainer.membership_id}`}
          onChange={(event) => setCohortId(event.currentTarget.value)}
          required
          value={cohortId}
        >
          <option value="">{message('operator.people.select').text}</option>
          {choices.map((cohort) => (
            <option key={cohort.id} value={cohort.id}>
              {cohort.name} ({cohort.key})
            </option>
          ))}
        </select>
      </Field>
      <button disabled={command.busy || cohortId === ''} type="submit">
        {message('operator.people.action.assign').text}
      </button>
    </form>
  )
}

function SafeAppointmentList({
  appointments,
  locale,
  message,
}: Readonly<{
  appointments: readonly OperatorAuthorityAppointment[]
  locale: SupportedLocale
  message: MessageResolver
}>) {
  return (
    <div className="operator-people__relationships">
      <h5>{message('operator.people.appointments.title').text}</h5>
      {appointments.length === 0 ? (
        <p>{message('operator.people.empty').text}</p>
      ) : (
        <ul className="operator-people__compact-list">
          {appointments.map((appointment) => (
            <li key={appointment.id}>
              <code>{appointment.authority_kind}</code>
              <span>
                {appointment.scope_type} · {appointment.state}
              </span>
              <time dateTime={appointment.valid_from}>
                {formatDateTime(appointment.valid_from, locale)}
              </time>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function AuthorityAppointmentsPanel({
  api,
  canReadTrainers,
  createCommandId,
  currentMembershipId,
  locale,
  message,
}: CommonPanelProps &
  Readonly<{
    canReadTrainers: boolean
    currentMembershipId: string
  }>) {
  const [membershipId, setMembershipId] = useState(
    canReadTrainers ? '' : currentMembershipId,
  )
  const loadTrainers = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listTrainerProfiles(undefined, cursor, signal),
    [api],
  )
  const trainers = usePagedResource<
    OperatorTrainerProfile,
    OperatorCollection<OperatorTrainerProfile>
  >(loadTrainers, canReadTrainers)
  const selectedMembershipId =
    membershipId !== ''
      ? membershipId
      : trainers.state.status === 'ready'
        ? (trainers.state.value.data[0]?.membership_id ?? '')
        : ''

  const loadAppointments = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listAuthorityAppointments(selectedMembershipId, cursor, signal),
    [api, selectedMembershipId],
  )
  const appointments = usePagedResource<
    OperatorAuthorityAppointment,
    OperatorCollection<OperatorAuthorityAppointment>
  >(loadAppointments, selectedMembershipId !== '')
  const command = useCommandRunner(createCommandId, appointments.reload)
  const profile =
    trainers.state.status === 'ready'
      ? trainers.state.value.data.find(
          (trainer) => trainer.membership_id === selectedMembershipId,
        ) ?? null
      : null
  const [detail, setDetail] =
    useState<ResourceState<OperatorAuthorityAppointmentDetail> | null>(null)

  return (
    <PeoplePanel
      headingId="operator-authority-appointments-title"
      leadKey="operator.people.appointments.lead"
      message={message}
      titleKey="operator.people.appointments.title"
      wide
    >
      {canReadTrainers ? (
        trainers.state.status === 'loading' ? (
          <PanelLoading message={message} />
        ) : trainers.state.status === 'error' ? (
          <PanelFailure message={message} onRetry={trainers.reload} />
        ) : trainers.state.status === 'disabled' ? null : trainers.state.value
            .data.length === 0 ? (
          <PanelEmpty message={message} />
        ) : (
          <>
            <Field
              id="operator-appointment-membership"
              label={message('operator.people.appointments.trainer').text}
            >
              <select
                id="operator-appointment-membership"
                onChange={(event) => {
                  setMembershipId(event.currentTarget.value)
                  setDetail(null)
                }}
                value={selectedMembershipId}
              >
                {trainers.state.value.data.map((trainer) => (
                  <option
                    key={trainer.membership_id}
                    value={trainer.membership_id}
                  >
                    {trainer.display_name ?? trainer.membership_id}
                  </option>
                ))}
              </select>
            </Field>
            <PageControls
              contextLabel={message('operator.people.trainers.title').text}
              message={message}
              resource={trainers}
            />
          </>
        )
      ) : null}

      {selectedMembershipId === '' ? null : (
        <CollectionState resource={appointments} message={message}>
          {(page) => (
            <>
              {hasAction(page.allowed_actions, 'appointment.create') &&
              profile !== null ? (
                <AppointmentCreateForm
                  command={command}
                  membershipId={selectedMembershipId}
                  message={message}
                  onCreate={(body, idempotencyKey) =>
                    api.createAuthorityAppointment(
                      selectedMembershipId,
                      body,
                      idempotencyKey,
                    )
                  }
                  profileVersion={profile.version}
                />
              ) : null}
              {page.data.length === 0 ? <PanelEmpty message={message} /> : null}
              <ul className="operator-people__cards operator-people__cards--wide">
                {page.data.map((appointment) => (
                  <li key={appointment.id}>
                    <ResourceHeading
                      code={appointment.authority_kind}
                      name={message('operator.people.appointments.appointment').text}
                      status={appointment.state}
                    />
                    <dl className="operator-people__details">
                      <Detail label={message('operator.people.field.scope').text}>
                        <code>{appointment.scope_type}</code>
                        {appointment.program_id === null ? null : (
                          <> · <code>{appointment.program_id}</code></>
                        )}
                      </Detail>
                      <Detail label={message('operator.people.field.valid_from').text}>
                        <time dateTime={appointment.valid_from}>
                          {formatDateTime(appointment.valid_from, locale)}
                        </time>
                      </Detail>
                      <Detail label={message('operator.people.field.valid_until').text}>
                        {appointment.valid_until === null ? (
                          message('operator.people.not_recorded').text
                        ) : (
                          <time dateTime={appointment.valid_until}>
                            {formatDateTime(appointment.valid_until, locale)}
                          </time>
                        )}
                      </Detail>
                    </dl>
                    <div className="operator-people__actions">
                      <button
                        onClick={() => {
                          setDetail({ status: 'loading' })
                          void api
                            .getAuthorityAppointment(
                              selectedMembershipId,
                              appointment.id,
                            )
                            .then((value) => setDetail({ status: 'ready', value }))
                            .catch(() => setDetail({ status: 'error' }))
                        }}
                        type="button"
                      >
                        {message('operator.people.action.view_details').text}
                      </button>
                      {hasAction(
                        appointment.allowed_actions,
                        'appointment.accept',
                      ) ? (
                        <button
                          disabled={command.busy}
                          onClick={() => {
                            void command.run(
                              `appointment.accept:${appointment.id}:${appointment.state_fingerprint}`,
                              (idempotencyKey) =>
                                api.acceptAuthorityAppointment(
                                  selectedMembershipId,
                                  appointment.id,
                                  {
                                    state_fingerprint:
                                      appointment.state_fingerprint,
                                  },
                                  idempotencyKey,
                                ),
                            )
                          }}
                          type="button"
                        >
                          {message('operator.people.action.accept').text}
                        </button>
                      ) : null}
                      {hasAction(
                        appointment.allowed_actions,
                        'appointment.surrender',
                      ) ? (
                        <ConfirmAction
                          busy={command.busy}
                          confirmText={message(
                            'operator.people.confirm.surrender_appointment',
                          ).text}
                          label={message(
                            'operator.people.action.surrender',
                          ).text}
                          message={message}
                          onConfirm={() =>
                            command.run(
                              `appointment.surrender:${appointment.id}:${appointment.state_fingerprint}`,
                              (idempotencyKey) =>
                                api.surrenderAuthorityAppointment(
                                  selectedMembershipId,
                                  appointment.id,
                                  {
                                    state_fingerprint:
                                      appointment.state_fingerprint,
                                  },
                                  idempotencyKey,
                                ),
                            )
                          }
                        />
                      ) : null}
                      {hasAction(
                        appointment.allowed_actions,
                        'appointment.revoke',
                      ) ? (
                        <ConfirmAction
                          busy={command.busy}
                          confirmText={message(
                            'operator.people.confirm.revoke_appointment',
                          ).text}
                          label={message('operator.people.action.revoke').text}
                          message={message}
                          onConfirm={() =>
                            command.run(
                              `appointment.revoke:${appointment.id}:${appointment.state_fingerprint}`,
                              (idempotencyKey) =>
                                api.revokeAuthorityAppointment(
                                  selectedMembershipId,
                                  appointment.id,
                                  {
                                    reason_key: 'operator_revoked',
                                    state_fingerprint:
                                      appointment.state_fingerprint,
                                  },
                                  idempotencyKey,
                                ),
                            )
                          }
                        />
                      ) : null}
                    </div>
                  </li>
                ))}
              </ul>
              <PageControls message={message} resource={appointments} />
            </>
          )}
        </CollectionState>
      )}

      <AppointmentDetail detail={detail} message={message} />
      <CommandFeedback
        message={message}
        onReload={appointments.reload}
        state={command.state}
      />
    </PeoplePanel>
  )
}

function AppointmentCreateForm({
  command,
  membershipId,
  message,
  onCreate,
  profileVersion,
}: Readonly<{
  command: CommandRunner
  membershipId: string
  message: MessageResolver
  onCreate: (
    body: Parameters<OperatorOrganizationApi['createAuthorityAppointment']>[1],
    idempotencyKey: string,
  ) => Promise<unknown>
  profileVersion: number
}>) {
  const [authorityKind, setAuthorityKind] =
    useState<OperatorAuthorityKind>('english_academic_reviewer')
  const [scopeType, setScopeType] = useState<'organization' | 'program'>(
    'organization',
  )
  const [programId, setProgramId] = useState('')
  const [validFrom, setValidFrom] = useState('')
  const [validUntil, setValidUntil] = useState('')
  const [qualification, setQualification] = useState('')
  const [evidenceKey, setEvidenceKey] = useState('')

  return (
    <details className="operator-people__editor">
      <summary>{message('operator.people.appointments.create').text}</summary>
      <form
        className="operator-people__form"
        onSubmit={(event) => {
          event.preventDefault()
          const body = {
            approval_evidence_key: evidenceKey.trim(),
            authority_kind: authorityKind,
            expected_membership_version: profileVersion,
            program_id: scopeType === 'program' ? emptyToNull(programId) : null,
            qualification_basis: qualification.trim(),
            scope_type: scopeType,
            valid_from: toInstant(validFrom),
            valid_until:
              validUntil === '' ? null : toInstant(validUntil),
          }
          void command.run(
            `appointment.create:${membershipId}:${JSON.stringify(body)}`,
            (idempotencyKey) => onCreate(body, idempotencyKey),
          )
        }}
      >
        <Field
          id={`operator-appointment-kind-${membershipId}`}
          label={message('operator.people.field.authority_kind').text}
        >
          <select
            id={`operator-appointment-kind-${membershipId}`}
            onChange={(event) =>
              setAuthorityKind(event.currentTarget.value as OperatorAuthorityKind)
            }
            value={authorityKind}
          >
            <option value="english_academic_reviewer">
              english_academic_reviewer
            </option>
            <option value="kannada_reviewer">kannada_reviewer</option>
            <option value="rights_confirmer">rights_confirmer</option>
          </select>
        </Field>
        <Field
          id={`operator-appointment-scope-${membershipId}`}
          label={message('operator.people.field.scope').text}
        >
          <select
            id={`operator-appointment-scope-${membershipId}`}
            onChange={(event) => {
              const next =
                event.currentTarget.value === 'program'
                  ? 'program'
                  : 'organization'
              setScopeType(next)
              if (next === 'organization') {
                setProgramId('')
              }
            }}
            value={scopeType}
          >
            <option value="organization">organization</option>
            <option value="program">program</option>
          </select>
        </Field>
        {scopeType === 'program' ? (
          <Field
            id={`operator-appointment-program-${membershipId}`}
            label={message('operator.people.field.program_id').text}
          >
            <input
              autoCapitalize="none"
              autoComplete="off"
              id={`operator-appointment-program-${membershipId}`}
              onChange={(event) => setProgramId(event.currentTarget.value)}
              required
              spellCheck={false}
              value={programId}
            />
          </Field>
        ) : null}
        <Field
          id={`operator-appointment-from-${membershipId}`}
          label={message('operator.people.field.valid_from').text}
        >
          <input
            id={`operator-appointment-from-${membershipId}`}
            onChange={(event) => setValidFrom(event.currentTarget.value)}
            required
            type="datetime-local"
            value={validFrom}
          />
        </Field>
        <Field
          id={`operator-appointment-until-${membershipId}`}
          label={message('operator.people.field.valid_until').text}
        >
          <input
            id={`operator-appointment-until-${membershipId}`}
            onChange={(event) => setValidUntil(event.currentTarget.value)}
            type="datetime-local"
            value={validUntil}
          />
        </Field>
        <Field
          id={`operator-appointment-qualification-${membershipId}`}
          label={message('operator.people.field.qualification').text}
        >
          <textarea
            id={`operator-appointment-qualification-${membershipId}`}
            maxLength={1000}
            onChange={(event) => setQualification(event.currentTarget.value)}
            required
            value={qualification}
          />
        </Field>
        <Field
          id={`operator-appointment-evidence-${membershipId}`}
          label={message('operator.people.field.evidence_key').text}
        >
          <input
            autoCapitalize="none"
            autoComplete="off"
            id={`operator-appointment-evidence-${membershipId}`}
            maxLength={120}
            onChange={(event) => setEvidenceKey(event.currentTarget.value)}
            required
            spellCheck={false}
            value={evidenceKey}
          />
        </Field>
        <button disabled={command.busy} type="submit">
          {message('operator.people.action.create').text}
        </button>
      </form>
    </details>
  )
}

function AppointmentDetail({
  detail,
  message,
}: Readonly<{
  detail: ResourceState<OperatorAuthorityAppointmentDetail> | null
  message: MessageResolver
}>) {
  if (detail === null) {
    return null
  }

  return (
    <aside className="operator-people__protected-detail">
      <h5>{message('operator.people.appointments.detail').text}</h5>
      {detail.status === 'loading' ? <PanelLoading message={message} /> : null}
      {detail.status === 'error' ? (
        <p role="alert">{message('operator.people.detail_unavailable').text}</p>
      ) : null}
      {detail.status === 'ready' ? (
        <dl className="operator-people__details">
          <Detail label={message('operator.people.field.qualification').text}>
            <SafeText>{detail.value.qualification_basis}</SafeText>
          </Detail>
          <Detail label={message('operator.people.field.evidence_key').text}>
            <code>{detail.value.approval_evidence_key}</code>
          </Detail>
        </dl>
      ) : null}
    </aside>
  )
}

function MembershipSessionsPanel({
  api,
  createCommandId,
  locale,
  message,
}: CommonPanelProps) {
  const loadMemberships = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listMemberships(undefined, cursor, signal),
    [api],
  )
  const memberships = usePagedResource<
    OperatorStaffMembership,
    OperatorCollection<OperatorStaffMembership>
  >(loadMemberships)
  const [membershipId, setMembershipId] = useState('')
  const selectedMembershipId =
    membershipId !== ''
      ? membershipId
      : memberships.state.status === 'ready'
        ? (memberships.state.value.data[0]?.membership_id ?? '')
        : ''

  const loadSessions = useCallback(
    (signal: AbortSignal) =>
      api.getMembershipSessions(selectedMembershipId, signal),
    [api, selectedMembershipId],
  )
  const sessions = useResource(loadSessions, selectedMembershipId !== '')
  const command = useCommandRunner(createCommandId, sessions.reload)

  return (
    <PeoplePanel
      headingId="operator-membership-sessions-title"
      leadKey="operator.people.sessions.lead"
      message={message}
      titleKey="operator.people.sessions.title"
    >
      {memberships.state.status === 'loading' ? (
        <PanelLoading message={message} />
      ) : memberships.state.status === 'error' ? (
        <PanelFailure message={message} onRetry={memberships.reload} />
      ) : memberships.state.status === 'disabled' ? null : memberships.state
          .value.data.length === 0 ? (
        <PanelEmpty message={message} />
      ) : (
        <>
          <Field
            id="operator-session-membership"
            label={message('operator.people.sessions.membership').text}
          >
            <select
              id="operator-session-membership"
              onChange={(event) => setMembershipId(event.currentTarget.value)}
              value={selectedMembershipId}
            >
              {memberships.state.value.data.map((membership) => (
                <option
                  key={membership.membership_id}
                  value={membership.membership_id}
                >
                  {membership.display_name ?? membership.membership_id}
                </option>
              ))}
            </select>
          </Field>
          <PageControls
            contextLabel={message('operator.people.memberships.title').text}
            message={message}
            resource={memberships}
          />
        </>
      )}

      {selectedMembershipId === '' || sessions.state.status === 'disabled' ? null :
      sessions.state.status === 'loading' ? (
        <PanelLoading message={message} />
      ) : sessions.state.status === 'error' ? (
        <PanelFailure message={message} onRetry={sessions.reload} />
      ) : (
        <div className="operator-people__session-counts">
          <dl className="operator-people__details">
            <Detail label={message('operator.people.sessions.web').text}>
              {sessions.state.value.active_web_session_count}
            </Detail>
            <Detail label={message('operator.people.sessions.android').text}>
              {sessions.state.value.active_android_family_count}
            </Detail>
            <Detail label={message('operator.people.sessions.latest').text}>
              {sessions.state.value.latest_activity_at === null ? (
                message('operator.people.not_recorded').text
              ) : (
                <time dateTime={sessions.state.value.latest_activity_at}>
                  {formatDateTime(
                    sessions.state.value.latest_activity_at,
                    locale,
                  )}
                </time>
              )}
            </Detail>
          </dl>
          {hasAction(
            sessions.state.value.allowed_actions,
            'membership_session.revoke',
          ) ? (
            <ConfirmAction
              busy={command.busy}
              confirmText={message(
                'operator.people.confirm.revoke_sessions',
              ).text}
              label={message('operator.people.sessions.revoke').text}
              message={message}
              onConfirm={() =>
                command.run(
                  `membership_session.revoke:${selectedMembershipId}:${sessions.state.status === 'ready' ? sessions.state.value.state_fingerprint : ''}`,
                  (idempotencyKey) =>
                    api.revokeMembershipSessions(
                      selectedMembershipId,
                      {
                        state_fingerprint:
                          sessions.state.status === 'ready'
                            ? sessions.state.value.state_fingerprint
                            : '',
                      },
                      idempotencyKey,
                    ),
                )
              }
            />
          ) : null}
        </div>
      )}
      <CommandFeedback
        message={message}
        onReload={sessions.reload}
        state={command.state}
      />
    </PeoplePanel>
  )
}

interface ResourceController<T> {
  readonly reload: () => void
  readonly state:
    | ResourceState<T>
    | { readonly status: 'disabled' }
}

interface PagedResource<T, P extends OperatorCollection<T>> {
  readonly loadMore: () => void
  readonly loadingMore: boolean
  readonly moreError: boolean
  readonly reload: () => void
  readonly state:
    | ResourceState<P>
    | { readonly status: 'disabled' }
}

interface CommandRunner {
  readonly busy: boolean
  readonly run: (
    signature: string,
    operation: (idempotencyKey: string) => Promise<unknown>,
  ) => Promise<boolean>
  readonly state: CommandState
}

function useResource<T>(
  load: (signal: AbortSignal) => Promise<T>,
  enabled = true,
): ResourceController<T> {
  const [attempt, setAttempt] = useState(0)
  const [result, setResult] = useState<{
    readonly attempt: number
    readonly load: typeof load
    readonly state: ResourceState<T>
  }>({ attempt, load, state: { status: 'loading' } })

  useEffect(() => {
    if (!enabled) {
      return
    }

    const controller = new AbortController()
    let active = true

    void load(controller.signal)
      .then((value) => {
        if (active) {
          setResult({
            attempt,
            load,
            state: { status: 'ready', value },
          })
        }
      })
      .catch(() => {
        if (active && !controller.signal.aborted) {
          setResult({ attempt, load, state: { status: 'error' } })
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [attempt, enabled, load])

  return {
    reload: () => setAttempt((current) => current + 1),
    state:
      !enabled
        ? { status: 'disabled' }
        : result.attempt === attempt && result.load === load
          ? result.state
          : { status: 'loading' },
  }
}

function usePagedResource<
  T,
  P extends OperatorCollection<T> = OperatorCollection<T>,
>(
  load: (cursor: string | undefined, signal: AbortSignal) => Promise<P>,
  enabled = true,
): PagedResource<T, P> {
  const [attempt, setAttempt] = useState(0)
  const [result, setResult] = useState<{
    readonly attempt: number
    readonly load: typeof load
    readonly state: ResourceState<P>
  }>({ attempt, load, state: { status: 'loading' } })
  const [loadingMore, setLoadingMore] = useState(false)
  const [moreError, setMoreError] = useState(false)
  const controller = useRef<AbortController | null>(null)

  const execute = useCallback(
    (cursor: string | undefined, append: boolean) => {
      controller.current?.abort()
      const request = new AbortController()
      controller.current = request

      if (append) {
        setLoadingMore(true)
        setMoreError(false)
      }

      void load(cursor, request.signal)
        .then((page) => {
          if (request.signal.aborted) {
            return
          }

          setResult((current) => {
            if (
              !append ||
              current.attempt !== attempt ||
              current.load !== load ||
              current.state.status !== 'ready'
            ) {
              return {
                attempt,
                load,
                state: { status: 'ready', value: page },
              }
            }

            return {
              attempt,
              load,
              state: {
                status: 'ready',
                value: {
                  ...page,
                  data: [...current.state.value.data, ...page.data],
                } as P,
              },
            }
          })
          setLoadingMore(false)
        })
        .catch(() => {
          if (request.signal.aborted) {
            return
          }

          if (append) {
            setMoreError(true)
            setLoadingMore(false)
          } else {
            setResult({ attempt, load, state: { status: 'error' } })
          }
        })
    },
    [attempt, load],
  )

  useEffect(() => {
    if (!enabled) {
      controller.current?.abort()
      return
    }

    controller.current?.abort()
    const request = new AbortController()
    controller.current = request

    void load(undefined, request.signal)
      .then((page) => {
        if (!request.signal.aborted) {
          setResult({
            attempt,
            load,
            state: { status: 'ready', value: page },
          })
        }
      })
      .catch(() => {
        if (!request.signal.aborted) {
          setResult({ attempt, load, state: { status: 'error' } })
        }
      })

    return () => request.abort()
  }, [attempt, enabled, load])

  const state: PagedResource<T, P>['state'] =
    !enabled
      ? { status: 'disabled' }
      : result.attempt === attempt && result.load === load
        ? result.state
        : { status: 'loading' }

  return {
    loadMore: () => {
      if (state.status === 'ready' && state.value.next_cursor !== null) {
        execute(state.value.next_cursor, true)
      }
    },
    loadingMore,
    moreError,
    reload: () => {
      setMoreError(false)
      setAttempt((current) => current + 1)
    },
    state,
  }
}

function useCommandRunner(
  createCommandId: () => string,
  onSuccess: () => void,
): CommandRunner {
  const [state, setState] = useState<CommandState>({ status: 'idle' })
  const commandIds = useRef(new Map<string, string>())

  return {
    busy: state.status === 'busy',
    run: async (signature, operation) => {
      const idempotencyKey =
        commandIds.current.get(signature) ?? createCommandId()
      commandIds.current.set(signature, idempotencyKey)
      setState({ status: 'busy' })

      try {
        await operation(idempotencyKey)
        commandIds.current.delete(signature)
        setState({ status: 'success' })
        onSuccess()

        return true
      } catch (error) {
        const kind =
          error instanceof OperatorOrganizationApiError
            ? error.kind
            : 'unavailable'

        if (kind === 'idempotency_conflict') {
          commandIds.current.delete(signature)
        }

        setState({
          kind,
          status: 'error',
        })

        return false
      }
    },
    state,
  }
}

function PeoplePanel({
  children,
  headingId,
  leadKey,
  message,
  titleKey,
  wide = false,
}: Readonly<{
  children: ReactNode
  headingId: string
  leadKey: MessageKey
  message: MessageResolver
  titleKey: MessageKey
  wide?: boolean
}>) {
  return (
    <article
      aria-labelledby={headingId}
      className={`operator-panel operator-people__panel${wide ? ' operator-panel--wide' : ''}`}
    >
      <h4 id={headingId}>
        <LocalizedMessage resolution={message(titleKey)} />
      </h4>
      <p>
        <LocalizedMessage resolution={message(leadKey)} />
      </p>
      {children}
    </article>
  )
}

function CollectionState<T, P extends OperatorCollection<T>>({
  children,
  message,
  resource,
}: Readonly<{
  children: (page: P) => ReactNode
  message: MessageResolver
  resource: PagedResource<T, P>
}>) {
  if (resource.state.status === 'disabled') {
    return null
  }

  if (resource.state.status === 'loading') {
    return <PanelLoading message={message} />
  }

  if (resource.state.status === 'error') {
    return <PanelFailure message={message} onRetry={resource.reload} />
  }

  return children(resource.state.value)
}

function PanelLoading({ message }: Readonly<{ message: MessageResolver }>) {
  return (
    <p className="operator-panel__loading" role="status">
      <LocalizedMessage resolution={message('operator.panel.loading')} />
    </p>
  )
}

function PanelFailure({
  message,
  onRetry,
}: Readonly<{ message: MessageResolver; onRetry: () => void }>) {
  return (
    <div className="operator-panel__failure" role="alert">
      <p>
        <LocalizedMessage resolution={message('operator.panel.error')} />
      </p>
      <button onClick={onRetry} type="button">
        <LocalizedMessage resolution={message('operator.retry')} />
      </button>
    </div>
  )
}

function PanelEmpty({ message }: Readonly<{ message: MessageResolver }>) {
  return <p>{message('operator.people.empty').text}</p>
}

function PageControls<T, P extends OperatorCollection<T>>({
  contextLabel,
  message,
  resource,
}: Readonly<{
  contextLabel?: string
  message: MessageResolver
  resource: PagedResource<T, P>
}>) {
  if (resource.state.status !== 'ready') {
    return null
  }

  return (
    <div className="operator-people__pagination">
      {resource.moreError ? (
        <p role="alert">{message('operator.people.more_error').text}</p>
      ) : null}
      {resource.state.value.next_cursor === null ? null : (
        <button
          aria-label={
            contextLabel === undefined
              ? undefined
              : `${message('operator.load_more').text}: ${contextLabel}`
          }
          disabled={resource.loadingMore}
          onClick={resource.loadMore}
          type="button"
        >
          {message(
            resource.loadingMore
              ? 'operator.loading_more'
              : 'operator.load_more',
          ).text}
        </button>
      )}
    </div>
  )
}

function Field({
  children,
  hint,
  id,
  label,
}: Readonly<{
  children: ReactNode
  hint?: string
  id: string
  label: string
}>) {
  return (
    <div className="operator-people__field">
      <label htmlFor={id}>{label}</label>
      {children}
      {hint === undefined ? null : <small id={`${id}-hint`}>{hint}</small>}
    </div>
  )
}

function ResourceHeading({
  code,
  name,
  status,
}: Readonly<{ code: string; name: string; status: string }>) {
  return (
    <div className="operator-people__resource-heading">
      <div>
        <strong>
          <SafeText>{name}</SafeText>
        </strong>
        <code>{code}</code>
      </div>
      <span>{status}</span>
    </div>
  )
}

function ResourceMeta({
  id,
  locale,
  message,
  updatedAt,
  version,
}: Readonly<{
  id: string
  locale: SupportedLocale
  message: MessageResolver
  updatedAt: string
  version: number
}>) {
  return (
    <dl className="operator-people__details">
      <Detail label={message('operator.people.field.identifier').text}>
        <code>{id}</code>
      </Detail>
      <Detail label={message('operator.people.field.version').text}>
        {version}
      </Detail>
      <Detail label={message('operator.people.field.updated').text}>
        <time dateTime={updatedAt}>{formatDateTime(updatedAt, locale)}</time>
      </Detail>
    </dl>
  )
}

function Detail({
  children,
  label,
}: Readonly<{ children: ReactNode; label: string }>) {
  return (
    <div>
      <dt>{label}</dt>
      <dd>{children}</dd>
    </div>
  )
}

function SafeText({ children }: Readonly<{ children: string }>) {
  return <bdi lang="">{children}</bdi>
}

function ConfirmAction({
  busy,
  confirmText,
  label,
  message,
  onConfirm,
}: Readonly<{
  busy: boolean
  confirmText: string
  label: string
  message: MessageResolver
  onConfirm: () => Promise<boolean>
}>) {
  const [confirming, setConfirming] = useState(false)
  const trigger = useRef<HTMLButtonElement>(null)

  const finish = () => {
    setConfirming(false)
    globalThis.setTimeout(() => trigger.current?.focus(), 0)
  }

  return (
    <div className="operator-people__confirmation">
      <button
        aria-expanded={confirming}
        disabled={busy}
        onClick={() => setConfirming(true)}
        ref={trigger}
        type="button"
      >
        {label}
      </button>
      {confirming ? (
        <div className="operator-people__confirmation-prompt" role="group">
          <p>{confirmText}</p>
          <div className="operator-people__actions">
            <button disabled={busy} onClick={finish} type="button">
              {message('operator.people.action.cancel').text}
            </button>
            <button
              disabled={busy}
              onClick={() => {
                void onConfirm().finally(finish)
              }}
              type="button"
            >
              {message('operator.people.action.confirm').text}
            </button>
          </div>
        </div>
      ) : null}
    </div>
  )
}

function CommandFeedback({
  message,
  onReload,
  state,
}: Readonly<{
  message: MessageResolver
  onReload: () => void
  state: CommandState
}>) {
  if (state.status === 'idle' || state.status === 'busy') {
    return null
  }

  if (state.status === 'success') {
    return (
      <p className="operator-people__feedback" role="status">
        {message('operator.people.command.success').text}
      </p>
    )
  }

  const errorKey: Readonly<
    Record<OperatorOrganizationErrorKind, MessageKey>
  > = {
    active_dependency: 'operator.people.command.active_dependency',
    forbidden: 'operator.people.command.forbidden',
    fresh_mfa: 'operator.people.command.fresh_mfa',
    idempotency_conflict: 'operator.people.command.idempotency_conflict',
    last_manager_required: 'operator.people.command.last_manager_required',
    self_target_forbidden: 'operator.people.command.self_target_forbidden',
    stale_state: 'operator.people.command.conflict',
    unavailable: 'operator.people.command.unavailable',
    validation: 'operator.people.command.validation',
  }

  return (
    <div className="operator-people__feedback" role="alert">
      <p>{message(errorKey[state.kind]).text}</p>
      {state.kind === 'fresh_mfa' ? (
        <a href="#operator-account-settings-title">
          {message('operator.people.command.open_mfa').text}
        </a>
      ) : null}
      {state.kind === 'stale_state' ? (
        <button onClick={onReload} type="button">
          {message('operator.people.command.reload').text}
        </button>
      ) : null}
    </div>
  )
}

function hasAction(
  actions: readonly OperatorOrganizationAction[],
  action: OperatorOrganizationAction,
): boolean {
  return actions.includes(action)
}

function emptyToNull(value: string): string | null {
  const normalized = value.trim()
  return normalized === '' ? null : normalized
}

function sorted(values: readonly string[]): readonly string[] {
  return [...new Set(values)].sort((left, right) => left.localeCompare(right))
}

function toInstant(value: string): string {
  const date = new Date(value)
  return Number.isNaN(date.valueOf()) ? value : date.toISOString()
}

function formatDateTime(value: string, locale: SupportedLocale): string {
  const date = new Date(value)

  if (Number.isNaN(date.valueOf())) {
    return value
  }

  return new Intl.DateTimeFormat(locale, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}
