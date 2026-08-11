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
} from 'react'
import type { WebSessionAuthentication } from '../auth/AuthenticationAdapter'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import {
  resolveInterfaceMessage,
  type InterfaceMessageResolution,
} from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import type {
  OperatorAuditEvent,
  OperatorConsoleApi,
  OperatorContentDraft,
  OperatorNavigationKey,
  OperatorPilotAnalyticsSummary,
  OperatorPublicationStatus,
  OperatorSearchResult,
  OperatorSearchResultKind,
  OperatorSupportDiagnosticItem,
} from './OperatorConsoleApi'
import {
  OperatorOrganizationAdministration,
  OperatorOrganizationSettingsPanel,
} from './OperatorOrganizationAdministration'
import {
  defaultOperatorOrganizationApi,
  type OperatorOrganizationApi,
  type OperatorOrganizationPanelKey,
} from './OperatorOrganizationApi'
import { OperatorLearnerAdministration } from './OperatorLearnerAdministration'
import {
  defaultOperatorLearnerApi,
  type OperatorLearnerApi,
  type OperatorLearnerPanelKey,
} from './OperatorLearnerApi'
import { OperatorContentStudio } from './OperatorContentStudio'
import {
  defaultOperatorContentStudioApi,
  type OperatorContentStudioApi,
} from './OperatorContentStudioApi'
import { OperatorMediaLibrary } from './OperatorMediaLibrary'
import {
  defaultOperatorMediaLibraryApi,
  type OperatorMediaLibraryApi,
} from './OperatorMediaLibraryApi'

export interface OperatorConsoleProps {
  api: OperatorConsoleApi
  contentApi?: OperatorContentStudioApi
  createCommandId?: () => string
  currentMembershipId?: string
  locale: SupportedLocale
  localizationPolicy: ResolutionPolicy
  learnerApi?: OperatorLearnerApi
  mediaApi?: OperatorMediaLibraryApi
  mfaAuthentication?: WebSessionAuthentication
  onCredentialsRevoked?: () => void
  organizationApi?: OperatorOrganizationApi
}

type MessageResolver = (key: MessageKey) => InterfaceMessageResolution

type ResourceState<T> =
  | { readonly status: 'loading' }
  | { readonly status: 'error' }
  | { readonly status: 'ready'; readonly value: T }

const navigationMetadata: Readonly<
  Record<
    OperatorNavigationKey,
    Readonly<{ anchor: string; label: MessageKey }>
  >
> = {
  overview: {
    anchor: 'operator-workspace-overview',
    label: 'operator.navigation.overview',
  },
  learning: {
    anchor: 'operator-learning',
    label: 'operator.navigation.learning',
  },
  operations: {
    anchor: 'operator-operations',
    label: 'operator.navigation.operations',
  },
  safety: {
    anchor: 'operator-safety',
    label: 'operator.navigation.safety',
  },
  people: {
    anchor: 'operator-people',
    label: 'operator.navigation.people',
  },
  learners: {
    anchor: 'operator-learners',
    label: 'operator.navigation.learners',
  },
}

const searchKindMessages: Readonly<
  Record<OperatorSearchResultKind, MessageKey>
> = {
  audit_event: 'operator.search.kind.audit_event',
  curriculum_publication:
    'operator.search.kind.curriculum_publication',
  curriculum_version: 'operator.search.kind.curriculum_version',
  support_diagnostic: 'operator.search.kind.support_diagnostic',
}

const organizationPanelKeys: ReadonlySet<string> = new Set([
  'organization_settings',
  'branches',
  'cohorts',
  'staff_memberships',
  'institution_invites',
  'roles',
  'trainer_profiles',
  'authority_appointments',
  'membership_sessions',
])

const learnerPanelKeys: ReadonlySet<string> = new Set([
  'learner_directory',
  'learner_enrollments',
  'learner_invitations',
])

function formatGroupedCounts(
  counts: Readonly<Record<string, number>>,
): string {
  const entries = Object.entries(counts)

  if (entries.length === 0) {
    return 'none'
  }

  return entries.map(([key, value]) => `${key}=${String(value)}`).join(', ')
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

function useOperatorResource<T>(
  load: (signal: AbortSignal) => Promise<T>,
): Readonly<{
  retry: () => void
  state: ResourceState<T>
}> {
  const [attempt, setAttempt] = useState(0)
  const [state, setState] = useState<ResourceState<T>>({
    status: 'loading',
  })

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    void load(controller.signal)
      .then((value) => {
        if (active) {
          setState({ status: 'ready', value })
        }
      })
      .catch(() => {
        if (active && !controller.signal.aborted) {
          setState({ status: 'error' })
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [attempt, load])

  return {
    retry: () => {
      setState({ status: 'loading' })
      setAttempt((current) => current + 1)
    },
    state,
  }
}

export function OperatorConsole({
  api,
  contentApi = defaultOperatorContentStudioApi,
  createCommandId = createRandomClientId,
  currentMembershipId = '',
  locale,
  learnerApi = defaultOperatorLearnerApi,
  mediaApi = defaultOperatorMediaLibraryApi,
  localizationPolicy,
  organizationApi = defaultOperatorOrganizationApi,
}: OperatorConsoleProps) {
  const message: MessageResolver = (key) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const loadOverview = useCallback(
    (signal: AbortSignal) => api.getOverview(signal),
    [api],
  )
  const overview = useOperatorResource(loadOverview)

  return (
    <section
      aria-labelledby="operator-console-title"
      className="operator-console"
      data-operator-console="permission-aware-workspace"
    >
      <div className="operator-console__heading">
        <div>
          <p className="operator-console__kicker">
            <LocalizedMessage
              resolution={message('operator.console.kicker')}
            />
          </p>
          <h2 className="operator-console__title" id="operator-console-title">
            <LocalizedMessage resolution={message('operator.console.title')} />
          </h2>
          <p className="operator-console__lead">
            <LocalizedMessage resolution={message('operator.console.lead')} />
          </p>
        </div>
        <p className="operator-console__notice">
          <LocalizedMessage
            resolution={message('operator.console.notice')}
          />
        </p>
      </div>

      {overview.state.status === 'loading' ? (
        <div className="operator-console__shell-state" role="status">
          <LocalizedMessage
            resolution={message('operator.console.loading')}
          />
        </div>
      ) : null}

      {overview.state.status === 'error' ? (
        <div className="operator-console__shell-state" role="alert">
          <p>
            <LocalizedMessage
              resolution={message('operator.console.error')}
            />
          </p>
          <button onClick={overview.retry} type="button">
            <LocalizedMessage resolution={message('operator.retry')} />
          </button>
        </div>
      ) : null}

      {overview.state.status === 'ready' ? (
        <div className="operator-workspace">
          <div
            className="operator-workspace__organization"
            id="operator-workspace-overview"
          >
            <div>
              <span className="operator-workspace__organization-label">
                <LocalizedMessage
                  resolution={message('operator.organization.label')}
                />
              </span>
              <bdi className="operator-workspace__organization-name" lang="">
                {overview.state.value.organization.name}
              </bdi>
            </div>
            <div>
              <span className="operator-workspace__organization-label">
                <LocalizedMessage
                  resolution={message('operator.organization.timezone')}
                />
              </span>
              <bdi>{overview.state.value.organization.timezone}</bdi>
            </div>
          </div>

          {overview.state.value.search_available ? (
            <OperatorSearch api={api} locale={locale} message={message} />
          ) : null}

          <nav
            aria-label={message('operator.navigation.label').text}
            className="operator-workspace__navigation"
          >
            <ul>
              {overview.state.value.navigation.map((item) => (
                <li key={item}>
                  <a href={`#${navigationMetadata[item].anchor}`}>
                    <LocalizedMessage
                      resolution={message(navigationMetadata[item].label)}
                    />
                  </a>
                </li>
              ))}
            </ul>
          </nav>

          <div className="operator-workspace__tasks">
            {overview.state.value.panels.includes('pilot_analytics') ||
            overview.state.value.panels.includes('organization_settings') ? (
              <section
                aria-labelledby="operator-overview-title"
                className="operator-task-group"
              >
                <div className="operator-task-group__heading">
                  <p>
                    <LocalizedMessage
                      resolution={message('operator.navigation.overview')}
                    />
                  </p>
                  <h3 id="operator-overview-title">
                    <LocalizedMessage
                      resolution={message('operator.overview.title')}
                    />
                  </h3>
                </div>
                <div className="operator-task-group__grid">
                  {overview.state.value.panels.includes(
                    'organization_settings',
                  ) ? (
                    <OperatorOrganizationSettingsPanel
                      api={organizationApi}
                      locale={locale}
                      localizationPolicy={localizationPolicy}
                    />
                  ) : null}
                  {overview.state.value.panels.includes('pilot_analytics') ? (
                    <PilotAnalyticsPanel api={api} message={message} />
                  ) : null}
                </div>
              </section>
            ) : null}

            {overview.state.value.navigation.includes('people') ? (
              <OperatorOrganizationAdministration
                api={organizationApi}
                createCommandId={createCommandId}
                currentMembershipId={currentMembershipId}
                locale={locale}
                localizationPolicy={localizationPolicy}
                panels={overview.state.value.panels.filter(
                  (panel): panel is OperatorOrganizationPanelKey =>
                    organizationPanelKeys.has(panel),
                )}
              />
            ) : null}

            {overview.state.value.navigation.includes('learners') ? (
              <OperatorLearnerAdministration
                api={learnerApi}
                createCommandId={createCommandId}
                locale={locale}
                localizationPolicy={localizationPolicy}
                panels={overview.state.value.panels.filter(
                  (panel): panel is OperatorLearnerPanelKey =>
                    learnerPanelKeys.has(panel),
                )}
              />
            ) : null}

            {overview.state.value.navigation.includes('learning') ? (
              <section
                aria-labelledby="operator-learning-title"
                className="operator-task-group"
                id="operator-learning"
              >
                <div className="operator-task-group__heading">
                  <p>
                    <LocalizedMessage
                      resolution={message('operator.navigation.learning')}
                    />
                  </p>
                  <h3 id="operator-learning-title">
                    <LocalizedMessage
                      resolution={message('operator.learning.title')}
                    />
                  </h3>
                </div>
                <div className="operator-task-group__grid">
                  {overview.state.value.panels.includes('content_studio') ? (
                    <OperatorContentStudio
                      api={contentApi}
                      createCommandId={createCommandId}
                      locale={locale}
                      localizationPolicy={localizationPolicy}
                    />
                  ) : null}
                  {overview.state.value.panels.includes('media_library') ? (
                    <OperatorMediaLibrary
                      api={mediaApi}
                      createCommandId={createCommandId}
                      locale={locale}
                      localizationPolicy={localizationPolicy}
                    />
                  ) : null}
                  {overview.state.value.panels.includes('review_queue') ? (
                    <ReviewQueuePanel api={api} message={message} />
                  ) : null}
                  {overview.state.value.panels.includes('publish_status') ? (
                    <PublicationStatusPanel api={api} message={message} />
                  ) : null}
                </div>
              </section>
            ) : null}

            {overview.state.value.navigation.includes('operations') ? (
              <section
                aria-labelledby="operator-operations-title"
                className="operator-task-group"
                id="operator-operations"
              >
                <div className="operator-task-group__heading">
                  <p>
                    <LocalizedMessage
                      resolution={message('operator.navigation.operations')}
                    />
                  </p>
                  <h3 id="operator-operations-title">
                    <LocalizedMessage
                      resolution={message('operator.operations.title')}
                    />
                  </h3>
                </div>
                {overview.state.value.panels.includes(
                  'support_diagnostics',
                ) ? (
                  <SupportDiagnosticsPanel api={api} message={message} />
                ) : null}
              </section>
            ) : null}

            {overview.state.value.navigation.includes('safety') ? (
              <section
                aria-labelledby="operator-safety-title"
                className="operator-task-group"
                id="operator-safety"
              >
                <div className="operator-task-group__heading">
                  <p>
                    <LocalizedMessage
                      resolution={message('operator.navigation.safety')}
                    />
                  </p>
                  <h3 id="operator-safety-title">
                    <LocalizedMessage
                      resolution={message('operator.safety.title')}
                    />
                  </h3>
                </div>
                {overview.state.value.panels.includes('audit') ? (
                  <AuditPanel api={api} locale={locale} message={message} />
                ) : null}
              </section>
            ) : null}
          </div>

        </div>
      ) : null}
    </section>
  )
}

interface PanelProps {
  api: OperatorConsoleApi
  message: MessageResolver
}

function PanelFailure({
  message,
  onRetry,
}: Readonly<{
  message: MessageResolver
  onRetry: () => void
}>) {
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

function PanelLoading({ message }: Readonly<{ message: MessageResolver }>) {
  return (
    <p className="operator-panel__loading" role="status">
      <LocalizedMessage resolution={message('operator.panel.loading')} />
    </p>
  )
}

function PilotAnalyticsPanel({ api, message }: PanelProps) {
  const load = useCallback(
    (signal: AbortSignal) => api.getPilotAnalytics(signal),
    [api],
  )
  const resource = useOperatorResource(load)

  return (
    <article
      aria-labelledby="operator-pilot-analytics-title"
      className="operator-panel operator-panel--wide"
    >
      <h4 id="operator-pilot-analytics-title">
        <LocalizedMessage
          resolution={message('operator.pilot_analytics.title')}
        />
      </h4>
      <p>
        <LocalizedMessage
          resolution={message('operator.pilot_analytics.lead')}
        />
      </p>
      {resource.state.status === 'loading' ? (
        <PanelLoading message={message} />
      ) : null}
      {resource.state.status === 'error' ? (
        <PanelFailure message={message} onRetry={resource.retry} />
      ) : null}
      {resource.state.status === 'ready' ? (
        <PilotAnalytics
          analytics={resource.state.value}
          message={message}
        />
      ) : null}
    </article>
  )
}

function PilotAnalytics({
  analytics,
  message,
}: Readonly<{
  analytics: OperatorPilotAnalyticsSummary
  message: MessageResolver
}>) {
  return (
    <div className="operator-metrics">
      <div className="operator-metric">
        <span>
          <LocalizedMessage
            resolution={message('operator.pilot_analytics.learners')}
          />
        </span>
        <strong>{analytics.usage.learner_membership_count}</strong>
      </div>
      <div className="operator-metric">
        <span>
          <LocalizedMessage
            resolution={message('operator.pilot_analytics.sessions')}
          />
        </span>
        <strong>{analytics.usage.conversation_session_count}</strong>
      </div>
      <div className="operator-metric">
        <span>
          <LocalizedMessage
            resolution={message('operator.pilot_analytics.attempts')}
          />
        </span>
        <strong>{analytics.usage.formative_attempt_count}</strong>
      </div>
      <div className="operator-metric">
        <span>
          <LocalizedMessage
            resolution={message('operator.pilot_analytics.mastery')}
          />
        </span>
        <strong>
          {analytics.demonstrated_learning.mastery_state_count}
        </strong>
      </div>
      <p className="operator-metrics__detail">
        <LocalizedMessage
          resolution={message('operator.pilot_analytics.learning')}
        />
        {': '}
        {formatGroupedCounts(
          analytics.demonstrated_learning.mastery_by_knowledge_state,
        )}
      </p>
    </div>
  )
}

function ReviewQueuePanel({ api, message }: PanelProps) {
  const load = useCallback(
    (signal: AbortSignal) => api.listDrafts(signal),
    [api],
  )
  const resource = useOperatorResource(load)

  return (
    <article
      aria-labelledby="operator-review-queue-title"
      className="operator-panel"
    >
      <h4 id="operator-review-queue-title">
        <LocalizedMessage resolution={message('operator.review_queue.title')} />
      </h4>
      {resource.state.status === 'loading' ? (
        <PanelLoading message={message} />
      ) : null}
      {resource.state.status === 'error' ? (
        <PanelFailure message={message} onRetry={resource.retry} />
      ) : null}
      {resource.state.status === 'ready' ? (
        <DraftList drafts={resource.state.value} message={message} />
      ) : null}
    </article>
  )
}

function DraftList({
  drafts,
  message,
}: Readonly<{
  drafts: readonly OperatorContentDraft[]
  message: MessageResolver
}>) {
  if (drafts.length === 0) {
    return (
      <p>
        <LocalizedMessage resolution={message('operator.review_queue.empty')} />
      </p>
    )
  }

  return (
    <ul className="operator-panel__list">
      {drafts.map((draft) => (
        <li key={draft.curriculum_version_id}>
          <bdi className="operator-panel__primary" lang="">
            {draft.program_internal_name}
          </bdi>
          <code>
            {draft.program_key} / {draft.version_key}
          </code>
          <span>{draft.publication_status}</span>
          <span>
            EN {draft.latest_english_decision ?? 'pending'} · KN{' '}
            {draft.latest_kannada_decision ?? 'pending'}
          </span>
        </li>
      ))}
    </ul>
  )
}

function PublicationStatusPanel({ api, message }: PanelProps) {
  const load = useCallback(
    (signal: AbortSignal) => api.listPublications(signal),
    [api],
  )
  const resource = useOperatorResource(load)

  return (
    <article
      aria-labelledby="operator-publish-status-title"
      className="operator-panel"
    >
      <h4 id="operator-publish-status-title">
        <LocalizedMessage
          resolution={message('operator.publish_status.title')}
        />
      </h4>
      {resource.state.status === 'loading' ? (
        <PanelLoading message={message} />
      ) : null}
      {resource.state.status === 'error' ? (
        <PanelFailure message={message} onRetry={resource.retry} />
      ) : null}
      {resource.state.status === 'ready' ? (
        <PublicationList
          message={message}
          publications={resource.state.value}
        />
      ) : null}
    </article>
  )
}

function PublicationList({
  message,
  publications,
}: Readonly<{
  message: MessageResolver
  publications: readonly OperatorPublicationStatus[]
}>) {
  if (publications.length === 0) {
    return (
      <p>
        <LocalizedMessage
          resolution={message('operator.publish_status.empty')}
        />
      </p>
    )
  }

  return (
    <ul className="operator-panel__list">
      {publications.map((publication) => (
        <li key={publication.publication_id}>
          <code>
            {publication.program_key} / {publication.version_key}
          </code>
          <span>
            #{publication.publication_number}
            {publication.is_head ? ' · head' : ''}
          </span>
        </li>
      ))}
    </ul>
  )
}

function SupportDiagnosticsPanel({ api, message }: PanelProps) {
  const load = useCallback(
    (signal: AbortSignal) => api.listSupportDiagnostics(signal),
    [api],
  )
  const resource = useOperatorResource(load)

  return (
    <article
      aria-labelledby="operator-support-diagnostics-title"
      className="operator-panel operator-panel--wide"
    >
      <h4 id="operator-support-diagnostics-title">
        <LocalizedMessage
          resolution={message('operator.support_diagnostics.title')}
        />
      </h4>
      <p>
        <LocalizedMessage
          resolution={message('operator.support_diagnostics.lead')}
        />
      </p>
      {resource.state.status === 'loading' ? (
        <PanelLoading message={message} />
      ) : null}
      {resource.state.status === 'error' ? (
        <PanelFailure message={message} onRetry={resource.retry} />
      ) : null}
      {resource.state.status === 'ready' ? (
        <DiagnosticList
          diagnostics={resource.state.value.items}
          message={message}
        />
      ) : null}
    </article>
  )
}

function DiagnosticList({
  diagnostics,
  message,
}: Readonly<{
  diagnostics: readonly OperatorSupportDiagnosticItem[]
  message: MessageResolver
}>) {
  if (diagnostics.length === 0) {
    return (
      <p>
        <LocalizedMessage
          resolution={message('operator.support_diagnostics.empty')}
        />
      </p>
    )
  }

  return (
    <ul className="operator-panel__list operator-panel__list--diagnostics">
      {diagnostics.map((item) => (
        <li key={`${item.subject_type}:${item.subject_id}`}>
          <strong>{item.kind}</strong>
          <code>{item.subject_id}</code>
          <span>{item.status}</span>
        </li>
      ))}
    </ul>
  )
}

function AuditPanel({
  api,
  locale,
  message,
}: PanelProps & Readonly<{ locale: SupportedLocale }>) {
  const [state, setState] = useState<ResourceState<
    readonly OperatorAuditEvent[]
  >>({ status: 'loading' })
  const [nextCursor, setNextCursor] = useState<string | null>(null)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [loadingMore, setLoadingMore] = useState(false)
  const [loadMoreFailed, setLoadMoreFailed] = useState(false)
  const controller = useRef<AbortController | null>(null)

  const load = useCallback(
    (cursor: string | undefined, append: boolean) => {
      controller.current?.abort()
      const request = new AbortController()
      controller.current = request

      void api
        .listAuditEvents(cursor, request.signal)
        .then((page) => {
          if (request.signal.aborted) {
            return
          }

          setState((current) => ({
            status: 'ready',
            value:
              append && current.status === 'ready'
                ? [...current.value, ...page.items]
                : page.items,
          }))
          setNextCursor(page.next_cursor)
          setLoadingMore(false)
        })
        .catch(() => {
          if (request.signal.aborted) {
            return
          }

          if (append) {
            setLoadMoreFailed(true)
            setLoadingMore(false)
          } else {
            setState({ status: 'error' })
          }
        })
    },
    [api],
  )

  useEffect(() => {
    load(undefined, false)

    return () => {
      controller.current?.abort()
    }
  }, [load])

  const events = state.status === 'ready' ? state.value : []
  const selected = events.find((event) => event.id === selectedId) ?? null

  return (
    <article
      aria-labelledby="operator-audit-title"
      className="operator-panel operator-panel--wide"
    >
      <h4 id="operator-audit-title">
        <LocalizedMessage resolution={message('operator.audit.title')} />
      </h4>
      {state.status === 'loading' ? <PanelLoading message={message} /> : null}
      {state.status === 'error' ? (
        <PanelFailure
          message={message}
          onRetry={() => {
            setState({ status: 'loading' })
            load(undefined, false)
          }}
        />
      ) : null}
      {state.status === 'ready' && events.length === 0 ? (
        <p>
          <LocalizedMessage resolution={message('operator.audit.empty')} />
        </p>
      ) : null}
      {state.status === 'ready' && events.length > 0 ? (
        <div className="operator-audit">
          <div>
            <ul
              aria-label={message('operator.audit.list_label').text}
              className="operator-audit__list"
            >
              {events.map((event) => (
                <li key={event.id}>
                  <button
                    aria-pressed={selected?.id === event.id}
                    onClick={() => {
                      setSelectedId(event.id)
                    }}
                    type="button"
                  >
                    <strong>{event.action}</strong>
                    <span>
                      {event.subject_type} ·{' '}
                      <time dateTime={event.occurred_at}>
                        {formatDateTime(event.occurred_at, locale)}
                      </time>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
            {loadMoreFailed ? (
              <p className="operator-panel__inline-error" role="alert">
                <LocalizedMessage
                  resolution={message('operator.audit.more_error')}
                />
              </p>
            ) : null}
            {nextCursor === null ? null : (
              <button
                className="operator-panel__more"
                disabled={loadingMore}
                onClick={() => {
                  setLoadingMore(true)
                  setLoadMoreFailed(false)
                  load(nextCursor, true)
                }}
                type="button"
              >
                <LocalizedMessage
                  resolution={message(
                    loadingMore
                      ? 'operator.loading_more'
                      : 'operator.load_more',
                  )}
                />
              </button>
            )}
          </div>
          <AuditDetail event={selected} locale={locale} message={message} />
        </div>
      ) : null}
    </article>
  )
}

function AuditDetail({
  event,
  locale,
  message,
}: Readonly<{
  event: OperatorAuditEvent | null
  locale: SupportedLocale
  message: MessageResolver
}>) {
  return (
    <aside
      aria-live="polite"
      className="operator-audit__detail"
      data-audit-detail={event === null ? 'empty' : event.id}
    >
      <h5>
        <LocalizedMessage
          resolution={message('operator.audit.detail_title')}
        />
      </h5>
      {event === null ? (
        <p>
          <LocalizedMessage
            resolution={message('operator.audit.select_hint')}
          />
        </p>
      ) : (
        <dl>
          <div>
            <dt>
              <LocalizedMessage
                resolution={message('operator.audit.field.action')}
              />
            </dt>
            <dd>{event.action}</dd>
          </div>
          <div>
            <dt>
              <LocalizedMessage
                resolution={message('operator.audit.field.subject')}
              />
            </dt>
            <dd>
              {event.subject_type}:<code>{event.subject_id}</code>
            </dd>
          </div>
          <div>
            <dt>
              <LocalizedMessage
                resolution={message('operator.audit.field.actor')}
              />
            </dt>
            <dd>
              <code>
                {event.actor_membership_id ??
                  message('operator.audit.not_recorded').text}
              </code>
            </dd>
          </div>
          <div>
            <dt>
              <LocalizedMessage
                resolution={message('operator.audit.field.correlation')}
              />
            </dt>
            <dd>
              <code>
                {event.correlation_id ??
                  message('operator.audit.not_recorded').text}
              </code>
            </dd>
          </div>
          <div>
            <dt>
              <LocalizedMessage
                resolution={message('operator.audit.field.occurred_at')}
              />
            </dt>
            <dd>
              <time dateTime={event.occurred_at}>
                {formatDateTime(event.occurred_at, locale)}
              </time>
            </dd>
          </div>
        </dl>
      )}
    </aside>
  )
}

function OperatorSearch({
  api,
  locale,
  message,
}: PanelProps & Readonly<{ locale: SupportedLocale }>) {
  const [input, setInput] = useState('')
  const [activeQuery, setActiveQuery] = useState('')
  const [state, setState] = useState<ResourceState<
    readonly OperatorSearchResult[]
  > | null>(null)
  const [nextCursor, setNextCursor] = useState<string | null>(null)
  const [loadingMore, setLoadingMore] = useState(false)
  const [loadMoreFailed, setLoadMoreFailed] = useState(false)
  const controller = useRef<AbortController | null>(null)
  const debounceTimer = useRef<
    ReturnType<typeof globalThis.setTimeout> | null
  >(null)

  const executeSearch = useCallback(
    (query: string, cursor: string | undefined, append: boolean) => {
      const normalized = query.trim()

      if (normalized.length < 2) {
        controller.current?.abort()
        setActiveQuery('')
        setState(null)
        setNextCursor(null)
        setLoadingMore(false)
        setLoadMoreFailed(false)

        return
      }

      controller.current?.abort()
      const request = new AbortController()
      controller.current = request
      setActiveQuery(normalized)

      if (append) {
        setLoadingMore(true)
        setLoadMoreFailed(false)
      } else {
        setState({ status: 'loading' })
        setNextCursor(null)
        setLoadingMore(false)
        setLoadMoreFailed(false)
      }

      void api
        .search(normalized, cursor, request.signal)
        .then((page) => {
          if (request.signal.aborted) {
            return
          }

          setState((current) => ({
            status: 'ready',
            value:
              append && current?.status === 'ready'
                ? [...current.value, ...page.items]
                : page.items,
          }))
          setNextCursor(page.next_cursor)
          setLoadingMore(false)
        })
        .catch(() => {
          if (request.signal.aborted) {
            return
          }

          if (append) {
            setLoadMoreFailed(true)
            setLoadingMore(false)
          } else {
            setState({ status: 'error' })
          }
        })
    },
    [api],
  )

  useEffect(() => {
    const query = input.trim()

    if (query.length < 2) {
      return
    }

    debounceTimer.current = globalThis.setTimeout(() => {
      executeSearch(query, undefined, false)
      debounceTimer.current = null
    }, 300)

    return () => {
      if (debounceTimer.current !== null) {
        globalThis.clearTimeout(debounceTimer.current)
        debounceTimer.current = null
      }
    }
  }, [executeSearch, input])

  useEffect(
    () => () => {
      controller.current?.abort()
    },
    [],
  )

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (debounceTimer.current !== null) {
      globalThis.clearTimeout(debounceTimer.current)
      debounceTimer.current = null
    }

    executeSearch(input, undefined, false)
  }

  return (
    <section
      aria-labelledby="operator-search-title"
      className="operator-search"
    >
      <div className="operator-search__heading">
        <div>
          <p>
            <LocalizedMessage
              resolution={message('operator.search.kicker')}
            />
          </p>
          <h3 id="operator-search-title">
            <LocalizedMessage resolution={message('operator.search.title')} />
          </h3>
        </div>
        <p>
          <LocalizedMessage resolution={message('operator.search.lead')} />
        </p>
      </div>
      <form className="operator-search__form" onSubmit={submit} role="search">
        <label htmlFor="operator-search-query">
          <LocalizedMessage resolution={message('operator.search.label')} />
        </label>
        <div className="operator-search__controls">
          <input
            aria-describedby="operator-search-hint"
            autoComplete="off"
            id="operator-search-query"
            maxLength={80}
            onChange={(event) => {
              const value = event.currentTarget.value
              setInput(value)

              if (value.trim().length < 2) {
                controller.current?.abort()
                setActiveQuery('')
                setState(null)
                setNextCursor(null)
                setLoadingMore(false)
                setLoadMoreFailed(false)
              }
            }}
            placeholder={message('operator.search.placeholder').text}
            spellCheck={false}
            type="search"
            value={input}
          />
          <button type="submit">
            <LocalizedMessage resolution={message('operator.search.submit')} />
          </button>
        </div>
        <p id="operator-search-hint">
          <LocalizedMessage resolution={message('operator.search.hint')} />
        </p>
      </form>

      <div aria-live="polite" className="operator-search__results">
        {state?.status === 'loading' ? (
          <p role="status">
            <LocalizedMessage
              resolution={message('operator.search.loading')}
            />
          </p>
        ) : null}
        {state?.status === 'error' ? (
          <div role="alert">
            <p>
              <LocalizedMessage
                resolution={message('operator.search.error')}
              />
            </p>
            <button
              onClick={() => {
                executeSearch(activeQuery, undefined, false)
              }}
              type="button"
            >
              <LocalizedMessage resolution={message('operator.retry')} />
            </button>
          </div>
        ) : null}
        {state?.status === 'ready' && state.value.length === 0 ? (
          <p>
            <LocalizedMessage resolution={message('operator.search.empty')} />
          </p>
        ) : null}
        {state?.status === 'ready' && state.value.length > 0 ? (
          <>
            <p className="operator-search__summary">
              <LocalizedMessage
                resolution={message('operator.search.results')}
              />
              {': '}
              <strong>{state.value.length}</strong>
            </p>
            <ul className="operator-search__list">
              {state.value.map((result) => (
                <SearchResult
                  key={`${result.kind}:${result.id}`}
                  locale={locale}
                  message={message}
                  result={result}
                />
              ))}
            </ul>
            {loadMoreFailed ? (
              <p className="operator-panel__inline-error" role="alert">
                <LocalizedMessage
                  resolution={message('operator.search.more_error')}
                />
              </p>
            ) : null}
            {nextCursor === null ? null : (
              <button
                className="operator-panel__more"
                disabled={loadingMore}
                onClick={() => {
                  executeSearch(activeQuery, nextCursor, true)
                }}
                type="button"
              >
                <LocalizedMessage
                  resolution={message(
                    loadingMore
                      ? 'operator.loading_more'
                      : 'operator.load_more',
                  )}
                />
              </button>
            )}
          </>
        ) : null}
      </div>
    </section>
  )
}

function SearchResult({
  locale,
  message,
  result,
}: Readonly<{
  locale: SupportedLocale
  message: MessageResolver
  result: OperatorSearchResult
}>) {
  return (
    <li>
      <a href={`#${navigationMetadata[result.section].anchor}`}>
        <span className="operator-search__kind">
          <LocalizedMessage
            resolution={message(searchKindMessages[result.kind])}
          />
        </span>
        <bdi className="operator-search__primary" lang="">
          {result.primary_text}
        </bdi>
        <bdi className="operator-search__secondary" lang="">
          {result.secondary_text}
        </bdi>
        {result.occurred_at === null ? null : (
          <time dateTime={result.occurred_at}>
            {formatDateTime(result.occurred_at, locale)}
          </time>
        )}
      </a>
    </li>
  )
}
