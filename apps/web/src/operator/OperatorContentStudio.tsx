import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import type {
  OperatorContentAction,
  OperatorContentDraftGraphInput,
  OperatorContentHistoryListing,
  OperatorContentPreview,
  OperatorContentReviewQueueListing,
  OperatorContentSourceQueueItem,
  OperatorContentStructureQueueItem,
  OperatorContentType,
  OperatorContentWorkspaceDetail,
  OperatorContentWorkspaceListing,
  OperatorContentWorkspaceSummary,
  OperatorPublicationListing,
  OperatorPublicationStatus,
  OperatorDailyMissionSourcePayload,
  OperatorGovernedSourcePayload,
} from '@global-english/contracts'
import {
  useCallback,
  useEffect,
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
  defaultOperatorContentStudioApi,
  OperatorContentApiError,
  type OperatorContentErrorKind,
  type OperatorContentQueue,
  type OperatorContentStudioApi,
} from './OperatorContentStudioApi'

type MessageResolver = (key: MessageKey) => InterfaceMessageResolution

type ResourceState<T> =
  | { readonly status: 'loading' }
  | { readonly status: 'error' }
  | { readonly status: 'ready'; readonly value: T }

type CommandState =
  | { readonly status: 'idle' }
  | { readonly status: 'busy' }
  | { readonly status: 'success' }
  | { readonly status: 'error'; readonly kind: OperatorContentErrorKind }

interface OperatorContentStudioProps {
  readonly api?: OperatorContentStudioApi
  readonly createCommandId?: () => string
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}

function useResource<T>(load: (signal: AbortSignal) => Promise<T>) {
  const [attempt, setAttempt] = useState(0)
  const [state, setState] = useState<ResourceState<T>>({ status: 'loading' })

  useEffect(() => {
    const controller = new AbortController()
    let active = true
    void load(controller.signal)
      .then((value) => {
        if (active) setState({ status: 'ready', value })
      })
      .catch(() => {
        if (active && !controller.signal.aborted) setState({ status: 'error' })
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [attempt, load])

  return {
    reload: () => {
      setState((current) =>
        current.status === 'ready' ? current : { status: 'loading' },
      )
      setAttempt((value) => value + 1)
    },
    state,
  } as const
}

function errorKind(error: unknown): OperatorContentErrorKind {
  return error instanceof OperatorContentApiError ? error.kind : 'unavailable'
}

function isConflict(kind: OperatorContentErrorKind): boolean {
  return [
    'concurrent_change',
    'head_conflict',
    'stale_version',
    'state_conflict',
  ].includes(kind)
}

export function OperatorContentStudio({
  api = defaultOperatorContentStudioApi,
  createCommandId = createRandomClientId,
  locale,
  localizationPolicy,
}: OperatorContentStudioProps) {
  const message: MessageResolver = (key) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const loadWorkspaces = useCallback(
    (signal: AbortSignal) => api.listWorkspaces(undefined, signal),
    [api],
  )
  const workspaces = useResource(loadWorkspaces)
  const [selectedVersion, setSelectedVersion] = useState<string | null>(null)
  const activeVersion =
    selectedVersion ??
    (workspaces.state.status === 'ready'
      ? (workspaces.state.value.data[0]?.curriculum_version_id ?? null)
      : null)

  return (
    <article
      aria-labelledby="operator-content-studio-title"
      className="operator-panel operator-panel--wide operator-content-studio"
      data-content-studio="governed"
    >
      <div className="operator-content-studio__heading">
        <div>
          <h4 id="operator-content-studio-title">
            <LocalizedMessage resolution={message('operator.content.title')} />
          </h4>
          <p>
            <LocalizedMessage resolution={message('operator.content.lead')} />
          </p>
        </div>
        <span className="operator-content-studio__private">
          <LocalizedMessage resolution={message('operator.content.private')} />
        </span>
      </div>

      {workspaces.state.status === 'loading' ? (
        <ContentLoading message={message} />
      ) : null}
      {workspaces.state.status === 'error' ? (
        <ContentFailure message={message} onRetry={workspaces.reload} />
      ) : null}
      {workspaces.state.status === 'ready' ? (
        <>
          <ProgramCreator
            api={api}
            createCommandId={createCommandId}
            message={message}
            onCreated={(versionId) => {
              setSelectedVersion(versionId)
              workspaces.reload()
            }}
            visible={workspaces.state.value.allowed_actions.includes(
              'create_program',
            )}
          />
          <WorkspaceDirectory
            listing={workspaces.state.value}
            message={message}
            onSelect={setSelectedVersion}
            selectedVersion={activeVersion}
          />
        </>
      ) : null}

      {activeVersion !== null ? (
        <WorkspaceEditor
          api={api}
          createCommandId={createCommandId}
          key={activeVersion}
          message={message}
          onWorkspaceChanged={workspaces.reload}
          onSuccessorCreated={(versionId) => {
            setSelectedVersion(versionId)
            workspaces.reload()
          }}
          versionId={activeVersion}
        />
      ) : null}

      <div className="operator-content-studio__secondary">
        <ReviewQueuePanel
          api={api}
          createCommandId={createCommandId}
          message={message}
        />
        <ContentPublicationPanel
          api={api}
          createCommandId={createCommandId}
          message={message}
        />
      </div>
    </article>
  )
}

function ProgramCreator({
  api,
  createCommandId,
  message,
  onCreated,
  visible,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
  onCreated: (versionId: string) => void
  visible: boolean
}>) {
  const [programKey, setProgramKey] = useState('')
  const [internalName, setInternalName] = useState('')
  const [versionKey, setVersionKey] = useState('')
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })

  if (!visible) return null

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setCommand({ status: 'busy' })
    void api
      .createProgram(
        {
          expected_version: 0,
          internal_name: internalName,
          program_key: programKey,
          version_key: versionKey,
        },
        createCommandId(),
      )
      .then((result) => {
        const versionId = result.curriculum_version_id ?? result.result_id
        setCommand({ status: 'success' })
        setProgramKey('')
        setInternalName('')
        setVersionKey('')
        onCreated(versionId)
      })
      .catch((error: unknown) => setCommand({ status: 'error', kind: errorKind(error) }))
  }

  return (
    <details className="operator-content-studio__disclosure">
      <summary>
        <LocalizedMessage resolution={message('operator.content.program.create')} />
      </summary>
      <form className="operator-content-studio__form" onSubmit={submit}>
        <ContentField label={message('operator.content.field.program_key').text}>
          <input
            maxLength={100}
            onChange={(event) => setProgramKey(event.target.value)}
            pattern="[a-z0-9][a-z0-9._-]*"
            required
            value={programKey}
          />
        </ContentField>
        <ContentField label={message('operator.content.field.name').text}>
          <input
            maxLength={160}
            onChange={(event) => setInternalName(event.target.value)}
            required
            value={internalName}
          />
        </ContentField>
        <ContentField label={message('operator.content.field.version_key').text}>
          <input
            maxLength={100}
            onChange={(event) => setVersionKey(event.target.value)}
            pattern="[a-z0-9][a-z0-9._-]*"
            required
            value={versionKey}
          />
        </ContentField>
        <button disabled={command.status === 'busy'} type="submit">
          <LocalizedMessage resolution={message('operator.content.program.create')} />
        </button>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function WorkspaceDirectory({
  listing,
  message,
  onSelect,
  selectedVersion,
}: Readonly<{
  listing: OperatorContentWorkspaceListing
  message: MessageResolver
  onSelect: (id: string) => void
  selectedVersion: string | null
}>) {
  return (
    <section aria-labelledby="operator-content-workspaces-title">
      <h5 id="operator-content-workspaces-title">
        <LocalizedMessage resolution={message('operator.content.workspaces.title')} />
      </h5>
      {listing.data.length === 0 ? (
        <p><LocalizedMessage resolution={message('operator.content.workspaces.empty')} /></p>
      ) : (
        <ul className="operator-content-studio__workspace-list">
          {listing.data.map((workspace) => (
            <li key={workspace.curriculum_version_id}>
              <button
                aria-pressed={selectedVersion === workspace.curriculum_version_id}
                onClick={() => onSelect(workspace.curriculum_version_id)}
                type="button"
              >
                <strong><bdi>{workspace.program_internal_name}</bdi></strong>
                <code>{workspace.program_key} / {workspace.version_key}</code>
                <span>{workspace.state} · v{workspace.workspace_version}</span>
                <BlockerCount message={message} workspace={workspace} />
              </button>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

function BlockerCount({
  message,
  workspace,
}: Readonly<{
  message: MessageResolver
  workspace: OperatorContentWorkspaceSummary
}>) {
  return (
    <span>
      {workspace.blocker_codes.length === 0
        ? message('operator.content.ready').text
        : `${String(workspace.blocker_codes.length)} ${message('operator.content.blockers').text}`}
    </span>
  )
}

function WorkspaceEditor({
  api,
  createCommandId,
  message,
  onSuccessorCreated,
  onWorkspaceChanged,
  versionId,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
  onSuccessorCreated: (versionId: string) => void
  onWorkspaceChanged: () => void
  versionId: string
}>) {
  const load = useCallback(
    (signal: AbortSignal) => api.getWorkspace(versionId, signal),
    [api, versionId],
  )
  const workspace = useResource(load)

  return (
    <section
      aria-labelledby="operator-content-workspace-title"
      className="operator-content-studio__workspace"
    >
      <h5 id="operator-content-workspace-title">
        <LocalizedMessage resolution={message('operator.content.workspace.title')} />
      </h5>
      {workspace.state.status === 'loading' ? <ContentLoading message={message} /> : null}
      {workspace.state.status === 'error' ? (
        <ContentFailure message={message} onRetry={workspace.reload} />
      ) : null}
      {workspace.state.status === 'ready' ? (
        <WorkspaceDetail
          api={api}
          createCommandId={createCommandId}
          message={message}
          onConflict={workspace.reload}
          onSuccessorCreated={onSuccessorCreated}
          onSaved={() => {
            workspace.reload()
            onWorkspaceChanged()
          }}
          workspace={workspace.state.value}
        />
      ) : null}
    </section>
  )
}

function WorkspaceDetail({
  api,
  createCommandId,
  message,
  onConflict,
  onSaved,
  onSuccessorCreated,
  workspace,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
  onConflict: () => void
  onSaved: () => void
  onSuccessorCreated: (versionId: string) => void
  workspace: OperatorContentWorkspaceDetail
}>) {
  const [selectedDocumentKey, setSelectedDocumentKey] = useState<string | null>(
    workspace.documents[0]?.document_key ?? null,
  )
  const [lifecycleCommand, setLifecycleCommand] = useState<CommandState>({ status: 'idle' })
  const [confirmPublish, setConfirmPublish] = useState(false)

  const publish = () => {
    setLifecycleCommand({ status: 'busy' })
    void api
      .publishNow(
        {
          curriculum_version_id: workspace.curriculum_version_id,
          expected_head_version: workspace.publication_head_version,
        },
        createCommandId(),
      )
      .then(() => {
        setConfirmPublish(false)
        setLifecycleCommand({ status: 'success' })
        onSaved()
      })
      .catch((error: unknown) => {
        const kind = errorKind(error)
        if (isConflict(kind)) onConflict()
        setLifecycleCommand({ status: 'error', kind })
      })
  }

  return (
    <div className="operator-content-studio__workspace-body">
      <div className="operator-content-studio__identity">
        <div>
          <strong><bdi>{workspace.program_internal_name}</bdi></strong>
          <code>{workspace.program_key} / {workspace.version_key}</code>
        </div>
        <span>{workspace.state} · v{workspace.workspace_version}</span>
      </div>
      <Readiness message={message} workspace={workspace} />

      {workspace.allowed_actions.includes('correct_descriptor') ? (
        <DescriptorCorrectionForm
          api={api}
          createCommandId={createCommandId}
          message={message}
          onConflict={onConflict}
          onSaved={onSaved}
          workspace={workspace}
        />
      ) : null}

      {workspace.allowed_actions.includes('edit_graph') ? (
        <GraphEditor
          api={api}
          createCommandId={createCommandId}
          message={message}
          onConflict={onConflict}
          onSaved={onSaved}
          workspace={workspace}
        />
      ) : null}

      {workspace.allowed_actions.includes('create_document') ? (
        <DocumentCreator
          api={api}
          createCommandId={createCommandId}
          message={message}
          onConflict={onConflict}
          onSaved={onSaved}
          workspace={workspace}
        />
      ) : null}

      <DocumentWorkspace
        api={api}
        createCommandId={createCommandId}
        message={message}
        onConflict={onConflict}
        onSaved={onSaved}
        onSelect={setSelectedDocumentKey}
        selectedDocumentKey={selectedDocumentKey}
        workspace={workspace}
      />

      <WorkspaceHistory
        api={api}
        key={`${workspace.curriculum_version_id}-${String(workspace.workspace_version)}`}
        message={message}
        versionId={workspace.curriculum_version_id}
      />

      <div className="operator-content-studio__actions">
        {workspace.allowed_actions.includes('submit_structure') ? (
          <WorkspaceCommandButton
            action={() => api.submitStructure(
              workspace.curriculum_version_id,
              { expected_workspace_version: workspace.workspace_version },
              createCommandId(),
            )}
            label={message('operator.content.action.submit').text}
            message={message}
            onConflict={onConflict}
            onSaved={onSaved}
          />
        ) : null}
        {workspace.allowed_actions.includes('abandon') ? (
          <AbandonWorkspaceForm
            api={api}
            createCommandId={createCommandId}
            message={message}
            onConflict={onConflict}
            onSaved={onSaved}
            workspace={workspace}
          />
        ) : null}
        {workspace.allowed_actions.includes('create_successor') ? (
          <SuccessorForm
            api={api}
            createCommandId={createCommandId}
            message={message}
            onConflict={onConflict}
            onCreated={onSuccessorCreated}
            workspace={workspace}
          />
        ) : null}
        {workspace.allowed_actions.includes('schedule') ? (
          <SchedulePublicationForm
            api={api}
            createCommandId={createCommandId}
            message={message}
            onConflict={onConflict}
            onSaved={onSaved}
            workspace={workspace}
          />
        ) : null}
        {workspace.allowed_actions.includes('publish_now') ? (
          confirmPublish ? (
            <div className="operator-content-studio__confirmation" role="alert">
              <p><LocalizedMessage resolution={message('operator.content.publish.confirm')} /></p>
              <button disabled={lifecycleCommand.status === 'busy'} onClick={publish} type="button">
                <LocalizedMessage resolution={message('operator.content.publish.confirm_action')} />
              </button>
              <button onClick={() => setConfirmPublish(false)} type="button">
                <LocalizedMessage resolution={message('operator.content.cancel')} />
              </button>
            </div>
          ) : (
            <button onClick={() => setConfirmPublish(true)} type="button">
              <LocalizedMessage resolution={message('operator.content.action.publish')} />
            </button>
          )
        ) : null}
      </div>
      <CommandFeedback command={lifecycleCommand} message={message} />
    </div>
  )
}

function DescriptorCorrectionForm({
  api, createCommandId, message, onConflict, onSaved, workspace,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
  onConflict: () => void
  onSaved: () => void
  workspace: OperatorContentWorkspaceDetail
}>) {
  const [internalName, setInternalName] = useState(workspace.program_internal_name)
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setCommand({ status: 'busy' })
    void api.correctDescriptor(workspace.program_id, {
      expected_descriptor_version: workspace.descriptor_version,
      internal_name: internalName,
    }, createCommandId()).then(() => {
      setCommand({ status: 'success' })
      onSaved()
    }).catch((error: unknown) => {
      const kind = errorKind(error)
      if (isConflict(kind)) onConflict()
      setCommand({ status: 'error', kind })
    })
  }

  return (
    <details className="operator-content-studio__disclosure">
      <summary><LocalizedMessage resolution={message('operator.content.descriptor.title')} /></summary>
      <form className="operator-content-studio__form" onSubmit={submit}>
        <ContentField label={message('operator.content.field.name').text}>
          <input maxLength={160} onChange={(event) => setInternalName(event.target.value)} required value={internalName} />
        </ContentField>
        <button disabled={command.status === 'busy'} type="submit">
          <LocalizedMessage resolution={message('operator.content.descriptor.save')} />
        </button>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function AbandonWorkspaceForm({
  api, createCommandId, message, onConflict, onSaved, workspace,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
  onConflict: () => void
  onSaved: () => void
  workspace: OperatorContentWorkspaceDetail
}>) {
  const [reasonKey, setReasonKey] = useState<'work_discontinued' | 'superseded' | 'created_in_error'>('work_discontinued')
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setCommand({ status: 'busy' })
    void api.abandonWorkspace(workspace.curriculum_version_id, {
      expected_workspace_version: workspace.workspace_version,
      reason_key: reasonKey,
    }, createCommandId()).then(() => {
      setCommand({ status: 'success' })
      onSaved()
    }).catch((error: unknown) => {
      const kind = errorKind(error)
      if (isConflict(kind)) onConflict()
      setCommand({ status: 'error', kind })
    })
  }

  return (
    <details className="operator-content-studio__action-disclosure">
      <summary><LocalizedMessage resolution={message('operator.content.action.abandon')} /></summary>
      <form className="operator-content-studio__form" onSubmit={submit}>
        <p><LocalizedMessage resolution={message('operator.content.abandon.confirm')} /></p>
        <ContentField label={message('operator.content.field.reason').text}>
          <select onChange={(event) => setReasonKey(event.target.value as typeof reasonKey)} value={reasonKey}>
            <option value="work_discontinued">work_discontinued</option>
            <option value="superseded">superseded</option>
            <option value="created_in_error">created_in_error</option>
          </select>
        </ContentField>
        <button disabled={command.status === 'busy'} type="submit">
          <LocalizedMessage resolution={message('operator.content.abandon.confirm_action')} />
        </button>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function SuccessorForm({
  api, createCommandId, message, onConflict, onCreated, workspace,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
  onConflict: () => void
  onCreated: (versionId: string) => void
  workspace: OperatorContentWorkspaceDetail
}>) {
  const [versionKey, setVersionKey] = useState('')
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setCommand({ status: 'busy' })
    void api.createSuccessor(workspace.curriculum_version_id, {
      expected_source_version: workspace.workspace_version,
      successor_version_key: versionKey,
    }, createCommandId()).then((result) => {
      setCommand({ status: 'success' })
      onCreated(result.curriculum_version_id ?? result.result_id)
    }).catch((error: unknown) => {
      const kind = errorKind(error)
      if (isConflict(kind)) onConflict()
      setCommand({ status: 'error', kind })
    })
  }

  return (
    <details className="operator-content-studio__action-disclosure">
      <summary><LocalizedMessage resolution={message('operator.content.successor.title')} /></summary>
      <form className="operator-content-studio__form" onSubmit={submit}>
        <p><LocalizedMessage resolution={message('operator.content.successor.explanation')} /></p>
        <ContentField label={message('operator.content.field.version_key').text}>
          <input maxLength={100} onChange={(event) => setVersionKey(event.target.value)} pattern="[a-z0-9][a-z0-9._-]*" required value={versionKey} />
        </ContentField>
        <button disabled={command.status === 'busy'} type="submit">
          <LocalizedMessage resolution={message('operator.content.successor.create')} />
        </button>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function SchedulePublicationForm({
  api, createCommandId, message, onConflict, onSaved, workspace,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
  onConflict: () => void
  onSaved: () => void
  workspace: OperatorContentWorkspaceDetail
}>) {
  const [activationAt, setActivationAt] = useState('')
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const parsed = new Date(activationAt)
    if (Number.isNaN(parsed.getTime())) {
      setCommand({ status: 'error', kind: 'validation' })
      return
    }
    setCommand({ status: 'busy' })
    void api.schedulePublication({
      activation_at: parsed.toISOString(),
      curriculum_version_id: workspace.curriculum_version_id,
      expected_head_version: workspace.publication_head_version,
    }, createCommandId()).then(() => {
      setCommand({ status: 'success' })
      onSaved()
    }).catch((error: unknown) => {
      const kind = errorKind(error)
      if (isConflict(kind)) onConflict()
      setCommand({ status: 'error', kind })
    })
  }

  return (
    <details className="operator-content-studio__action-disclosure">
      <summary><LocalizedMessage resolution={message('operator.content.schedule.title')} /></summary>
      <form className="operator-content-studio__form" onSubmit={submit}>
        <p><LocalizedMessage resolution={message('operator.content.schedule.confirm')} /></p>
        <ContentField label={message('operator.content.field.activation_at').text}>
          <input onChange={(event) => setActivationAt(event.target.value)} required type="datetime-local" value={activationAt} />
        </ContentField>
        <button disabled={command.status === 'busy'} type="submit">
          <LocalizedMessage resolution={message('operator.content.schedule.confirm_action')} />
        </button>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function WorkspaceHistory({
  api, message, versionId,
}: Readonly<{
  api: OperatorContentStudioApi
  message: MessageResolver
  versionId: string
}>) {
  const load = useCallback(
    (signal: AbortSignal) => api.listHistory(versionId, undefined, signal),
    [api, versionId],
  )
  const history = useResource<OperatorContentHistoryListing>(load)

  return (
    <details className="operator-content-studio__disclosure">
      <summary><LocalizedMessage resolution={message('operator.content.history.title')} /></summary>
      {history.state.status === 'loading' ? <ContentLoading message={message} /> : null}
      {history.state.status === 'error' ? <ContentFailure message={message} onRetry={history.reload} /> : null}
      {history.state.status === 'ready' && history.state.value.data.length === 0 ? (
        <p><LocalizedMessage resolution={message('operator.content.history.empty')} /></p>
      ) : null}
      {history.state.status === 'ready' && history.state.value.data.length > 0 ? (
        <ol className="operator-content-studio__history">
          {history.state.value.data.map((event) => (
            <li key={event.id}>
              <code>{event.event_kind}</code>
              <time dateTime={event.occurred_at}>{event.occurred_at}</time>
              {event.actor_membership_id === null ? null : <span>{event.actor_membership_id}</span>}
            </li>
          ))}
        </ol>
      ) : null}
    </details>
  )
}

function Readiness({
  message,
  workspace,
}: Readonly<{
  message: MessageResolver
  workspace: OperatorContentWorkspaceDetail
}>) {
  return (
    <section className="operator-content-studio__readiness" aria-live="polite">
      <strong>
        <LocalizedMessage
          resolution={message(
            workspace.readiness.ready
              ? 'operator.content.ready'
              : 'operator.content.blockers',
          )}
        />
      </strong>
      {workspace.readiness.blocker_codes.length > 0 ? (
        <ul>
          {workspace.readiness.blocker_codes.map((blocker) => (
            <li key={blocker}><code>{blocker}</code></li>
          ))}
        </ul>
      ) : null}
    </section>
  )
}

interface GraphFormRow { readonly [key: string]: string }
interface GraphFormState {
  readonly levels: readonly GraphFormRow[]
  readonly objectives: readonly GraphFormRow[]
  readonly modules: readonly GraphFormRow[]
  readonly lessons: readonly GraphFormRow[]
  readonly items: readonly GraphFormRow[]
  readonly delivery_bundles: readonly GraphFormRow[]
}

const graphDefinitions = {
  levels: ['key', 'sequence'],
  objectives: ['key', 'outcome_code', 'sequence', 'prerequisite_keys'],
  modules: ['key', 'level_key', 'sequence', 'estimated_minutes'],
  lessons: ['key', 'module_key', 'sequence', 'objective_keys'],
  items: ['key', 'lesson_key', 'content_document_key', 'item_type', 'sequence'],
  delivery_bundles: ['key', 'instruction_document_key', 'sequence', 'item_keys'],
} as const

function graphForm(workspace: OperatorContentWorkspaceDetail): GraphFormState {
  const value = workspace.graph?.value
  if (value === undefined) {
    return {
      levels: [], objectives: [], modules: [], lessons: [], items: [], delivery_bundles: [],
    }
  }

  const rows = <T extends Readonly<Record<string, unknown>>>(
    values: readonly T[],
    fields: readonly string[],
  ): readonly GraphFormRow[] => values.map((row) => Object.fromEntries(
    fields.map((field) => {
      const value = row[field]
      return [field, Array.isArray(value) ? value.join(', ') : value === null ? '' : String(value ?? '')]
    }),
  ))

  return {
    levels: rows(value.levels, graphDefinitions.levels),
    objectives: rows(value.objectives, graphDefinitions.objectives),
    modules: rows(value.modules, graphDefinitions.modules),
    lessons: rows(value.lessons, graphDefinitions.lessons),
    items: rows(value.items, graphDefinitions.items),
    delivery_bundles: rows(value.delivery_bundles, graphDefinitions.delivery_bundles),
  }
}

function graphInput(form: GraphFormState): OperatorContentDraftGraphInput {
  const number = (value: string) => Number.parseInt(value, 10)
  const list = (value: string) => value.split(',').map((item) => item.trim()).filter(Boolean)
  return {
    schema: 'global-english.curriculum-draft-graph.v2',
    levels: form.levels.map((row) => ({ key: row.key ?? '', sequence: number(row.sequence ?? '') })),
    objectives: form.objectives.map((row) => ({
      key: row.key ?? '', outcome_code: row.outcome_code ?? '', sequence: number(row.sequence ?? ''),
      prerequisite_keys: list(row.prerequisite_keys ?? ''),
    })),
    modules: form.modules.map((row) => ({
      key: row.key ?? '', level_key: row.level_key ?? '', sequence: number(row.sequence ?? ''),
      estimated_minutes: row.estimated_minutes === '' ? null : number(row.estimated_minutes ?? ''),
    })),
    lessons: form.lessons.map((row) => ({
      key: row.key ?? '', module_key: row.module_key ?? '', sequence: number(row.sequence ?? ''),
      objective_keys: list(row.objective_keys ?? ''),
    })),
    items: form.items.map((row) => ({
      key: row.key ?? '', lesson_key: row.lesson_key ?? '',
      content_document_key: row.content_document_key ?? '', item_type: row.item_type ?? '',
      sequence: number(row.sequence ?? ''),
    })),
    delivery_bundles: form.delivery_bundles.map((row) => ({
      key: row.key ?? '', instruction_document_key: row.instruction_document_key ?? '',
      sequence: number(row.sequence ?? ''), item_keys: list(row.item_keys ?? ''),
    })),
  }
}

function GraphEditor({
  api, createCommandId, message, onConflict, onSaved, workspace,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
  onConflict: () => void
  onSaved: () => void
  workspace: OperatorContentWorkspaceDetail
}>) {
  const [form, setForm] = useState<GraphFormState>(() => graphForm(workspace))
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setCommand({ status: 'busy' })
    void api.saveGraph(
      workspace.curriculum_version_id,
      { expected_workspace_version: workspace.workspace_version, graph: graphInput(form) },
      createCommandId(),
    ).then(() => {
      setCommand({ status: 'success' })
      onSaved()
    }).catch((error: unknown) => {
      const kind = errorKind(error)
      if (isConflict(kind)) onConflict()
      setCommand({ status: 'error', kind })
    })
  }

  const update = (collection: keyof GraphFormState, rows: readonly GraphFormRow[]) => {
    setForm((current) => ({ ...current, [collection]: rows }))
  }

  return (
    <details className="operator-content-studio__disclosure">
      <summary><LocalizedMessage resolution={message('operator.content.graph.title')} /></summary>
      <form className="operator-content-studio__graph" onSubmit={submit}>
        {(Object.keys(graphDefinitions) as (keyof typeof graphDefinitions)[]).map((collection) => (
          <GraphCollection
            collection={collection}
            fields={graphDefinitions[collection]}
            key={collection}
            message={message}
            onChange={(rows) => update(collection, rows)}
            rows={form[collection]}
          />
        ))}
        <button disabled={command.status === 'busy'} type="submit">
          <LocalizedMessage resolution={message('operator.content.graph.save')} />
        </button>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function GraphCollection({
  collection, fields, message, onChange, rows,
}: Readonly<{
  collection: keyof GraphFormState
  fields: readonly string[]
  message: MessageResolver
  onChange: (rows: readonly GraphFormRow[]) => void
  rows: readonly GraphFormRow[]
}>) {
  return (
    <fieldset className="operator-content-studio__graph-collection">
      <legend>{message(`operator.content.graph.${collection}` as MessageKey).text}</legend>
      {rows.map((row, index) => (
        <div className="operator-content-studio__graph-row" key={`${collection}-${String(index)}`}>
          {fields.map((field) => (
            <ContentField key={field} label={message(`operator.content.field.${field}` as MessageKey).text}>
              <input
                min={field === 'sequence' || field === 'estimated_minutes' ? 1 : undefined}
                onChange={(event) => onChange(rows.map((item, rowIndex) =>
                  rowIndex === index ? { ...item, [field]: event.target.value } : item,
                ))}
                required={field !== 'estimated_minutes'}
                type={field === 'sequence' || field === 'estimated_minutes' ? 'number' : 'text'}
                value={row[field] ?? ''}
              />
            </ContentField>
          ))}
          <button onClick={() => onChange(rows.filter((_, rowIndex) => rowIndex !== index))} type="button">
            <LocalizedMessage resolution={message('operator.content.remove')} />
          </button>
        </div>
      ))}
      <button
        onClick={() => onChange([...rows, Object.fromEntries(fields.map((field) => [field, field === 'sequence' ? String(rows.length + 1) : '']))])}
        type="button"
      >
        <LocalizedMessage resolution={message('operator.content.add')} />
      </button>
    </fieldset>
  )
}

type WritablePayloadSchema =
  | 'global-english.governed-text.v1'
  | 'global-english.daily-speaking-mission.v1'

interface DailyMissionOptionForm {
  readonly id: string
  readonly label: string
}

interface DailyMissionFormState {
  readonly goalKey: OperatorDailyMissionSourcePayload['goal_key']
  readonly estimatedMinutes: string
  readonly title: string
  readonly modelText: string
  readonly transcript: string
  readonly canDo: string
  readonly modelAudioDurationMs: string
  readonly checkId: string
  readonly checkPrompt: string
  readonly options: readonly DailyMissionOptionForm[]
  readonly correctOptionId: string
}

const contentTypes: readonly OperatorContentType[] = [
  'assessment_rationale',
  'dialogue',
  'explanation',
  'feedback',
  'instruction',
  'learner_action',
  'model_text',
  'safety_note',
  'title',
  'transcript',
]

function emptyDailyMission(): DailyMissionFormState {
  return {
    goalKey: 'everyday-conversation',
    estimatedMinutes: '10',
    title: '',
    modelText: '',
    transcript: '',
    canDo: '',
    modelAudioDurationMs: '3000',
    checkId: 'understanding-check',
    checkPrompt: '',
    options: [{ id: 'option-a', label: '' }, { id: 'option-b', label: '' }],
    correctOptionId: 'option-a',
  }
}

function dailyMissionPayload(form: DailyMissionFormState): OperatorDailyMissionSourcePayload {
  return {
    schema: 'global-english.daily-speaking-mission.v1',
    goal_key: form.goalKey,
    estimated_minutes: Number.parseInt(form.estimatedMinutes, 10),
    title: form.title,
    model_text: form.modelText,
    transcript: form.transcript,
    can_do: form.canDo,
    model_audio_duration_ms: Number.parseInt(form.modelAudioDurationMs, 10),
    check: {
      id: form.checkId,
      prompt: form.checkPrompt,
      options: form.options.map((option) => ({ ...option })),
      correct_option_id: form.correctOptionId,
    },
  }
}

function missionFormFromPayload(payload: Readonly<Record<string, unknown>>): DailyMissionFormState {
  const initial = emptyDailyMission()
  const check = isRecord(payload.check) ? payload.check : {}
  const options = Array.isArray(check.options)
    ? check.options.flatMap((option) => isRecord(option)
      && typeof option.id === 'string'
      && typeof option.label === 'string'
      ? [{ id: option.id, label: option.label }]
      : [])
    : initial.options
  const goal = payload.goal_key

  return {
    goalKey: goal === 'workplace-confidence' || goal === 'travel-confidence'
      ? goal
      : 'everyday-conversation',
    estimatedMinutes: scalarText(payload.estimated_minutes, initial.estimatedMinutes),
    title: scalarText(payload.title),
    modelText: scalarText(payload.model_text),
    transcript: scalarText(payload.transcript),
    canDo: scalarText(payload.can_do),
    modelAudioDurationMs: scalarText(payload.model_audio_duration_ms, initial.modelAudioDurationMs),
    checkId: scalarText(check.id, initial.checkId),
    checkPrompt: scalarText(check.prompt),
    options: options.length >= 2 ? options.slice(0, 6) : initial.options,
    correctOptionId: scalarText(check.correct_option_id, initial.correctOptionId),
  }
}

function isRecord(value: unknown): value is Readonly<Record<string, unknown>> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function scalarText(value: unknown, fallback = ''): string {
  return typeof value === 'string' || typeof value === 'number'
    ? String(value)
    : fallback
}

function DailyMissionFields({
  form, message, onChange,
}: Readonly<{
  form: DailyMissionFormState
  message: MessageResolver
  onChange: (form: DailyMissionFormState) => void
}>) {
  const update = <K extends keyof DailyMissionFormState>(
    key: K,
    value: DailyMissionFormState[K],
  ) => onChange({ ...form, [key]: value })

  return (
    <fieldset className="operator-content-studio__mission-fields">
      <legend><LocalizedMessage resolution={message('operator.content.mission.title')} /></legend>
      <ContentField label={message('operator.content.field.goal_key').text}>
        <select onChange={(event) => update('goalKey', event.target.value as DailyMissionFormState['goalKey'])} value={form.goalKey}>
          <option value="everyday-conversation">everyday-conversation</option>
          <option value="workplace-confidence">workplace-confidence</option>
          <option value="travel-confidence">travel-confidence</option>
        </select>
      </ContentField>
      <ContentField label={message('operator.content.field.estimated_minutes').text}>
        <input min={1} onChange={(event) => update('estimatedMinutes', event.target.value)} required type="number" value={form.estimatedMinutes} />
      </ContentField>
      <ContentField label={message('operator.content.field.title').text}>
        <input maxLength={8000} onChange={(event) => update('title', event.target.value)} required value={form.title} />
      </ContentField>
      <ContentField label={message('operator.content.field.model_text').text}>
        <textarea maxLength={8000} onChange={(event) => update('modelText', event.target.value)} required rows={4} value={form.modelText} />
      </ContentField>
      <ContentField label={message('operator.content.field.transcript').text}>
        <textarea maxLength={8000} onChange={(event) => update('transcript', event.target.value)} required rows={4} value={form.transcript} />
      </ContentField>
      <ContentField label={message('operator.content.field.can_do').text}>
        <textarea maxLength={8000} onChange={(event) => update('canDo', event.target.value)} required rows={3} value={form.canDo} />
      </ContentField>
      <ContentField label={message('operator.content.field.model_audio_duration_ms').text}>
        <input min={1} onChange={(event) => update('modelAudioDurationMs', event.target.value)} required type="number" value={form.modelAudioDurationMs} />
      </ContentField>
      <ContentField label={message('operator.content.field.check_id').text}>
        <input maxLength={100} onChange={(event) => update('checkId', event.target.value)} pattern="[a-z0-9][a-z0-9._-]*" required value={form.checkId} />
      </ContentField>
      <ContentField label={message('operator.content.field.check_prompt').text}>
        <textarea maxLength={8000} onChange={(event) => update('checkPrompt', event.target.value)} required rows={3} value={form.checkPrompt} />
      </ContentField>
      <fieldset className="operator-content-studio__mission-options">
        <legend><LocalizedMessage resolution={message('operator.content.mission.options')} /></legend>
        {form.options.map((option, index) => (
          <div className="operator-content-studio__mission-option" key={`mission-option-${String(index)}`}>
            <ContentField label={`${message('operator.content.field.option_id').text} ${String(index + 1)}`}>
              <input
                maxLength={100}
                onChange={(event) => update('options', form.options.map((current, optionIndex) => optionIndex === index ? { ...current, id: event.target.value } : current))}
                pattern="[a-z0-9][a-z0-9._-]*"
                required
                value={option.id}
              />
            </ContentField>
            <ContentField label={`${message('operator.content.field.option_label').text} ${String(index + 1)}`}>
              <input
                maxLength={8000}
                onChange={(event) => update('options', form.options.map((current, optionIndex) => optionIndex === index ? { ...current, label: event.target.value } : current))}
                required
                value={option.label}
              />
            </ContentField>
            {form.options.length > 2 ? (
              <button onClick={() => update('options', form.options.filter((_, optionIndex) => optionIndex !== index))} type="button">
                <LocalizedMessage resolution={message('operator.content.remove')} />
              </button>
            ) : null}
          </div>
        ))}
        {form.options.length < 6 ? (
          <button onClick={() => update('options', [...form.options, { id: `option-${String(form.options.length + 1)}`, label: '' }])} type="button">
            <LocalizedMessage resolution={message('operator.content.mission.add_option')} />
          </button>
        ) : null}
      </fieldset>
      <ContentField label={message('operator.content.field.correct_option_id').text}>
        <select onChange={(event) => update('correctOptionId', event.target.value)} required value={form.correctOptionId}>
          {form.options.map((option, index) => (
            <option key={`correct-${String(index)}`} value={option.id}>{option.id || String(index + 1)}</option>
          ))}
        </select>
      </ContentField>
    </fieldset>
  )
}

function DocumentCreator({
  api, createCommandId, message, onConflict, onSaved, workspace,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
  onConflict: () => void
  onSaved: () => void
  workspace: OperatorContentWorkspaceDetail
}>) {
  const [documentKey, setDocumentKey] = useState('')
  const [payloadSchema, setPayloadSchema] = useState<WritablePayloadSchema>('global-english.governed-text.v1')
  const [contentType, setContentType] = useState<OperatorContentType>('instruction')
  const [text, setText] = useState('')
  const [mission, setMission] = useState<DailyMissionFormState>(emptyDailyMission)
  const [learnerVisible, setLearnerVisible] = useState(true)
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setCommand({ status: 'busy' })
    void api.createDocument(
      workspace.curriculum_version_id,
      {
        content_type: contentType,
        document_key: documentKey,
        expected_workspace_version: workspace.workspace_version,
        learner_visible: learnerVisible,
        payload: payloadSchema === 'global-english.daily-speaking-mission.v1'
          ? dailyMissionPayload(mission)
          : { schema: 'global-english.governed-text.v1', text },
      },
      createCommandId(),
    ).then(() => {
      setDocumentKey('')
      setText('')
      setMission(emptyDailyMission())
      setCommand({ status: 'success' })
      onSaved()
    }).catch((error: unknown) => {
      const kind = errorKind(error)
      if (isConflict(kind)) onConflict()
      setCommand({ status: 'error', kind })
    })
  }

  return (
    <details className="operator-content-studio__disclosure">
      <summary><LocalizedMessage resolution={message('operator.content.document.create')} /></summary>
      <form className="operator-content-studio__form" onSubmit={submit}>
        <ContentField label={message('operator.content.field.document_key').text}>
          <input maxLength={120} onChange={(event) => setDocumentKey(event.target.value)} required value={documentKey} />
        </ContentField>
        <ContentField label={message('operator.content.field.content_type').text}>
          <select
            disabled={payloadSchema === 'global-english.daily-speaking-mission.v1'}
            onChange={(event) => setContentType(event.target.value as OperatorContentType)}
            value={contentType}
          >
            {contentTypes.map((type) => <option key={type} value={type}>{type}</option>)}
          </select>
        </ContentField>
        <ContentField label={message('operator.content.field.payload_schema').text}>
          <select
            onChange={(event) => {
              const schema = event.target.value as WritablePayloadSchema
              setPayloadSchema(schema)
              if (schema === 'global-english.daily-speaking-mission.v1') {
                setContentType('instruction')
                setLearnerVisible(true)
              }
            }}
            value={payloadSchema}
          >
            <option value="global-english.governed-text.v1">governed text</option>
            <option value="global-english.daily-speaking-mission.v1">daily speaking mission</option>
          </select>
        </ContentField>
        {payloadSchema === 'global-english.governed-text.v1' ? (
          <ContentField label={message('operator.content.field.english_text').text}>
            <textarea maxLength={8000} onChange={(event) => setText(event.target.value)} required rows={5} value={text} />
          </ContentField>
        ) : <DailyMissionFields form={mission} message={message} onChange={setMission} />}
        <label className="operator-content-studio__checkbox">
          <input
            checked={learnerVisible}
            disabled={payloadSchema === 'global-english.daily-speaking-mission.v1'}
            onChange={(event) => setLearnerVisible(event.target.checked)}
            type="checkbox"
          />
          <LocalizedMessage resolution={message('operator.content.field.learner_visible')} />
        </label>
        <button disabled={command.status === 'busy'} type="submit">
          <LocalizedMessage resolution={message('operator.content.document.create')} />
        </button>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function DocumentWorkspace({
  api, createCommandId, message, onConflict, onSaved, onSelect,
  selectedDocumentKey, workspace,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
  onConflict: () => void
  onSaved: () => void
  onSelect: (key: string) => void
  selectedDocumentKey: string | null
  workspace: OperatorContentWorkspaceDetail
}>) {
  const selected = workspace.documents.find((document) => document.document_key === selectedDocumentKey)
  const loadPreview = useCallback(
    (signal: AbortSignal) => selectedDocumentKey === null
      ? Promise.reject(new Error('No document selected.'))
      : api.previewDocument(workspace.curriculum_version_id, selectedDocumentKey, signal),
    [api, selectedDocumentKey, workspace.curriculum_version_id],
  )
  const preview = useResource(loadPreview)

  if (workspace.documents.length === 0) {
    return <p><LocalizedMessage resolution={message('operator.content.documents.empty')} /></p>
  }

  return (
    <section className="operator-content-studio__documents" aria-labelledby="operator-content-documents-title">
      <h6 id="operator-content-documents-title"><LocalizedMessage resolution={message('operator.content.documents.title')} /></h6>
      <div className="operator-content-studio__document-layout">
        <ul className="operator-content-studio__document-list">
          {workspace.documents.map((document) => (
            <li key={document.content_document_id}>
              <button aria-pressed={selectedDocumentKey === document.document_key} onClick={() => onSelect(document.document_key)} type="button">
                <code>{document.document_key}</code>
                <span>{document.content_type} · r{document.source_revision_number}</span>
              </button>
            </li>
          ))}
        </ul>
        <div className="operator-content-studio__preview-region">
          {preview.state.status === 'loading' ? <ContentLoading message={message} /> : null}
          {preview.state.status === 'error' ? <ContentFailure message={message} onRetry={preview.reload} /> : null}
          {preview.state.status === 'ready' ? (
            <>
              <PrivatePreview message={message} preview={preview.state.value} />
              {selected !== undefined && workspace.allowed_actions.includes('edit_graph') && !preview.state.value.read_only ? (
                <ContentRevisionForms
                  api={api}
                  createCommandId={createCommandId}
                  document={selected}
                  message={message}
                  onConflict={onConflict}
                  onSaved={() => {
                    preview.reload()
                    onSaved()
                  }}
                  preview={preview.state.value}
                  translate={workspace.allowed_actions.includes('translate')}
                />
              ) : null}
            </>
          ) : null}
        </div>
      </div>
    </section>
  )
}

function PrivatePreview({ message, preview }: Readonly<{ message: MessageResolver; preview: OperatorContentPreview }>) {
  return (
    <section className="operator-content-studio__preview" aria-live="polite">
      <h6><LocalizedMessage resolution={message('operator.content.preview.title')} /></h6>
      <div className="operator-content-studio__preview-columns">
        <article lang="en-IN">
          <strong>English · r{preview.english.revision_number}</strong>
          <PayloadPreview message={message} payload={preview.english.payload} />
        </article>
        <article lang="kn-IN">
          <strong>ಕನ್ನಡ{preview.kannada === null ? '' : ` · r${String(preview.kannada.revision_number)}`}</strong>
          {preview.kannada === null
            ? <p>{message('operator.content.preview.kannada_missing').text}</p>
            : <PayloadPreview message={message} payload={preview.kannada.payload} />}
        </article>
      </div>
    </section>
  )
}

function PayloadPreview({
  message, payload,
}: Readonly<{
  message: MessageResolver
  payload: Readonly<Record<string, unknown>>
}>) {
  if (payload.schema === 'global-english.governed-text.v1') {
    return <p>{scalarText(payload.text)}</p>
  }
  if (payload.schema === 'global-english.daily-speaking-mission.v1') {
    const fields = ['goal_key', 'estimated_minutes', 'title', 'model_text', 'transcript', 'can_do', 'model_audio_duration_ms', 'guidance'] as const
    return (
      <dl>
        {fields.flatMap((field) => payload[field] === undefined ? [] : [
          <div key={field}>
            <dt>{field}</dt>
            <dd>{scalarText(payload[field])}</dd>
          </div>,
        ])}
      </dl>
    )
  }

  const safeScalars = Object.entries(payload)
    .filter(([, value]) => ['string', 'number', 'boolean'].includes(typeof value))
    .slice(0, 20)
  if (safeScalars.length === 0) {
    return <p><LocalizedMessage resolution={message('operator.content.preview.typed')} /></p>
  }
  return (
    <dl>
      {safeScalars.map(([key, value]) => (
        <div key={key}><dt>{key}</dt><dd>{String(value)}</dd></div>
      ))}
    </dl>
  )
}

function ContentRevisionForms({
  api, createCommandId, document, message, onConflict, onSaved, preview, translate,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  document: OperatorContentWorkspaceDetail['documents'][number]
  message: MessageResolver
  onConflict: () => void
  onSaved: () => void
  preview: OperatorContentPreview
  translate: boolean
}>) {
  const sourceText = typeof preview.english.payload.text === 'string' ? preview.english.payload.text : ''
  const kannadaText = preview.kannada !== null && typeof preview.kannada.payload.text === 'string' ? preview.kannada.payload.text : ''
  const kannadaGuidance = preview.kannada !== null && typeof preview.kannada.payload.guidance === 'string' ? preview.kannada.payload.guidance : ''
  const [english, setEnglish] = useState(sourceText)
  const [kannada, setKannada] = useState(kannadaText)
  const [guidance, setGuidance] = useState(kannadaGuidance)
  const [mission, setMission] = useState<DailyMissionFormState>(() => missionFormFromPayload(preview.english.payload))
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })

  const finish = (promise: Promise<unknown>) => {
    setCommand({ status: 'busy' })
    void promise.then(() => {
      setCommand({ status: 'success' })
      onSaved()
    }).catch((error: unknown) => {
      const kind = errorKind(error)
      if (isConflict(kind)) onConflict()
      setCommand({ status: 'error', kind })
    })
  }

  const appendEnglish = (payload: OperatorGovernedSourcePayload) => {
    finish(api.appendEnglish(document.content_document_id, {
      content_type: document.content_type,
      expected_head_revision_id: document.source_revision_id,
      expected_revision_number: document.source_revision_number,
      learner_visible: document.learner_visible,
      payload,
    }, createCommandId()))
  }

  return (
    <div className="operator-content-studio__revision-forms">
      {preview.english.payload.schema === 'global-english.governed-text.v1' ? (
        <form onSubmit={(event) => {
          event.preventDefault()
          appendEnglish({ schema: 'global-english.governed-text.v1', text: english })
        }}>
          <ContentField label={message('operator.content.field.english_text').text}>
            <textarea maxLength={8000} onChange={(event) => setEnglish(event.target.value)} required rows={4} value={english} />
          </ContentField>
          <button disabled={command.status === 'busy'} type="submit"><LocalizedMessage resolution={message('operator.content.document.append_english')} /></button>
        </form>
      ) : null}
      {preview.english.payload.schema === 'global-english.daily-speaking-mission.v1' ? (
        <form onSubmit={(event) => {
          event.preventDefault()
          appendEnglish(dailyMissionPayload(mission))
        }}>
          <DailyMissionFields form={mission} message={message} onChange={setMission} />
          <button disabled={command.status === 'busy'} type="submit"><LocalizedMessage resolution={message('operator.content.document.append_english')} /></button>
        </form>
      ) : null}
      {translate && preview.english.payload.schema === 'global-english.governed-text.v1' ? (
        <form onSubmit={(event) => {
          event.preventDefault()
          finish(api.appendKannada(document.source_revision_id, {
            expected_head_variant_id: preview.kannada?.variant_id ?? null,
            expected_source_revision_number: document.source_revision_number,
            expected_variant_revision_number: preview.kannada?.revision_number ?? 0,
            payload: { schema: 'global-english.governed-text.v1', text: kannada },
            source_content_hash: document.source_content_hash,
          }, createCommandId()))
        }}>
          <ContentField label={message('operator.content.field.kannada_text').text}>
            <textarea lang="kn-IN" maxLength={8000} onChange={(event) => setKannada(event.target.value)} required rows={4} value={kannada} />
          </ContentField>
          <button disabled={command.status === 'busy'} type="submit"><LocalizedMessage resolution={message('operator.content.document.append_kannada')} /></button>
        </form>
      ) : null}
      {translate && preview.english.payload.schema === 'global-english.daily-speaking-mission.v1' ? (
        <form onSubmit={(event) => {
          event.preventDefault()
          finish(api.appendKannada(document.source_revision_id, {
            expected_head_variant_id: preview.kannada?.variant_id ?? null,
            expected_source_revision_number: document.source_revision_number,
            expected_variant_revision_number: preview.kannada?.revision_number ?? 0,
            payload: { schema: 'global-english.daily-speaking-mission.v1', guidance },
            source_content_hash: document.source_content_hash,
          }, createCommandId()))
        }}>
          <ContentField label={message('operator.content.field.kannada_guidance').text}>
            <textarea lang="kn-IN" maxLength={8000} onChange={(event) => setGuidance(event.target.value)} required rows={4} value={guidance} />
          </ContentField>
          <button disabled={command.status === 'busy'} type="submit"><LocalizedMessage resolution={message('operator.content.document.append_kannada')} /></button>
        </form>
      ) : null}
      <CommandFeedback command={command} message={message} />
    </div>
  )
}

function ReviewQueuePanel({ api, createCommandId, message }: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
}>) {
  const [queue, setQueue] = useState<OperatorContentQueue>('structure')
  const load = useCallback(
    (signal: AbortSignal) => api.listReviewQueue(queue, undefined, signal),
    [api, queue],
  )
  const resource = useResource(load)

  return (
    <section className="operator-content-studio__subpanel" aria-labelledby="operator-content-review-title">
      <h5 id="operator-content-review-title"><LocalizedMessage resolution={message('operator.content.review.title')} /></h5>
      <ContentField label={message('operator.content.review.queue').text}>
        <select onChange={(event) => setQueue(event.target.value as OperatorContentQueue)} value={queue}>
          <option value="structure">structure</option><option value="english">english</option>
          <option value="kannada">kannada</option><option value="readiness">readiness</option>
        </select>
      </ContentField>
      {resource.state.status === 'loading' ? <ContentLoading message={message} /> : null}
      {resource.state.status === 'error' ? <ContentFailure message={message} onRetry={resource.reload} /> : null}
      {resource.state.status === 'ready' ? (
        <ReviewQueueItems
          api={api}
          createCommandId={createCommandId}
          listing={resource.state.value}
          message={message}
          onSaved={resource.reload}
          queue={queue}
        />
      ) : null}
    </section>
  )
}

function ReviewQueueItems({
  api, createCommandId, listing, message, onSaved, queue,
}: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  listing: OperatorContentReviewQueueListing
  message: MessageResolver
  onSaved: () => void
  queue: OperatorContentQueue
}>) {
  if (listing.data.length === 0) return <p><LocalizedMessage resolution={message('operator.content.review.empty')} /></p>
  return (
    <ul className="operator-content-studio__queue-list">
      {listing.data.map((item) => {
        const id = 'graph_revision_id' in item ? item.graph_revision_id
          : 'source_revision_id' in item ? item.source_revision_id : item.curriculum_version_id
        return (
          <li key={id}>
            <code>{item.program_key}</code>
            {'document_key' in item ? <span>{item.document_key}</span> : null}
            {'latest_decision' in item ? <span>{item.latest_decision ?? 'pending'}</span> : null}
            <ReviewActions api={api} createCommandId={createCommandId} item={item} message={message} onSaved={onSaved} queue={queue} />
          </li>
        )
      })}
    </ul>
  )
}

function ReviewActions({ api, createCommandId, item, message, onSaved, queue }: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  item: OperatorContentReviewQueueListing['data'][number]
  message: MessageResolver
  onSaved: () => void
  queue: OperatorContentQueue
}>) {
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })
  const [confirmation, setConfirmation] = useState<'approved' | 'rejected' | null>(null)
  const run = (decision: 'approved' | 'rejected') => {
    let promise: Promise<unknown> | null = null
    if ('graph_revision_id' in item && item.allowed_actions.includes('review_structure')) {
      const structure = item as OperatorContentStructureQueueItem
      const appointment = structure.appointment_choices[0]
      if (appointment !== undefined) promise = api.reviewStructure({
        authority_appointment_id: appointment.id,
        curriculum_graph_revision_id: structure.graph_revision_id,
        decision,
        graph_hash: structure.graph_hash,
        rationale_key: decision === 'approved' ? 'meets_standard' : 'structure_revision_required',
      }, createCommandId())
    } else if ('source_revision_id' in item) {
      const source = item as OperatorContentSourceQueueItem
      const appointment = source.appointment_choices[0]
      if (appointment !== undefined && queue === 'english' && source.allowed_actions.includes('review_english')) {
        promise = api.reviewEnglish({
          authority_appointment_id: appointment.id, decision,
          rationale_key: decision === 'approved' ? 'meets_standard' : 'accuracy_revision_required',
          source_content_hash: source.source_content_hash, source_revision_id: source.source_revision_id,
        }, createCommandId())
      } else if (appointment !== undefined && queue === 'kannada' && source.allowed_actions.includes('review_kannada') && source.localized_variant_id !== null && source.localized_content_hash !== null) {
        promise = api.reviewKannada({
          authority_appointment_id: appointment.id, decision,
          localized_content_hash: source.localized_content_hash,
          localized_variant_id: source.localized_variant_id,
          rationale_key: decision === 'approved' ? 'meets_standard' : 'language_revision_required',
          source_content_hash: source.source_content_hash, source_revision_id: source.source_revision_id,
        }, createCommandId())
      }
    }
    if (promise === null) return
    setCommand({ status: 'busy' })
    void promise.then(() => { setConfirmation(null); setCommand({ status: 'success' }); onSaved() })
      .catch((error: unknown) => setCommand({ status: 'error', kind: errorKind(error) }))
  }
  const actions = 'allowed_actions' in item ? item.allowed_actions as readonly OperatorContentAction[] : []
  const reviewable = actions.some((action) => action.startsWith('review_'))
  return reviewable ? (
    <div className="operator-content-studio__review-actions">
      {confirmation === null ? (
        <>
          <button disabled={command.status === 'busy'} onClick={() => setConfirmation('approved')} type="button"><LocalizedMessage resolution={message('operator.content.review.approve')} /></button>
          <button disabled={command.status === 'busy'} onClick={() => setConfirmation('rejected')} type="button"><LocalizedMessage resolution={message('operator.content.review.reject')} /></button>
        </>
      ) : (
        <div className="operator-content-studio__confirmation" role="alert">
          <p>{message('operator.content.review.confirm').text} <code>{confirmation}</code></p>
          <button disabled={command.status === 'busy'} onClick={() => run(confirmation)} type="button">
            <LocalizedMessage resolution={message('operator.content.review.confirm_action')} />
          </button>
          <button onClick={() => setConfirmation(null)} type="button"><LocalizedMessage resolution={message('operator.content.cancel')} /></button>
        </div>
      )}
      <CommandFeedback command={command} message={message} />
    </div>
  ) : null
}

function ContentPublicationPanel({ api, createCommandId, message }: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
}>) {
  const load = useCallback((signal: AbortSignal) => api.listPublications(undefined, signal), [api])
  const resource = useResource(load)
  return (
    <section className="operator-content-studio__subpanel" aria-labelledby="operator-content-publications-title">
      <h5 id="operator-content-publications-title"><LocalizedMessage resolution={message('operator.content.publications.title')} /></h5>
      {resource.state.status === 'loading' ? <ContentLoading message={message} /> : null}
      {resource.state.status === 'error' ? <ContentFailure message={message} onRetry={resource.reload} /> : null}
      {resource.state.status === 'ready' ? (
        <PublicationItems api={api} createCommandId={createCommandId} listing={resource.state.value} message={message} onSaved={resource.reload} />
      ) : null}
    </section>
  )
}

function PublicationItems({ api, createCommandId, listing, message, onSaved }: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  listing: OperatorPublicationListing
  message: MessageResolver
  onSaved: () => void
}>) {
  if (listing.items.length === 0) return <p><LocalizedMessage resolution={message('operator.content.publications.empty')} /></p>
  return <ul className="operator-content-studio__queue-list">{listing.items.map((publication) => (
    <li key={publication.publication_id}>
      <code>{publication.program_key} / {publication.version_key}</code>
      <span>#{publication.publication_number} · {publication.lifecycle_status}</span>
      <PublicationActions api={api} createCommandId={createCommandId} message={message} onSaved={onSaved} publication={publication} />
    </li>
  ))}</ul>
}

function PublicationActions({ api, createCommandId, message, onSaved, publication }: Readonly<{
  api: OperatorContentStudioApi
  createCommandId: () => string
  message: MessageResolver
  onSaved: () => void
  publication: OperatorPublicationStatus
}>) {
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })
  const [confirmation, setConfirmation] = useState<OperatorContentAction | null>(null)
  const execute = (action: OperatorContentAction) => {
    const head = publication.publication_head_version
    const promise = action === 'activate'
      ? api.activatePublication({ publication_id: publication.publication_id, expected_head_version: head }, createCommandId())
      : action === 'cancel_schedule'
        ? api.cancelSchedule({ publication_id: publication.publication_id, expected_head_version: head, reason_key: 'release_withdrawn' }, createCommandId())
        : action === 'retire'
          ? api.retirePublication({ program_id: publication.program_id, expected_head_version: head, reason_key: 'release_no_longer_current' }, createCommandId())
          : action === 'rollback'
            ? api.rollbackPublication({
                expected_head_version: head,
                program_id: publication.program_id,
                reason_key: 'operator_console_rollback',
                target_publication_id: publication.publication_id,
              }, createCommandId())
            : null
    if (promise === null) return
    setCommand({ status: 'busy' })
    void promise.then(() => {
      setConfirmation(null)
      setCommand({ status: 'success' })
      onSaved()
    }).catch((error: unknown) => {
      const kind = errorKind(error)
      if (isConflict(kind)) onSaved()
      setCommand({ status: 'error', kind })
    })
  }
  return (
    <div className="operator-content-studio__actions">
      {confirmation === null
        ? publication.allowed_actions.map((action) => (
            <button disabled={command.status === 'busy'} key={action} onClick={() => setConfirmation(action)} type="button">{action}</button>
          ))
        : (
          <div className="operator-content-studio__confirmation" role="alert">
            <p>{message('operator.content.publication.confirm').text} <code>{confirmation}</code></p>
            <button disabled={command.status === 'busy'} onClick={() => execute(confirmation)} type="button">
              <LocalizedMessage resolution={message('operator.content.publication.confirm_action')} />
            </button>
            <button onClick={() => setConfirmation(null)} type="button">
              <LocalizedMessage resolution={message('operator.content.cancel')} />
            </button>
          </div>
        )}
      <CommandFeedback command={command} message={message} />
    </div>
  )
}

function WorkspaceCommandButton({ action, label, message, onConflict, onSaved }: Readonly<{
  action: () => Promise<unknown>
  label: string
  message: MessageResolver
  onConflict: () => void
  onSaved: () => void
}>) {
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })
  return <div>
    <button disabled={command.status === 'busy'} onClick={() => {
      setCommand({ status: 'busy' })
      void action().then(() => { setCommand({ status: 'success' }); onSaved() })
        .catch((error: unknown) => {
          const kind = errorKind(error)
          if (isConflict(kind)) onConflict()
          setCommand({ status: 'error', kind })
        })
    }} type="button">{label}</button>
    <CommandFeedback command={command} message={message} />
  </div>
}

function CommandFeedback({ command, message }: Readonly<{ command: CommandState; message: MessageResolver }>) {
  if (command.status === 'idle' || command.status === 'busy') return command.status === 'busy' ? (
    <p aria-live="polite"><LocalizedMessage resolution={message('operator.content.command.busy')} /></p>
  ) : null
  const key = command.status === 'success'
    ? 'operator.content.command.success'
    : isConflict(command.kind)
      ? 'operator.content.command.conflict'
      : command.kind === 'fresh_mfa'
        ? 'operator.content.command.mfa'
        : 'operator.content.command.error'
  return <p aria-live="assertive"><LocalizedMessage resolution={message(key)} /></p>
}

function ContentField({ children, label }: Readonly<{ children: ReactNode; label: string }>) {
  return <label className="operator-content-studio__field"><span>{label}</span>{children}</label>
}

function ContentLoading({ message }: Readonly<{ message: MessageResolver }>) {
  return <p aria-live="polite"><LocalizedMessage resolution={message('operator.content.loading')} /></p>
}

function ContentFailure({ message, onRetry }: Readonly<{ message: MessageResolver; onRetry: () => void }>) {
  return <div role="alert"><p><LocalizedMessage resolution={message('operator.content.error')} /></p><button onClick={onRetry} type="button"><LocalizedMessage resolution={message('operator.retry')} /></button></div>
}
