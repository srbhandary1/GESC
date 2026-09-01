import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useState } from 'react'
import {
  resolveInterfaceMessage,
  type InterfaceMessageResolution,
} from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import type {
  OperatorAuditEvent,
  OperatorConsoleApi,
  OperatorContentDraft,
  OperatorPilotAnalyticsSummary,
  OperatorPublicationStatus,
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
import { OperatorEntitlementAdministration } from './OperatorEntitlementAdministration'
import {
  defaultOperatorEntitlementApi,
  type OperatorEntitlementApi,
} from './OperatorEntitlementApi'
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
  locale: SupportedLocale
  localizationPolicy: ResolutionPolicy
  learnerApi?: OperatorLearnerApi
  mediaApi?: OperatorMediaLibraryApi
  mfaAuthentication?: WebSessionAuthentication
  onCredentialsRevoked?: () => void
  organizationApi?: OperatorOrganizationApi
  entitlementApi?: OperatorEntitlementApi
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
  entitlements: {
    anchor: 'operator-entitlements',
    label: 'operator.navigation.entitlements',
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

export function OperatorConsole({
  api,
  locale,
  localizationPolicy,
}: OperatorConsoleProps) {
  const message = (key: MessageKey): InterfaceMessageResolution =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const [state, setState] = useState<LoadState>('loading')
  const [drafts, setDrafts] = useState<readonly OperatorContentDraft[]>([])
  const [publications, setPublications] = useState<
    readonly OperatorPublicationStatus[]
  >([])
  const [auditEvents, setAuditEvents] = useState<
    readonly OperatorAuditEvent[]
  >([])
  const [pilotAnalytics, setPilotAnalytics] =
    useState<OperatorPilotAnalyticsSummary | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    Promise.all([
      api.listDrafts(controller.signal),
      api.listPublications(controller.signal),
      api.listAuditEvents(controller.signal),
      api.getPilotAnalytics(controller.signal),
    ])
      .then(
        ([nextDrafts, nextPublications, nextAudit, nextAnalytics]) => {
          if (!active) {
            return
          }

          setDrafts(nextDrafts)
          setPublications(nextPublications)
          setAuditEvents(nextAudit)
          setPilotAnalytics(nextAnalytics)
          setState('ready')
        },
      )
      .catch(() => {
        if (active) {
          setState('error')
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
  entitlementApi = defaultOperatorEntitlementApi,
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
      data-operator-console="content-workflow"
    >
      <h2 className="operator-console__title" id="operator-console-title">
        <LocalizedMessage resolution={message('operator.console.title')} />
      </h2>
      <p className="operator-console__lead">
        <LocalizedMessage resolution={message('operator.console.lead')} />
      </p>
      <p className="operator-console__notice">
        <LocalizedMessage resolution={message('operator.console.notice')} />
      </p>

      {state === 'loading' ? (
        <p>
          <LocalizedMessage
            resolution={message('operator.console.loading')}
          />
        </p>
      ) : null}

      {state === 'error' ? (
        <p role="alert">
          <LocalizedMessage resolution={message('operator.console.error')} />
        </p>
      ) : null}

      {state === 'ready' ? (
        <>
          <section
            aria-labelledby="operator-pilot-analytics-title"
            className="operator-console__panel"
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

            {overview.state.value.navigation.includes('entitlements') ? (
              <OperatorEntitlementAdministration
                api={entitlementApi}
                createCommandId={createCommandId}
                locale={locale}
                localizationPolicy={localizationPolicy}
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
                resolution={message('operator.pilot_analytics.title')}
              />
            </h3>
            <p>
              <LocalizedMessage
                resolution={message('operator.pilot_analytics.lead')}
              />
            </p>
            {pilotAnalytics === null ? (
              <p>
                <LocalizedMessage
                  resolution={message('operator.pilot_analytics.empty')}
                />
              </p>
            ) : (
              <ul className="operator-console__list">
                <li>
                  <LocalizedMessage
                    resolution={message('operator.pilot_analytics.usage')}
                  />
                  {': '}
                  learners=
                  {String(pilotAnalytics.usage.learner_membership_count)}
                  {'; sessions='}
                  {String(pilotAnalytics.usage.conversation_session_count)}
                  {'; attempts='}
                  {String(pilotAnalytics.usage.formative_attempt_count)}
                </li>
                <li>
                  <LocalizedMessage
                    resolution={message(
                      'operator.pilot_analytics.learning',
                    )}
                  />
                  {': '}
                  mastery=
                  {String(
                    pilotAnalytics.demonstrated_learning.mastery_state_count,
                  )}
                  {'; reviews='}
                  {String(
                    pilotAnalytics.demonstrated_learning.review_schedule_count,
                  )}
                  {' · '}
                  {formatGroupedCounts(
                    pilotAnalytics.demonstrated_learning
                      .mastery_by_knowledge_state,
                  )}
                </li>
              </ul>
            )}
          </section>
          <section
            aria-labelledby="operator-review-queue-title"
            className="operator-console__panel"
          >
            <h3 id="operator-review-queue-title">
              <LocalizedMessage
                resolution={message('operator.review_queue.title')}
              />
            </h3>
            {drafts.length === 0 ? (
              <p>
                <LocalizedMessage
                  resolution={message('operator.review_queue.empty')}
                />
              </p>
            ) : (
              <ul className="operator-console__list">
                {drafts.map((draft) => (
                  <li key={draft.curriculum_version_id}>
                    <strong>
                      {draft.program_key} / {draft.version_key}
                    </strong>
                    <span>
                      {' '}
                      · {draft.publication_status}
                      {' · EN '}
                      {draft.latest_english_decision ?? 'pending'}
                      {' · KN '}
                      {draft.latest_kannada_decision ?? 'pending'}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section
            aria-labelledby="operator-publish-status-title"
            className="operator-console__panel"
          >
            <h3 id="operator-publish-status-title">
              <LocalizedMessage
                resolution={message('operator.publish_status.title')}
              />
            </h3>
            {publications.length === 0 ? (
              <p>
                <LocalizedMessage
                  resolution={message('operator.publish_status.empty')}
                />
              </p>
            ) : (
              <ul className="operator-console__list">
                {publications.map((publication) => (
                  <li key={publication.publication_id}>
                    <strong>
                      {publication.program_key} / {publication.version_key}
                    </strong>
                    <span>
                      {' '}
                      · #{publication.publication_number}
                      {publication.is_head ? ' · head' : ''}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section
            aria-labelledby="operator-audit-title"
            className="operator-console__panel"
          >
            <h3 id="operator-audit-title">
              <LocalizedMessage
                resolution={message('operator.audit.title')}
              />
            </h3>
            {auditEvents.length === 0 ? (
              <p>
                <LocalizedMessage
                  resolution={message('operator.audit.empty')}
                />
              </p>
            ) : (
              <ul className="operator-console__list">
                {auditEvents.map((event) => (
                  <li key={event.id}>
                    <strong>{event.action}</strong>
                    <span>
                      {' '}
                      · {event.subject_type}:{event.subject_id}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      ) : null}
    </section>
  )
}
