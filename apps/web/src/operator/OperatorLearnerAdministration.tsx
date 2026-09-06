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
  OperatorLearnerApiError,
  type OperatorLearner,
  type OperatorLearnerAction,
  type OperatorLearnerApi,
  type OperatorLearnerChoice,
  type OperatorLearnerCollection,
  type OperatorLearnerEnrollment,
  type OperatorLearnerEnrollmentCollection,
  type OperatorLearnerErrorKind,
  type OperatorLearnerFilters,
  type OperatorLearnerGoalKey,
  type OperatorLearnerInvitation,
  type OperatorLearnerInvitationCollection,
  type OperatorLearnerInvitationIssuance,
  type OperatorLearnerPanelKey,
  type OperatorTuitionRequest,
} from './OperatorLearnerApi'

type MessageResolver = (key: MessageKey) => InterfaceMessageResolution

type ResourceState<T> =
  | { readonly status: 'loading' }
  | { readonly status: 'error' }
  | { readonly status: 'ready'; readonly value: T }

type CommandState =
  | { readonly status: 'idle' }
  | { readonly status: 'busy' }
  | { readonly status: 'success' }
  | { readonly status: 'error'; readonly kind: OperatorLearnerErrorKind }

interface Page<T> {
  readonly data: readonly T[]
  readonly next_cursor: string | null
}

interface PagedResource<T, P extends Page<T>> {
  readonly loadMore: () => void
  readonly loadingMore: boolean
  readonly moreError: boolean
  readonly reload: () => void
  readonly state: ResourceState<P> | { readonly status: 'disabled' }
}

interface CommandRunner {
  readonly busy: boolean
  readonly run: (
    signature: string,
    operation: (idempotencyKey: string) => Promise<unknown>,
  ) => Promise<boolean>
  readonly state: CommandState
}

const goalKeys: readonly OperatorLearnerGoalKey[] = [
  'everyday-conversation',
  'workplace-confidence',
  'travel-confidence',
]

export interface OperatorLearnerAdministrationProps {
  readonly api: OperatorLearnerApi
  readonly createCommandId?: () => string
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly panels: readonly OperatorLearnerPanelKey[]
}

export function OperatorLearnerAdministration({
  api,
  createCommandId = createRandomClientId,
  locale,
  localizationPolicy,
  panels,
}: OperatorLearnerAdministrationProps) {
  const message: MessageResolver = (key) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const [filters, setFilters] = useState<OperatorLearnerFilters>({})
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const loadDirectory = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listLearners(filters, cursor, signal),
    [api, filters],
  )
  const directory = usePagedResource<OperatorLearner, OperatorLearnerCollection>(
    loadDirectory,
    panels.includes('learner_directory'),
  )
  const loadDetail = useCallback(
    (signal: AbortSignal) => {
      if (selectedId === null) return Promise.reject(new Error('No learner selected'))
      return api.getLearner(selectedId, signal)
    },
    [api, selectedId],
  )
  const detail = useResource(loadDetail, selectedId !== null)
  const selectedFromDirectory =
    directory.state.status === 'ready' && selectedId !== null
      ? directory.state.value.data.find(
          (learner) => learner.membership_id === selectedId,
        ) ?? null
      : null
  const selectedLearner =
    detail.state.status === 'ready' ? detail.state.value : selectedFromDirectory
  const cohorts =
    directory.state.status === 'ready'
      ? directory.state.value.choices?.cohorts ?? []
      : []

  const refreshLearner = () => {
    directory.reload()
    detail.reload()
  }

  return (
    <section
      aria-labelledby="operator-learners-title"
      className="operator-task-group operator-people operator-learners"
      id="operator-learners"
    >
      <div className="operator-task-group__heading">
        <p><LocalizedMessage resolution={message('operator.navigation.learners')} /></p>
        <h3 id="operator-learners-title">
          <LocalizedMessage resolution={message('operator.learners.title')} />
        </h3>
        <p className="operator-people__lead">
          <LocalizedMessage resolution={message('operator.learners.lead')} />
        </p>
      </div>

      <div className="operator-task-group__grid operator-people__grid">
        {panels.includes('learner_directory') ? (
          <DirectoryPanel
            api={api}
            cohorts={cohorts}
            createCommandId={createCommandId}
            detail={detail}
            directory={directory}
            filters={filters}
            locale={locale}
            localizationPolicy={localizationPolicy}
            message={message}
            onApplyFilters={setFilters}
            onRefresh={refreshLearner}
            onSelect={setSelectedId}
            selectedId={selectedId}
            selectedLearner={selectedLearner}
          />
        ) : null}

        {panels.includes('learner_enrollments') ? (
          <EnrollmentsPanel
            api={api}
            cohorts={cohorts}
            createCommandId={createCommandId}
            locale={locale}
            message={message}
            onRefreshLearner={refreshLearner}
            selectedLearner={selectedLearner}
          />
        ) : null}

        {panels.includes('learner_invitations') ? (
          <InvitationsPanel
            api={api}
            createCommandId={createCommandId}
            locale={locale}
            message={message}
          />
        ) : null}

        {panels.includes('learner_directory') ? (
          <TuitionQueuePanel
            api={api}
            createCommandId={createCommandId}
            locale={locale}
            message={message}
          />
        ) : null}
      </div>
    </section>
  )
}

function DirectoryPanel({
  api,
  cohorts,
  createCommandId,
  detail,
  directory,
  filters,
  locale,
  localizationPolicy,
  message,
  onApplyFilters,
  onRefresh,
  onSelect,
  selectedId,
  selectedLearner,
}: Readonly<{
  api: OperatorLearnerApi
  cohorts: readonly OperatorLearnerChoice[]
  createCommandId: () => string
  detail: ResourceController<OperatorLearner>
  directory: PagedResource<OperatorLearner, OperatorLearnerCollection>
  filters: OperatorLearnerFilters
  locale: SupportedLocale
  localizationPolicy: ResolutionPolicy
  message: MessageResolver
  onApplyFilters: (filters: OperatorLearnerFilters) => void
  onRefresh: () => void
  onSelect: (id: string) => void
  selectedId: string | null
  selectedLearner: OperatorLearner | null
}>) {
  const [query, setQuery] = useState(filters.q ?? '')
  const [status, setStatus] = useState(filters.status ?? '')
  const [goal, setGoal] = useState(filters.goal_key ?? '')
  const [cohortId, setCohortId] = useState(filters.cohort_id ?? '')
  const command = useCommandRunner(createCommandId, onRefresh)

  const applyFilters = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    onApplyFilters({
      ...(cohortId === '' ? {} : { cohort_id: cohortId }),
      ...(goal === '' ? {} : { goal_key: goal as OperatorLearnerGoalKey }),
      ...(query.trim() === '' ? {} : { q: query.trim() }),
      ...(status === '' ? {} : { status: status as OperatorLearner['status'] }),
    })
  }

  const resetFilters = () => {
    setQuery('')
    setStatus('')
    setGoal('')
    setCohortId('')
    onApplyFilters({})
  }

  return (
    <LearnerPanel
      headingId="operator-learner-directory-title"
      leadKey="operator.learners.directory.lead"
      message={message}
      titleKey="operator.learners.directory.title"
      wide
    >
      <form
        className="operator-directory__toolbar operator-people__form"
        onSubmit={applyFilters}
        role="search"
      >
        <Field id="operator-learner-search" label={message('operator.learners.field.search').text}>
          <input
            autoComplete="off"
            id="operator-learner-search"
            maxLength={80}
            onChange={(event) => setQuery(event.currentTarget.value)}
            placeholder={message('operator.directory.search_hint').text}
            value={query}
          />
        </Field>
        <Field id="operator-learner-status-filter" label={message('operator.people.field.status').text}>
          <select
            id="operator-learner-status-filter"
            onChange={(event) => setStatus(event.currentTarget.value)}
            value={status}
          >
            <option value="">{message('operator.learners.filter.all').text}</option>
            <option value="active">active</option>
            <option value="suspended">suspended</option>
            <option value="ended">ended</option>
          </select>
        </Field>
        <Field id="operator-learner-goal-filter" label={message('operator.learners.field.goal').text}>
          <select
            id="operator-learner-goal-filter"
            onChange={(event) => setGoal(event.currentTarget.value)}
            value={goal}
          >
            <option value="">{message('operator.learners.filter.all').text}</option>
            {goalKeys.map((key) => <option key={key} value={key}>{key}</option>)}
          </select>
        </Field>
        <Field id="operator-learner-cohort-filter" label={message('operator.learners.field.cohort').text}>
          <select
            id="operator-learner-cohort-filter"
            onChange={(event) => setCohortId(event.currentTarget.value)}
            value={cohortId}
          >
            <option value="">{message('operator.learners.filter.all').text}</option>
            {cohorts.map((cohort) => (
              <option key={cohort.id} value={cohort.id}>
                {cohort.name ?? cohort.key}
              </option>
            ))}
          </select>
        </Field>
        <div className="operator-directory__toolbar-actions">
          <button type="submit">{message('operator.learners.filter.apply').text}</button>
          <button onClick={resetFilters} type="button">
            {message('operator.directory.reset_filters').text}
          </button>
        </div>
      </form>

      <div className="operator-directory operator-directory--split">
        <div className="operator-directory__browse">
          <PagedState message={message} resource={directory}>
            {(page) => (
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
                  aria-label={message('operator.learners.directory.title').text}
                  className="operator-directory__list"
                >
                  {page.data.map((learner) => (
                    <li key={learner.membership_id}>
                      <button
                        aria-label={
                          learner.display_name ??
                          message('operator.people.name_unavailable').text
                        }
                        aria-pressed={selectedId === learner.membership_id}
                        className="operator-directory__row"
                        onClick={() => onSelect(learner.membership_id)}
                        type="button"
                      >
                        <span className="operator-directory__primary">
                          {learner.display_name ??
                            message('operator.people.name_unavailable').text}
                        </span>
                        <span className="operator-directory__meta">
                          <code>{learner.membership_id}</code>
                        </span>
                        <span className="operator-directory__meta">
                          {learner.status}
                        </span>
                        <span className="operator-directory__meta">
                          {learner.profile?.current_goal_key ??
                            message('operator.people.not_recorded').text}
                        </span>
                        <span className="operator-directory__meta">
                          {String(learner.attempt_count)}
                        </span>
                        <span className="operator-directory__meta">
                          {learner.latest_attempt_at === null
                            ? message('operator.people.not_recorded').text
                            : formatDateTime(learner.latest_attempt_at, locale)}
                        </span>
                      </button>
                    </li>
                  ))}
                </ul>
                <PageControls message={message} resource={directory} />
              </>
            )}
          </PagedState>
        </div>

        <aside className="operator-directory__detail">
          {selectedId === null ? (
            <p className="operator-directory__select-hint">
              {message('operator.learners.enrollments.select_prompt').text}
            </p>
          ) : (
            <LearnerDetail
              api={api}
              command={command}
              detail={detail}
              locale={locale}
              message={message}
              selectedLearner={selectedLearner}
            />
          )}
        </aside>
      </div>

      <CommandFeedback message={message} onReload={onRefresh} state={command.state} />
    </LearnerPanel>
  )
}

function LearnerDetail({
  api,
  command,
  detail,
  locale,
  message,
  selectedLearner,
}: Readonly<{
  api: OperatorLearnerApi
  command: CommandRunner
  detail: ResourceController<OperatorLearner>
  locale: SupportedLocale
  message: MessageResolver
  selectedLearner: OperatorLearner | null
}>) {
  if (detail.state.status === 'loading') return <PanelLoading message={message} />
  if (detail.state.status === 'error') {
    return <PanelFailure message={message} onRetry={detail.reload} />
  }
  if (selectedLearner === null) return null

  const learner = detail.state.status === 'ready' ? detail.state.value : selectedLearner
  const profile = learner.profile

  return (
    <section aria-labelledby="operator-learner-detail-title" className="operator-people__relationships">
      <h5 id="operator-learner-detail-title">{message('operator.learners.detail.title').text}</h5>
      <ResourceHeading
        code={learner.membership_id}
        name={learner.display_name ?? message('operator.people.name_unavailable').text}
        status={learner.status}
      />
      <dl className="operator-people__details">
        <Detail label={message('operator.people.field.version').text}>{learner.version}</Detail>
        <Detail label={message('operator.learners.field.joined').text}>
          <time dateTime={learner.joined_at}>{formatDateTime(learner.joined_at, locale)}</time>
        </Detail>
        <Detail label={message('operator.learners.field.ended').text}>
          {learner.ended_at === null
            ? message('operator.people.not_recorded').text
            : <time dateTime={learner.ended_at}>{formatDateTime(learner.ended_at, locale)}</time>}
        </Detail>
        <Detail label={message('operator.learners.field.adult_confirmed').text}>
          {yesNo(profile?.adult_confirmed ?? false, message)}
        </Detail>
        <Detail label={message('operator.learners.field.onboarding_completed').text}>
          {yesNo(profile?.onboarding_completed ?? false, message)}
        </Detail>
      </dl>
      {profile !== null && profile.current_goal_version !== null && hasAction(learner.allowed_actions, 'learner.goal_correct') ? (
        <GoalCorrectionForm api={api} command={command} learner={learner} message={message} />
      ) : null}
      <div className="operator-people__actions">
        {hasAction(learner.allowed_actions, 'learner.suspend') ? (
          <ConfirmAction
            busy={command.busy}
            confirmText={message('operator.learners.confirm.suspend').text}
            label={message('operator.learners.action.suspend').text}
            message={message}
            onConfirm={() => command.run(
              `learner.suspend:${learner.membership_id}:${String(learner.version)}`,
              (key) => api.suspendLearner(learner.membership_id, { expected_version: learner.version }, key),
            )}
          />
        ) : null}
        {hasAction(learner.allowed_actions, 'learner.reactivate') ? (
          <ConfirmAction
            busy={command.busy}
            confirmText={message('operator.learners.confirm.reactivate').text}
            label={message('operator.people.action.reactivate').text}
            message={message}
            onConfirm={() => command.run(
              `learner.reactivate:${learner.membership_id}:${String(learner.version)}`,
              (key) => api.reactivateLearner(learner.membership_id, { expected_version: learner.version }, key),
            )}
          />
        ) : null}
      </div>
    </section>
  )
}

function GoalCorrectionForm({ api, command, learner, message }: Readonly<{
  api: OperatorLearnerApi
  command: CommandRunner
  learner: OperatorLearner
  message: MessageResolver
}>) {
  const current = learner.profile?.current_goal_key
  const version = learner.profile?.current_goal_version
  const [goal, setGoal] = useState<OperatorLearnerGoalKey>(
    goalKeys.find((key) => key !== current) ?? 'everyday-conversation',
  )

  if (version === null || version === undefined) return null

  return (
    <form
      className="operator-people__form operator-people__form--inline"
      onSubmit={(event) => {
        event.preventDefault()
        void command.run(
          `learner.goal:${learner.membership_id}:${goal}:${String(version)}`,
          (key) => api.correctGoal(
            learner.membership_id,
            { expected_version: version, goal_key: goal },
            key,
          ),
        )
      }}
    >
      <h5>{message('operator.learners.goal.correct').text}</h5>
      <Field id={`learner-goal-${learner.membership_id}`} label={message('operator.learners.field.goal').text}>
        <select
          id={`learner-goal-${learner.membership_id}`}
          onChange={(event) => setGoal(event.currentTarget.value as OperatorLearnerGoalKey)}
          value={goal}
        >
          {goalKeys.filter((key) => key !== current).map((key) => <option key={key}>{key}</option>)}
        </select>
      </Field>
      <button disabled={command.busy} type="submit">{message('operator.learners.action.correct_goal').text}</button>
    </form>
  )
}

function EnrollmentsPanel({
  api,
  cohorts,
  createCommandId,
  locale,
  message,
  onRefreshLearner,
  selectedLearner,
}: Readonly<{
  api: OperatorLearnerApi
  cohorts: readonly OperatorLearnerChoice[]
  createCommandId: () => string
  locale: SupportedLocale
  message: MessageResolver
  onRefreshLearner: () => void
  selectedLearner: OperatorLearner | null
}>) {
  const learnerId = selectedLearner?.membership_id ?? null
  const load = useCallback(
    (cursor: string | undefined, signal: AbortSignal) => {
      if (learnerId === null) return Promise.reject(new Error('No learner selected'))
      return api.listEnrollments(learnerId, cursor, signal)
    },
    [api, learnerId],
  )
  const resource = usePagedResource<OperatorLearnerEnrollment, OperatorLearnerEnrollmentCollection>(
    load,
    learnerId !== null,
  )
  const reload = () => {
    resource.reload()
    onRefreshLearner()
  }
  const command = useCommandRunner(createCommandId, reload)

  return (
    <LearnerPanel
      headingId="operator-learner-enrollments-title"
      leadKey="operator.learners.enrollments.lead"
      message={message}
      titleKey="operator.learners.enrollments.title"
      wide
    >
      {learnerId === null ? <p>{message('operator.learners.enrollments.select_prompt').text}</p> : null}
      {selectedLearner !== null && hasAction(selectedLearner.allowed_actions, 'learner_enrollment.create') ? (
        <CreateEnrollmentForm
          api={api}
          cohorts={cohorts}
          command={command}
          learner={selectedLearner}
          message={message}
        />
      ) : null}
      <PagedState message={message} resource={resource}>
        {(page) => (
          <>
            {page.data.length === 0 ? <PanelEmpty message={message} /> : null}
            <ul className="operator-people__cards operator-people__cards--wide">
              {page.data.map((enrollment) => (
                <li key={enrollment.id}>
                  <ResourceHeading code={enrollment.id} name={message('operator.learners.enrollments.enrollment').text} status={enrollment.status} />
                  <EnrollmentDetails enrollment={enrollment} locale={locale} message={message} />
                  {hasAction(enrollment.allowed_actions, 'learner_enrollment.change_cohort') ? (
                    <ChangeCohortForm api={api} cohorts={cohorts} command={command} enrollment={enrollment} message={message} />
                  ) : null}
                  {hasAction(enrollment.allowed_actions, 'learner_enrollment.activate_curriculum') ? (
                    <ActivateEnrollmentForm api={api} command={command} enrollment={enrollment} message={message} />
                  ) : null}
                  {hasAction(enrollment.allowed_actions, 'learner_enrollment.withdraw') ? (
                    <ConfirmAction
                      busy={command.busy}
                      confirmText={message('operator.learners.confirm.withdraw').text}
                      label={message('operator.learners.action.withdraw').text}
                      message={message}
                      onConfirm={() => command.run(
                        `enrollment.withdraw:${enrollment.id}:${String(enrollment.version)}`,
                        (key) => api.withdrawEnrollment(enrollment.id, { expected_version: enrollment.version }, key),
                      )}
                    />
                  ) : null}
                </li>
              ))}
            </ul>
            <PageControls message={message} resource={resource} />
          </>
        )}
      </PagedState>
      <CommandFeedback message={message} onReload={reload} state={command.state} />
    </LearnerPanel>
  )
}

function CreateEnrollmentForm({ api, cohorts, command, learner, message }: Readonly<{
  api: OperatorLearnerApi
  cohorts: readonly OperatorLearnerChoice[]
  command: CommandRunner
  learner: OperatorLearner
  message: MessageResolver
}>) {
  const [cohortId, setCohortId] = useState('')
  const cohort = cohorts.find((choice) => choice.id === cohortId) ?? null
  return (
    <form
      className="operator-people__form"
      onSubmit={(event) => {
        event.preventDefault()
        const body = {
          cohort_id: cohort?.id ?? null,
          expected_cohort_version: cohort?.version ?? null,
          expected_membership_version: learner.version,
          expected_version: 0 as const,
        }
        void command.run(
          `enrollment.create:${learner.membership_id}:${JSON.stringify(body)}`,
          (key) => api.createEnrollment(learner.membership_id, body, key),
        )
      }}
    >
      <h5>{message('operator.learners.enrollments.create').text}</h5>
      <CohortSelect cohorts={cohorts} id="operator-new-enrollment-cohort" message={message} onChange={setCohortId} value={cohortId} />
      <button disabled={command.busy} type="submit">{message('operator.people.action.create').text}</button>
    </form>
  )
}

function ChangeCohortForm({ api, cohorts, command, enrollment, message }: Readonly<{
  api: OperatorLearnerApi
  cohorts: readonly OperatorLearnerChoice[]
  command: CommandRunner
  enrollment: OperatorLearnerEnrollment
  message: MessageResolver
}>) {
  const [cohortId, setCohortId] = useState(enrollment.cohort?.id ?? '')
  const selected = cohorts.find((choice) => choice.id === cohortId) ?? null
  return (
    <form
      className="operator-people__form operator-people__form--inline"
      onSubmit={(event) => {
        event.preventDefault()
        const body = {
          cohort_id: selected?.id ?? null,
          expected_cohort_version: selected?.version ?? null,
          expected_version: enrollment.version,
        }
        void command.run(
          `enrollment.cohort:${enrollment.id}:${JSON.stringify(body)}`,
          (key) => api.changeEnrollmentCohort(enrollment.id, body, key),
        )
      }}
    >
      <h5>{message('operator.learners.enrollments.change_cohort').text}</h5>
      <CohortSelect cohorts={cohorts} id={`operator-enrollment-cohort-${enrollment.id}`} message={message} onChange={setCohortId} value={cohortId} />
      <button disabled={command.busy} type="submit">{message('operator.people.action.save').text}</button>
    </form>
  )
}

function ActivateEnrollmentForm({ api, command, enrollment, message }: Readonly<{
  api: OperatorLearnerApi
  command: CommandRunner
  enrollment: OperatorLearnerEnrollment
  message: MessageResolver
}>) {
  const [publicationId, setPublicationId] = useState('')
  const [shareGrantId, setShareGrantId] = useState('')
  return (
    <form
      className="operator-people__form"
      onSubmit={(event) => {
        event.preventDefault()
        const body = {
          expected_version: enrollment.version,
          publication_id: publicationId.trim(),
          share_grant_id: emptyToNull(shareGrantId),
        }
        void command.run(
          `enrollment.activate:${enrollment.id}:${JSON.stringify(body)}`,
          (key) => api.activateEnrollment(enrollment.id, body, key),
        )
      }}
    >
      <h5>{message('operator.learners.enrollments.activate').text}</h5>
      <Field id={`operator-publication-${enrollment.id}`} label={message('operator.learners.field.publication').text}>
        <input autoCapitalize="characters" autoComplete="off" id={`operator-publication-${enrollment.id}`} maxLength={26} minLength={26} onChange={(event) => setPublicationId(event.currentTarget.value)} required spellCheck={false} value={publicationId} />
      </Field>
      <Field id={`operator-share-grant-${enrollment.id}`} label={message('operator.learners.field.share_grant').text}>
        <input autoCapitalize="characters" autoComplete="off" id={`operator-share-grant-${enrollment.id}`} maxLength={26} onChange={(event) => setShareGrantId(event.currentTarget.value)} spellCheck={false} value={shareGrantId} />
      </Field>
      <button disabled={command.busy} type="submit">{message('operator.learners.action.activate').text}</button>
    </form>
  )
}

function EnrollmentDetails({ enrollment, locale, message }: Readonly<{
  enrollment: OperatorLearnerEnrollment
  locale: SupportedLocale
  message: MessageResolver
}>) {
  return (
    <>
      <dl className="operator-people__details">
        <Detail label={message('operator.people.field.version').text}>{enrollment.version}</Detail>
        <Detail label={message('operator.learners.field.cohort').text}>
          {enrollment.cohort === null ? message('operator.people.unassigned').text : <SafeText>{enrollment.cohort.name ?? enrollment.cohort.key ?? enrollment.cohort.id}</SafeText>}
        </Detail>
        <Detail label={message('operator.learners.field.enrolled').text}>
          <time dateTime={enrollment.enrolled_at}>{formatDateTime(enrollment.enrolled_at, locale)}</time>
        </Detail>
        <Detail label={message('operator.learners.field.activated').text}>
          {enrollment.activated_at === null ? message('operator.people.not_recorded').text : <time dateTime={enrollment.activated_at}>{formatDateTime(enrollment.activated_at, locale)}</time>}
        </Detail>
        <Detail label={message('operator.learners.field.curriculum_pin').text}>
          {enrollment.curriculum_pin === null ? message('operator.people.not_recorded').text : <code>{enrollment.curriculum_pin.publication_id}</code>}
        </Detail>
      </dl>
      {enrollment.trainers === null ? null : (
        <div className="operator-people__relationships">
          <h5>{message('operator.learners.field.trainers').text}</h5>
          {enrollment.trainers.length === 0 ? <p>{message('operator.learners.enrollments.no_trainers').text}</p> : (
            <ul className="operator-people__compact-list">
              {enrollment.trainers.map((trainer) => (
                <li key={trainer.membership_id}>
                  <SafeText>{trainer.display_name ?? message('operator.people.name_unavailable').text}</SafeText>
                  <code>{trainer.membership_id}</code>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </>
  )
}

function InvitationsPanel({ api, createCommandId, locale, message }: Readonly<{
  api: OperatorLearnerApi
  createCommandId: () => string
  locale: SupportedLocale
  message: MessageResolver
}>) {
  const load = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listInvitations(undefined, cursor, signal),
    [api],
  )
  const resource = usePagedResource<OperatorLearnerInvitation, OperatorLearnerInvitationCollection>(load)
  const [issuance, setIssuance] = useState<OperatorLearnerInvitationIssuance | null>(null)
  const command = useCommandRunner(createCommandId, resource.reload)

  return (
    <LearnerPanel
      headingId="operator-learner-invitations-title"
      leadKey="operator.learners.invitations.lead"
      message={message}
      titleKey="operator.learners.invitations.title"
      wide
    >
      <PagedState message={message} resource={resource}>
        {(page) => (
          <>
            {hasAction(page.allowed_actions, 'learner_invitation.issue') ? (
              <IssueInvitationForm api={api} choices={page.choices} command={command} message={message} onIssuance={setIssuance} />
            ) : null}
            {issuance === null ? null : (
              <InvitationSecret issuance={issuance} message={message} onDismiss={() => setIssuance(null)} />
            )}
            {page.data.length === 0 ? <PanelEmpty message={message} /> : null}
            <ul className="operator-people__cards operator-people__cards--wide">
              {page.data.map((invitation) => (
                <li key={invitation.id}>
                  <ResourceHeading code={invitation.id} name={message('operator.learners.invitations.invitation').text} status={invitation.status} />
                  <dl className="operator-people__details">
                    <Detail label={message('operator.learners.field.role').text}>
                      {invitation.role === null ? message('operator.people.not_recorded').text : <SafeText>{invitation.role.name}</SafeText>}
                    </Detail>
                    <Detail label={message('operator.learners.field.cohort').text}>
                      {invitation.cohort === null ? message('operator.people.unassigned').text : <SafeText>{invitation.cohort.name}</SafeText>}
                    </Detail>
                    <Detail label={message('operator.people.field.expires').text}>
                      <time dateTime={invitation.expires_at}>{formatDateTime(invitation.expires_at, locale)}</time>
                    </Detail>
                    <Detail label={message('operator.people.field.version').text}>{invitation.version}</Detail>
                  </dl>
                  {hasAction(invitation.allowed_actions, 'learner_invitation.revoke') ? (
                    <ConfirmAction
                      busy={command.busy}
                      confirmText={message('operator.learners.confirm.revoke_invitation').text}
                      label={message('operator.people.action.revoke').text}
                      message={message}
                      onConfirm={() => command.run(
                        `learner-invitation.revoke:${invitation.id}:${String(invitation.version)}`,
                        (key) => api.revokeInvitation(invitation.id, { expected_version: invitation.version }, key),
                      )}
                    />
                  ) : null}
                </li>
              ))}
            </ul>
            <PageControls message={message} resource={resource} />
          </>
        )}
      </PagedState>
      <CommandFeedback message={message} onReload={resource.reload} state={command.state} />
    </LearnerPanel>
  )
}

function IssueInvitationForm({ api, choices, command, message, onIssuance }: Readonly<{
  api: OperatorLearnerApi
  choices: OperatorLearnerInvitationCollection['choices']
  command: CommandRunner
  message: MessageResolver
  onIssuance: (issuance: OperatorLearnerInvitationIssuance) => void
}>) {
  const [roleId, setRoleId] = useState(choices.roles[0]?.id ?? '')
  const [cohortId, setCohortId] = useState('')
  const [expiry, setExpiry] = useState<24 | 72 | 168>(24)
  const role = choices.roles.find((choice) => choice.id === roleId) ?? null
  const cohort = choices.cohorts.find((choice) => choice.id === cohortId) ?? null

  return (
    <form
      className="operator-people__form"
      onSubmit={(event) => {
        event.preventDefault()
        if (role === null) return
        const body = {
          cohort_id: cohort?.id ?? null,
          expected_cohort_version: cohort?.version ?? null,
          expected_role_version: role.version,
          expected_version: 0 as const,
          expires_in_hours: expiry,
          role_id: role.id,
        }
        void command.run(
          `learner-invitation.issue:${JSON.stringify(body)}`,
          async (key) => {
            const result = await api.issueInvitation(body, key)
            onIssuance(result)
          },
        )
      }}
    >
      <h5>{message('operator.learners.invitations.issue').text}</h5>
      <Field id="operator-learner-invitation-role" label={message('operator.learners.field.role').text}>
        <select id="operator-learner-invitation-role" onChange={(event) => setRoleId(event.currentTarget.value)} required value={roleId}>
          {choices.roles.map((roleChoice) => <option key={roleChoice.id} value={roleChoice.id}>{roleChoice.name}</option>)}
        </select>
      </Field>
      <CohortSelect cohorts={choices.cohorts} id="operator-learner-invitation-cohort" message={message} onChange={setCohortId} value={cohortId} />
      <Field id="operator-learner-invitation-expiry" label={message('operator.people.field.expiry').text}>
        <select id="operator-learner-invitation-expiry" onChange={(event) => setExpiry(Number(event.currentTarget.value) as 24 | 72 | 168)} value={expiry}>
          <option value={24}>24 hours</option>
          <option value={72}>72 hours</option>
          <option value={168}>168 hours</option>
        </select>
      </Field>
      <button disabled={command.busy || role === null} type="submit">{message('operator.people.action.issue').text}</button>
    </form>
  )
}

function TuitionQueuePanel({
  api,
  createCommandId,
  locale,
  message,
}: Readonly<{
  api: OperatorLearnerApi
  createCommandId: () => string
  locale: SupportedLocale
  message: MessageResolver
}>) {
  const [requests, setRequests] = useState<readonly OperatorTuitionRequest[]>([])
  const [loadError, setLoadError] = useState(false)
  const [busyId, setBusyId] = useState<string | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    void api
      .listTuitionRequests(controller.signal)
      .then((listing) => {
        setRequests(listing.requests)
        setLoadError(false)
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setLoadError(true)
        }
      })
    return () => controller.abort()
  }, [api])

  return (
    <article aria-labelledby="operator-tuition-title">
      <h4 id="operator-tuition-title">
        <LocalizedMessage resolution={message('operator.learners.tuition.title')} />
      </h4>
      <p>
        <LocalizedMessage resolution={message('operator.learners.tuition.lead')} />
      </p>
      {loadError ? (
        <p role="alert">
          <LocalizedMessage resolution={message('operator.panel.error')} />
        </p>
      ) : null}
      {requests.length === 0 && !loadError ? (
        <p>
          <LocalizedMessage resolution={message('operator.learners.tuition.empty')} />
        </p>
      ) : (
        <ul>
          {requests.map((request) => (
            <li key={request.id} lang={locale}>
              <strong>{request.display_name ?? request.membership_id}</strong>
              {' · '}
              {request.topic}
              {' · '}
              {request.preferred_window}
              {' · '}
              <LocalizedMessage
                resolution={message(
                  request.status === 'acknowledged'
                    ? 'tuition.status.acknowledged'
                    : 'tuition.status.submitted',
                )}
              />
              {request.status === 'submitted' ? (
                <button
                  disabled={busyId === request.id}
                  onClick={() => {
                    setBusyId(request.id)
                    void api
                      .acknowledgeTuitionRequest(request.id, createCommandId())
                      .then((updated) => {
                        setRequests((current) =>
                          current.map((item) =>
                            item.id === updated.id ? updated : item,
                          ),
                        )
                      })
                      .finally(() => setBusyId(null))
                  }}
                  type="button"
                >
                  <LocalizedMessage
                    resolution={message(
                      busyId === request.id
                        ? 'operator.learners.tuition.acknowledging'
                        : 'operator.learners.tuition.acknowledge',
                    )}
                  />
                </button>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </article>
  )
}

function InvitationSecret({ issuance, message, onDismiss }: Readonly<{
  issuance: OperatorLearnerInvitationIssuance
  message: MessageResolver
  onDismiss: () => void
}>) {
  const [copyState, setCopyState] = useState<'idle' | 'copied' | 'failed'>('idle')
  const code = issuance.activation_code

  if (code === null) {
    return (
      <section className="operator-invitation-code">
        <h5>{message('operator.people.invitation.replay_title').text}</h5>
        <p>{message('operator.people.invitation.replay_notice').text}</p>
        <button onClick={onDismiss} type="button">{message('operator.people.action.dismiss').text}</button>
      </section>
    )
  }

  return (
    <section className="operator-invitation-code">
      <h5>{message('operator.people.invitation.code_title').text}</h5>
      <p>{message('operator.people.invitation.code_notice').text}</p>
      <code className="operator-invitation-code__value" dir="ltr">{code}</code>
      <div className="operator-people__actions">
        <button
          onClick={() => {
            const clipboard = globalThis.navigator.clipboard

            if (clipboard === undefined) {
              setCopyState('failed')
              return
            }

            void clipboard.writeText(code)
              .then(() => setCopyState('copied'))
              .catch(() => setCopyState('failed'))
          }}
          type="button"
        >
          {message('operator.people.action.copy').text}
        </button>
        <button onClick={onDismiss} type="button">{message('operator.people.invitation.saved').text}</button>
      </div>
      {copyState === 'copied' ? <p role="status">{message('operator.people.invitation.copied').text}</p> : null}
      {copyState === 'failed' ? <p role="alert">{message('operator.people.invitation.copy_failed').text}</p> : null}
    </section>
  )
}

interface ResourceController<T> {
  readonly reload: () => void
  readonly state: ResourceState<T> | { readonly status: 'disabled' }
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
    if (!enabled) return
    const controller = new AbortController()
    void load(controller.signal)
      .then((value) => {
        if (!controller.signal.aborted) setResult({ attempt, load, state: { status: 'ready', value } })
      })
      .catch(() => {
        if (!controller.signal.aborted) setResult({ attempt, load, state: { status: 'error' } })
      })
    return () => controller.abort()
  }, [attempt, enabled, load])

  return {
    reload: () => setAttempt((current) => current + 1),
    state: !enabled
      ? { status: 'disabled' }
      : result.attempt === attempt && result.load === load
        ? result.state
        : { status: 'loading' },
  }
}

function usePagedResource<T, P extends Page<T>>(
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

  const execute = useCallback((cursor: string | undefined, append: boolean) => {
    controller.current?.abort()
    const request = new AbortController()
    controller.current = request
    if (append) {
      setLoadingMore(true)
      setMoreError(false)
    }
    void load(cursor, request.signal)
      .then((page) => {
        if (request.signal.aborted) return
        setResult((current) => {
          if (!append || current.attempt !== attempt || current.load !== load || current.state.status !== 'ready') {
            return { attempt, load, state: { status: 'ready', value: page } }
          }
          return {
            attempt,
            load,
            state: {
              status: 'ready',
              value: { ...page, data: [...current.state.value.data, ...page.data] } as P,
            },
          }
        })
        setLoadingMore(false)
      })
      .catch(() => {
        if (request.signal.aborted) return
        if (append) {
          setMoreError(true)
          setLoadingMore(false)
        } else {
          setResult({ attempt, load, state: { status: 'error' } })
        }
      })
  }, [attempt, load])

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
        if (!request.signal.aborted) setResult({ attempt, load, state: { status: 'ready', value: page } })
      })
      .catch(() => {
        if (!request.signal.aborted) setResult({ attempt, load, state: { status: 'error' } })
      })
    return () => request.abort()
  }, [attempt, enabled, load])

  const state: PagedResource<T, P>['state'] = !enabled
    ? { status: 'disabled' }
    : result.attempt === attempt && result.load === load
      ? result.state
      : { status: 'loading' }

  return {
    loadMore: () => {
      if (state.status === 'ready' && state.value.next_cursor !== null) execute(state.value.next_cursor, true)
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

function useCommandRunner(createCommandId: () => string, onSuccess: () => void): CommandRunner {
  const [state, setState] = useState<CommandState>({ status: 'idle' })
  const keys = useRef(new Map<string, string>())
  return {
    busy: state.status === 'busy',
    run: async (signature, operation) => {
      const key = keys.current.get(signature) ?? createCommandId()
      keys.current.set(signature, key)
      setState({ status: 'busy' })
      try {
        await operation(key)
        keys.current.delete(signature)
        setState({ status: 'success' })
        onSuccess()
        return true
      } catch (error) {
        const kind = error instanceof OperatorLearnerApiError ? error.kind : 'unavailable'
        if (kind === 'idempotency_conflict') keys.current.delete(signature)
        setState({ kind, status: 'error' })
        return false
      }
    },
    state,
  }
}

function LearnerPanel({ children, headingId, leadKey, message, titleKey, wide = false }: Readonly<{
  children: ReactNode
  headingId: string
  leadKey: MessageKey
  message: MessageResolver
  titleKey: MessageKey
  wide?: boolean
}>) {
  return (
    <article aria-labelledby={headingId} className={`operator-panel operator-people__panel${wide ? ' operator-panel--wide' : ''}`}>
      <h4 id={headingId}><LocalizedMessage resolution={message(titleKey)} /></h4>
      <p><LocalizedMessage resolution={message(leadKey)} /></p>
      {children}
    </article>
  )
}

function PagedState<T, P extends Page<T>>({ children, message, resource }: Readonly<{
  children: (page: P) => ReactNode
  message: MessageResolver
  resource: PagedResource<T, P>
}>) {
  if (resource.state.status === 'disabled') return null
  if (resource.state.status === 'loading') return <PanelLoading message={message} />
  if (resource.state.status === 'error') return <PanelFailure message={message} onRetry={resource.reload} />
  return children(resource.state.value)
}

function PanelLoading({ message }: Readonly<{ message: MessageResolver }>) {
  return <p className="operator-panel__loading" role="status">{message('operator.panel.loading').text}</p>
}

function PanelFailure({ message, onRetry }: Readonly<{ message: MessageResolver; onRetry: () => void }>) {
  return (
    <div className="operator-panel__failure" role="alert">
      <p>{message('operator.panel.error').text}</p>
      <button onClick={onRetry} type="button">{message('operator.retry').text}</button>
    </div>
  )
}

function PanelEmpty({ message }: Readonly<{ message: MessageResolver }>) {
  return <p>{message('operator.people.empty').text}</p>
}

function PageControls<T, P extends Page<T>>({ message, resource }: Readonly<{
  message: MessageResolver
  resource: PagedResource<T, P>
}>) {
  if (resource.state.status !== 'ready') return null
  return (
    <div className="operator-people__pagination">
      {resource.moreError ? <p role="alert">{message('operator.people.more_error').text}</p> : null}
      {resource.state.value.next_cursor === null ? null : (
        <button disabled={resource.loadingMore} onClick={resource.loadMore} type="button">
          {message(resource.loadingMore ? 'operator.loading_more' : 'operator.load_more').text}
        </button>
      )}
    </div>
  )
}

function Field({ children, id, label }: Readonly<{ children: ReactNode; id: string; label: string }>) {
  return <div className="operator-people__field"><label htmlFor={id}>{label}</label>{children}</div>
}

function CohortSelect({ cohorts, id, message, onChange, value }: Readonly<{
  cohorts: readonly OperatorLearnerChoice[]
  id: string
  message: MessageResolver
  onChange: (value: string) => void
  value: string
}>) {
  return (
    <Field id={id} label={message('operator.learners.field.cohort').text}>
      <select id={id} onChange={(event) => onChange(event.currentTarget.value)} value={value}>
        <option value="">{message('operator.people.unassigned').text}</option>
        {cohorts.map((cohort) => <option key={cohort.id} value={cohort.id}>{cohort.name}</option>)}
      </select>
    </Field>
  )
}

function ResourceHeading({ code, name, status }: Readonly<{ code: string; name: string; status: string }>) {
  return (
    <div className="operator-people__resource-heading">
      <div><strong><SafeText>{name}</SafeText></strong><code>{code}</code></div>
      <span>{status}</span>
    </div>
  )
}

function Detail({ children, label }: Readonly<{ children: ReactNode; label: string }>) {
  return <div><dt>{label}</dt><dd>{children}</dd></div>
}

function SafeText({ children }: Readonly<{ children: string }>) {
  return <bdi lang="">{children}</bdi>
}

function ConfirmAction({ busy, confirmText, label, message, onConfirm }: Readonly<{
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
      <button aria-expanded={confirming} disabled={busy} onClick={() => setConfirming(true)} ref={trigger} type="button">{label}</button>
      {confirming ? (
        <div className="operator-people__confirmation-prompt" role="group">
          <p>{confirmText}</p>
          <div className="operator-people__actions">
            <button disabled={busy} onClick={finish} type="button">{message('operator.people.action.cancel').text}</button>
            <button disabled={busy} onClick={() => { void onConfirm().finally(finish) }} type="button">{message('operator.people.action.confirm').text}</button>
          </div>
        </div>
      ) : null}
    </div>
  )
}

function CommandFeedback({ message, onReload, state }: Readonly<{
  message: MessageResolver
  onReload: () => void
  state: CommandState
}>) {
  if (state.status === 'idle' || state.status === 'busy') return null
  if (state.status === 'success') {
    return <p className="operator-people__feedback" role="status">{message('operator.learners.command.success').text}</p>
  }
  const keys: Readonly<Record<OperatorLearnerErrorKind, MessageKey>> = {
    active_dependency: 'operator.learners.command.active_dependency',
    forbidden: 'operator.people.command.forbidden',
    idempotency_conflict: 'operator.people.command.idempotency_conflict',
    stale_state: 'operator.people.command.conflict',
    unavailable: 'operator.people.command.unavailable',
    unsafe_role: 'operator.learners.command.unsafe_role',
    validation: 'operator.people.command.validation',
  }
  return (
    <div className="operator-people__feedback" role="alert">
      <p>{message(keys[state.kind]).text}</p>
      {state.kind === 'stale_state' ? <button onClick={onReload} type="button">{message('operator.people.command.reload').text}</button> : null}
    </div>
  )
}

function hasAction(actions: readonly OperatorLearnerAction[], action: OperatorLearnerAction): boolean {
  return actions.includes(action)
}

function yesNo(value: boolean, message: MessageResolver): string {
  return message(value ? 'operator.learners.yes' : 'operator.learners.no').text
}

function emptyToNull(value: string): string | null {
  const normalized = value.trim()
  return normalized === '' ? null : normalized
}

function formatDateTime(value: string, locale: SupportedLocale): string {
  const date = new Date(value)
  if (Number.isNaN(date.valueOf())) return value
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}
