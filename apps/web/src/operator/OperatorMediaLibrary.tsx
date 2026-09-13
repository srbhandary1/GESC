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
  defaultOperatorMediaLibraryApi,
  OperatorMediaApiError,
  type AppendOperatorMediaKannadaRequest,
  type AttachOperatorMediaRequest,
  type DecideOperatorMediaRightsRequest,
  type OperatorMediaAction,
  type OperatorMediaAssetDetail,
  type OperatorMediaAssetKind,
  type OperatorMediaAssetState,
  type OperatorMediaAssetSummary,
  type OperatorMediaCommandResult,
  type OperatorMediaErrorKind,
  type OperatorMediaLibraryApi,
  type OperatorMediaManifest,
  type OperatorMediaMetadataReview,
  type OperatorMediaPage,
  type OperatorMediaRightsRevision,
  type OperatorMediaUploadDetail,
  type OperatorMediaUploadState,
  type OperatorMediaUploadSummary,
  type ReviewOperatorMediaMetadataRequest,
} from './OperatorMediaLibraryApi'

type MessageResolver = (key: MessageKey) => InterfaceMessageResolution

type ResourceState<T> =
  | { readonly status: 'loading' }
  | { readonly status: 'error' }
  | { readonly status: 'ready'; readonly value: T }

type CommandState =
  | { readonly status: 'idle' }
  | { readonly status: 'busy' }
  | { readonly status: 'success' }
  | { readonly status: 'error'; readonly kind: OperatorMediaErrorKind }

interface PendingUpload {
  readonly file: File
  readonly hash: string
  readonly startKey: string
  readonly partKeys: readonly string[]
  readonly finalizeKey: string
  readonly uploadId: string | null
  readonly nextPart: number
  readonly version: number
}

interface OperatorMediaLibraryProps {
  readonly api?: OperatorMediaLibraryApi
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
      setState({ status: 'loading' })
      setAttempt((value) => value + 1)
    },
    state,
  } as const
}

function usePagedResource<T>(
  load: (
    cursor: string | undefined,
    signal: AbortSignal,
  ) => Promise<OperatorMediaPage<T>>,
) {
  const [attempt, setAttempt] = useState(0)
  const [state, setState] = useState<ResourceState<OperatorMediaPage<T>>>({
    status: 'loading',
  })
  const [loadingMore, setLoadingMore] = useState(false)
  const [loadMoreFailed, setLoadMoreFailed] = useState(false)
  const nextController = useRef<AbortController | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    let active = true
    nextController.current?.abort()
    nextController.current = null
    void load(undefined, controller.signal)
      .then((value) => {
        if (active) {
          setState({ status: 'ready', value })
          setLoadingMore(false)
          setLoadMoreFailed(false)
        }
      })
      .catch(() => {
        if (active && !controller.signal.aborted) {
          setState({ status: 'error' })
          setLoadingMore(false)
          setLoadMoreFailed(false)
        }
      })
    return () => {
      active = false
      controller.abort()
    }
  }, [attempt, load])

  useEffect(() => () => nextController.current?.abort(), [])

  const loadMore = () => {
    if (
      state.status !== 'ready'
      || state.value.next_cursor === null
      || loadingMore
    ) return
    const cursor = state.value.next_cursor
    const controller = new AbortController()
    nextController.current?.abort()
    nextController.current = controller
    setLoadingMore(true)
    setLoadMoreFailed(false)
    void load(cursor, controller.signal)
      .then((page) => {
        if (controller.signal.aborted) return
        setState((current) => current.status === 'ready'
          ? {
              status: 'ready',
              value: {
                ...page,
                data: [...current.value.data, ...page.data],
              },
            }
          : current)
      })
      .catch(() => {
        if (!controller.signal.aborted) setLoadMoreFailed(true)
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoadingMore(false)
        if (nextController.current === controller) nextController.current = null
      })
  }

  return {
    loadMore,
    loadMoreFailed,
    loadingMore,
    reload: () => {
      setState({ status: 'loading' })
      setLoadingMore(false)
      setLoadMoreFailed(false)
      setAttempt((value) => value + 1)
    },
    state,
  } as const
}

function errorKind(error: unknown): OperatorMediaErrorKind {
  return error instanceof OperatorMediaApiError ? error.kind : 'unavailable'
}

function commandValue(result: OperatorMediaCommandResult, key: string): unknown {
  return result[key]
}

function commandString(result: OperatorMediaCommandResult, key: string): string {
  const value = commandValue(result, key)
  if (typeof value !== 'string' || value === '') {
    throw new OperatorMediaApiError('unavailable')
  }
  return value
}

function commandNumber(result: OperatorMediaCommandResult, key: string): number {
  const value = commandValue(result, key)
  if (typeof value !== 'number' || !Number.isInteger(value)) {
    throw new OperatorMediaApiError('unavailable')
  }
  return value
}

export function OperatorMediaLibrary({
  api = defaultOperatorMediaLibraryApi,
  createCommandId = createRandomClientId,
  locale,
  localizationPolicy,
}: OperatorMediaLibraryProps) {
  const message: MessageResolver = (key) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const [assetKind, setAssetKind] = useState<OperatorMediaAssetKind | ''>('')
  const [assetState, setAssetState] = useState<OperatorMediaAssetState | ''>('')
  const [uploadState, setUploadState] = useState<OperatorMediaUploadState | ''>('')
  const [selectedAsset, setSelectedAsset] = useState<string | null>(null)
  const [selectedUpload, setSelectedUpload] = useState<string | null>(null)
  const loadManifest = useCallback(
    (signal: AbortSignal) => api.getManifest(signal),
    [api],
  )
  const loadAssets = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listAssets(
        {
          ...(cursor === undefined ? {} : { cursor }),
          ...(assetKind === '' ? {} : { kind: assetKind }),
          ...(assetState === '' ? {} : { state: assetState }),
        },
        signal,
      ),
    [api, assetKind, assetState],
  )
  const loadUploads = useCallback(
    (cursor: string | undefined, signal: AbortSignal) =>
      api.listUploads(
        {
          ...(cursor === undefined ? {} : { cursor }),
          ...(uploadState === '' ? {} : { state: uploadState }),
        },
        signal,
      ),
    [api, uploadState],
  )
  const manifest = useResource(loadManifest)
  const assets = usePagedResource(loadAssets)
  const uploads = usePagedResource(loadUploads)
  const actions = manifest.state.status === 'ready'
    ? manifest.state.value.allowed_actions
    : []

  return (
    <article
      aria-labelledby="operator-media-library-title"
      className="operator-panel operator-panel--wide operator-media-library"
      data-media-library="private-governed"
    >
      <div className="operator-media-library__heading">
        <div>
          <h4 id="operator-media-library-title">
            <LocalizedMessage resolution={message('operator.media.title')} />
          </h4>
          <p><LocalizedMessage resolution={message('operator.media.lead')} /></p>
        </div>
        <span className="operator-media-library__private">
          <LocalizedMessage resolution={message('operator.media.private')} />
        </span>
      </div>

      <ResourceBoundary
        message={message}
        resource={manifest}
        retry={manifest.reload}
      >
        {(value) => (
          <MediaManifest message={message} value={value} />
        )}
      </ResourceBoundary>

      {manifest.state.status === 'ready' && actions.includes('start_upload') ? (
        <MediaUploader
          api={api}
          createCommandId={createCommandId}
          manifest={manifest.state.value}
          message={message}
          onChanged={uploads.reload}
        />
      ) : null}

      {actions.includes('adopt_asset') ? (
        <AssetAdoption
          api={api}
          createCommandId={createCommandId}
          message={message}
          onChanged={assets.reload}
        />
      ) : null}

      <div className="operator-media-library__directories">
        <section aria-labelledby="operator-media-assets-title">
          <h5 id="operator-media-assets-title">
            <LocalizedMessage resolution={message('operator.media.assets')} />
          </h5>
          <div className="operator-media-library__filters">
            <MediaField label={message('operator.media.field.kind').text}>
              <select
                onChange={(event) => setAssetKind(event.target.value as OperatorMediaAssetKind | '')}
                value={assetKind}
              >
                <option value="">{message('operator.media.filter.all').text}</option>
                <option value="model_audio">{message('operator.media.kind.audio').text}</option>
                <option value="lesson_audio">{message('operator.media.kind.lesson_audio').text}</option>
                <option value="lesson_video">{message('operator.media.kind.video').text}</option>
              </select>
            </MediaField>
            <MediaField label={message('operator.media.field.state').text}>
              <select
                onChange={(event) => setAssetState(event.target.value as OperatorMediaAssetState | '')}
                value={assetState}
              >
                <option value="">{message('operator.media.filter.all').text}</option>
                <option value="active">{message('operator.media.state.active').text}</option>
                <option value="retired">{message('operator.media.state.retired').text}</option>
              </select>
            </MediaField>
          </div>
          <ResourceBoundary message={message} resource={assets} retry={assets.reload}>
            {(value) => (
              <AssetDirectory
                message={message}
                onSelect={setSelectedAsset}
                onLoadMore={assets.loadMore}
                loadMoreFailed={assets.loadMoreFailed}
                loadingMore={assets.loadingMore}
                selected={selectedAsset}
                value={value}
              />
            )}
          </ResourceBoundary>
        </section>

        <section aria-labelledby="operator-media-uploads-title">
          <h5 id="operator-media-uploads-title">
            <LocalizedMessage resolution={message('operator.media.uploads')} />
          </h5>
          <MediaField label={message('operator.media.field.state').text}>
            <select
              onChange={(event) => setUploadState(event.target.value as OperatorMediaUploadState | '')}
              value={uploadState}
            >
              <option value="">{message('operator.media.filter.all').text}</option>
              {[
                'uploading',
                'finalized',
                'scan_clean',
                'accepted',
                'rejected',
                'expired',
                'inspection_failed',
                'scan_failed',
                'promotion_failed',
              ].map((state) => <option key={state} value={state}>{state}</option>)}
            </select>
          </MediaField>
          <ResourceBoundary message={message} resource={uploads} retry={uploads.reload}>
            {(value) => (
              <UploadDirectory
                message={message}
                onSelect={setSelectedUpload}
                onLoadMore={uploads.loadMore}
                loadMoreFailed={uploads.loadMoreFailed}
                loadingMore={uploads.loadingMore}
                selected={selectedUpload}
                value={value}
              />
            )}
          </ResourceBoundary>
        </section>
      </div>

      {selectedUpload === null ? null : (
        <UploadInspector
          actions={actions}
          api={api}
          createCommandId={createCommandId}
          key={selectedUpload}
          message={message}
          onChanged={() => {
            uploads.reload()
            assets.reload()
          }}
          uploadId={selectedUpload}
        />
      )}

      {selectedAsset === null ? null : (
        <AssetEditor
          actions={actions}
          api={api}
          createCommandId={createCommandId}
          key={selectedAsset}
          message={message}
          rightsAttestation={manifest.state.status === 'ready'
            ? manifest.state.value.rights_attestation
            : null}
          onChanged={() => {
            assets.reload()
            uploads.reload()
          }}
          assetId={selectedAsset}
        />
      )}
    </article>
  )
}

function ResourceBoundary<T>({
  children,
  message,
  resource,
  retry,
}: Readonly<{
  children: (value: T) => ReactNode
  message: MessageResolver
  resource: Readonly<{ state: ResourceState<T> }>
  retry: () => void
}>) {
  if (resource.state.status === 'loading') {
    return <p role="status"><LocalizedMessage resolution={message('operator.media.loading')} /></p>
  }
  if (resource.state.status === 'error') {
    return (
      <div className="operator-media-library__failure" role="alert">
        <p><LocalizedMessage resolution={message('operator.media.error')} /></p>
        <button onClick={retry} type="button"><LocalizedMessage resolution={message('operator.retry')} /></button>
      </div>
    )
  }
  return children(resource.state.value)
}

function MediaManifest({ message, value }: Readonly<{
  message: MessageResolver
  value: OperatorMediaManifest
}>) {
  return (
    <div className="operator-media-library__manifest">
      <span>{message('operator.media.limit.audio').text}: {formatBytes(value.limits.model_audio_maximum_bytes)}</span>
      <span>{message('operator.media.limit.lesson_audio').text}: {formatBytes(value.limits.lesson_audio_maximum_bytes)}</span>
      <span>{message('operator.media.limit.video').text}: {formatBytes(value.limits.lesson_video_maximum_bytes)}</span>
      <span>{message('operator.media.part_size').text}: {formatBytes(value.limits.part_size_bytes)}</span>
      <span>{message('operator.media.attestation').text}: <code>{value.rights_attestation.approval_reference}</code></span>
      <strong><LocalizedMessage resolution={message('operator.media.attestation.warning')} /></strong>
    </div>
  )
}

function AssetDirectory({ loadMoreFailed, loadingMore, message, onLoadMore, onSelect, selected, value }: Readonly<{
  loadMoreFailed: boolean
  loadingMore: boolean
  message: MessageResolver
  onLoadMore: () => void
  onSelect: (id: string) => void
  selected: string | null
  value: OperatorMediaPage<OperatorMediaAssetSummary>
}>) {
  if (value.data.length === 0) return <p><LocalizedMessage resolution={message('operator.media.assets.empty')} /></p>
  return (
    <>
      <ul className="operator-media-library__list">
        {value.data.map((asset) => (
        <li key={asset.media_asset_id}>
          <button
            aria-pressed={selected === asset.media_asset_id}
            onClick={() => onSelect(asset.media_asset_id)}
            type="button"
          >
            <strong><bdi>{asset.operator_label ?? message('operator.media.unlabelled').text}</bdi></strong>
            <span>{asset.asset_kind} · {asset.state} · {formatBytes(asset.byte_size)}</span>
            <code>{shortId(asset.media_asset_id)}</code>
          </button>
        </li>
        ))}
      </ul>
      <PageContinuation
        failed={loadMoreFailed}
        loading={loadingMore}
        message={message}
        nextCursor={value.next_cursor}
        onLoadMore={onLoadMore}
      />
    </>
  )
}

function UploadDirectory({ loadMoreFailed, loadingMore, message, onLoadMore, onSelect, selected, value }: Readonly<{
  loadMoreFailed: boolean
  loadingMore: boolean
  message: MessageResolver
  onLoadMore: () => void
  onSelect: (id: string) => void
  selected: string | null
  value: OperatorMediaPage<OperatorMediaUploadSummary>
}>) {
  if (value.data.length === 0) return <p><LocalizedMessage resolution={message('operator.media.uploads.empty')} /></p>
  return (
    <>
      <ul className="operator-media-library__list">
        {value.data.map((upload) => (
        <li key={upload.id}>
          <button
            aria-pressed={selected === upload.id}
            onClick={() => onSelect(upload.id)}
            type="button"
          >
            <strong><bdi>{upload.safe_label}</bdi></strong>
            <span>{upload.state} · {upload.received_part_count}/{upload.expected_part_count}</span>
            <code>{shortId(upload.id)}</code>
          </button>
        </li>
        ))}
      </ul>
      <PageContinuation
        failed={loadMoreFailed}
        loading={loadingMore}
        message={message}
        nextCursor={value.next_cursor}
        onLoadMore={onLoadMore}
      />
    </>
  )
}

function PageContinuation({ failed, loading, message, nextCursor, onLoadMore }: Readonly<{
  failed: boolean
  loading: boolean
  message: MessageResolver
  nextCursor: string | null
  onLoadMore: () => void
}>) {
  if (nextCursor === null) return null
  return (
    <div className="operator-media-library__continuation">
      <button disabled={loading} onClick={onLoadMore} type="button">
        <LocalizedMessage resolution={message(loading ? 'operator.media.loading' : 'operator.load_more')} />
      </button>
      {failed ? <p role="alert"><LocalizedMessage resolution={message('operator.media.error')} /></p> : null}
    </div>
  )
}

function AssetAdoption({ api, createCommandId, message, onChanged }: Readonly<{
  api: OperatorMediaLibraryApi
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
}>) {
  const [assetId, setAssetId] = useState('')
  const [assetHash, setAssetHash] = useState('')
  const [confirmed, setConfirmed] = useState(false)
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setCommand({ status: 'busy' })
    void api.adoptAsset(assetId, assetHash, createCommandId()).then(() => {
      setCommand({ status: 'success' })
      setConfirmed(false)
      setAssetId('')
      setAssetHash('')
      onChanged()
    }).catch((error: unknown) => {
      setCommand({ status: 'error', kind: errorKind(error) })
    })
  }
  return (
    <details className="operator-media-library__disclosure">
      <summary><LocalizedMessage resolution={message('operator.media.adoption.title')} /></summary>
      <form className="operator-media-library__form" onSubmit={submit}>
        <p><LocalizedMessage resolution={message('operator.media.adoption.lead')} /></p>
        <MediaField label={message('operator.media.adoption.asset').text}>
          <input onChange={(event) => setAssetId(event.target.value)} pattern="[0-9A-HJKMNP-TV-Z]{26}" required value={assetId} />
        </MediaField>
        <MediaField label={message('operator.media.field.hash').text}>
          <input maxLength={64} minLength={64} onChange={(event) => setAssetHash(event.target.value)} pattern="[0-9a-f]{64}" required value={assetHash} />
        </MediaField>
        <label className="operator-media-library__checkbox">
          <input checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} required type="checkbox" />
          <LocalizedMessage resolution={message('operator.media.adoption.confirm')} />
        </label>
        <button disabled={!confirmed || command.status === 'busy'} type="submit">
          <LocalizedMessage resolution={message('operator.media.adoption.record')} />
        </button>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function MediaUploader({ api, createCommandId, manifest, message, onChanged }: Readonly<{
  api: OperatorMediaLibraryApi
  createCommandId: () => string
  manifest: OperatorMediaManifest
  message: MessageResolver
  onChanged: () => void
}>) {
  const [kind, setKind] = useState<OperatorMediaAssetKind>('model_audio')
  const [label, setLabel] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [pending, setPending] = useState<PendingUpload | null>(null)
  const [progress, setProgress] = useState(0)
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })

  const continueUpload = async (current: PendingUpload) => {
    setCommand({ status: 'busy' })
    try {
      let uploadId = current.uploadId
      let version = current.version
      let nextPart = current.nextPart
      if (uploadId === null) {
        const started = await api.startUpload({
          asset_kind: kind,
          safe_label: label,
          total_bytes: current.file.size,
          whole_object_hash: current.hash,
        }, current.startKey)
        uploadId = commandString(started, 'id')
        version = commandNumber(started, 'version')
        setPending({ ...current, uploadId, version })
      }
      const partSize = manifest.limits.part_size_bytes
      const partCount = Math.ceil(current.file.size / partSize)
      while (nextPart <= partCount) {
        const bytes = current.file.slice(
          (nextPart - 1) * partSize,
          Math.min(nextPart * partSize, current.file.size),
        )
        const partHash = await sha256(bytes)
        const result = await api.uploadPart(
          uploadId,
          bytes,
          nextPart,
          partHash,
          version,
          current.partKeys[nextPart - 1] ?? createCommandId(),
        )
        version = commandNumber(result, 'upload_version')
        nextPart += 1
        setProgress(Math.round(((nextPart - 1) / partCount) * 100))
        setPending({ ...current, uploadId, version, nextPart })
      }
      await api.finalizeUpload(uploadId, version, current.finalizeKey)
      setCommand({ status: 'success' })
      setPending(null)
      setFile(null)
      setLabel('')
      setProgress(100)
      onChanged()
    } catch (error) {
      setCommand({ status: 'error', kind: errorKind(error) })
    }
  }

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (file === null) return
    const maximum = maximumBytesForKind(manifest, kind)
    if (file.size < 1 || file.size > maximum) {
      setCommand({ status: 'error', kind: 'validation' })
      return
    }
    setCommand({ status: 'busy' })
    void sha256(file).then((hash) => {
      const partCount = Math.ceil(file.size / manifest.limits.part_size_bytes)
      const next: PendingUpload = {
        file,
        finalizeKey: createCommandId(),
        hash,
        nextPart: 1,
        partKeys: Array.from({ length: partCount }, () => createCommandId()),
        startKey: createCommandId(),
        uploadId: null,
        version: 1,
      }
      setPending(next)
      return continueUpload(next)
    }).catch((error: unknown) => {
      setCommand({ status: 'error', kind: errorKind(error) })
    })
  }

  return (
    <details className="operator-media-library__disclosure">
      <summary><LocalizedMessage resolution={message('operator.media.upload.start')} /></summary>
      <form className="operator-media-library__form" onSubmit={submit}>
        <MediaField label={message('operator.media.field.kind').text}>
          <select disabled={pending !== null} onChange={(event) => setKind(event.target.value as OperatorMediaAssetKind)} value={kind}>
            <option value="model_audio">{message('operator.media.kind.audio').text}</option>
            <option value="lesson_audio">{message('operator.media.kind.lesson_audio').text}</option>
            <option value="lesson_video">{message('operator.media.kind.video').text}</option>
          </select>
        </MediaField>
        <MediaField label={message('operator.media.field.label').text}>
          <input disabled={pending !== null} maxLength={160} onChange={(event) => setLabel(event.target.value)} required value={label} />
        </MediaField>
        <MediaField label={message('operator.media.field.file').text}>
          <input
            accept={kind === 'lesson_video' ? 'video/mp4' : 'audio/*'}
            disabled={pending !== null}
            onChange={(event) => setFile(event.target.files?.item(0) ?? null)}
            required
            type="file"
          />
        </MediaField>
        <button disabled={command.status === 'busy' || pending !== null} type="submit">
          <LocalizedMessage resolution={message('operator.media.upload.start')} />
        </button>
        {pending === null ? null : (
          <button disabled={command.status === 'busy'} onClick={() => void continueUpload(pending)} type="button">
            <LocalizedMessage resolution={message('operator.media.upload.resume')} />
          </button>
        )}
        <progress aria-label={message('operator.media.upload.progress').text} max={100} value={progress}>{progress}%</progress>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function UploadInspector({ actions, api, createCommandId, message, onChanged, uploadId }: Readonly<{
  actions: readonly OperatorMediaAction[]
  api: OperatorMediaLibraryApi
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
  uploadId: string
}>) {
  const load = useCallback((signal: AbortSignal) => api.getUpload(uploadId, signal), [api, uploadId])
  const resource = useResource(load)
  return (
    <section aria-labelledby="operator-media-upload-detail-title" className="operator-media-library__detail">
      <h5 id="operator-media-upload-detail-title"><LocalizedMessage resolution={message('operator.media.upload.detail')} /></h5>
      <ResourceBoundary message={message} resource={resource} retry={resource.reload}>
        {(upload) => (
          <UploadDetail
            actions={actions}
            api={api}
            createCommandId={createCommandId}
            message={message}
            onChanged={() => { resource.reload(); onChanged() }}
            upload={upload}
          />
        )}
      </ResourceBoundary>
    </section>
  )
}

function UploadDetail({ actions, api, createCommandId, message, onChanged, upload }: Readonly<{
  actions: readonly OperatorMediaAction[]
  api: OperatorMediaLibraryApi
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
  upload: OperatorMediaUploadDetail
}>) {
  const [confirmed, setConfirmed] = useState(false)
  const [reason, setReason] = useState('operator_rejected')
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })
  const run = (action: 'accept' | 'reject') => {
    setCommand({ status: 'busy' })
    const promise = action === 'accept'
      ? api.acceptUpload(upload.id, upload.version, createCommandId())
      : api.rejectUpload(upload.id, upload.version, reason, createCommandId())
    void promise.then(() => {
      setCommand({ status: 'success' })
      setConfirmed(false)
      onChanged()
    }).catch((error: unknown) => setCommand({ status: 'error', kind: errorKind(error) }))
  }
  return (
    <div className="operator-media-library__detail-body">
      <dl className="operator-media-library__facts">
        <div><dt>{message('operator.media.field.label').text}</dt><dd><bdi>{upload.safe_label}</bdi></dd></div>
        <div><dt>{message('operator.media.field.state').text}</dt><dd>{upload.state}</dd></div>
        <div><dt>{message('operator.media.upload.parts').text}</dt><dd>{upload.received_part_count}/{upload.expected_part_count}</dd></div>
        <div><dt>{message('operator.media.field.hash').text}</dt><dd><code>{upload.whole_object_hash}</code></dd></div>
      </dl>
      {upload.state === 'scan_clean' && actions.includes('review_quarantine') ? (
        <>
          <PrivatePreview api={api} kind={upload.asset_kind} message={message} subjectId={upload.id} subject="upload" />
          <label className="operator-media-library__checkbox">
            <input checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} type="checkbox" />
            <LocalizedMessage resolution={message('operator.media.quarantine.confirm')} />
          </label>
          <MediaField label={message('operator.media.field.reason').text}>
            <input maxLength={100} onChange={(event) => setReason(event.target.value)} pattern="[a-z][a-z0-9_.-]*" value={reason} />
          </MediaField>
          <div className="operator-media-library__actions">
            <button disabled={!confirmed || command.status === 'busy'} onClick={() => run('accept')} type="button">
              <LocalizedMessage resolution={message('operator.media.quarantine.accept')} />
            </button>
            <button disabled={!confirmed || command.status === 'busy'} onClick={() => run('reject')} type="button">
              <LocalizedMessage resolution={message('operator.media.quarantine.reject')} />
            </button>
          </div>
          <CommandFeedback command={command} message={message} />
        </>
      ) : null}
      <HistoryList items={upload.events} message={message} />
    </div>
  )
}

function AssetEditor({ actions, api, assetId, createCommandId, message, onChanged, rightsAttestation }: Readonly<{
  actions: readonly OperatorMediaAction[]
  api: OperatorMediaLibraryApi
  assetId: string
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
  rightsAttestation: OperatorMediaManifest['rights_attestation'] | null
}>) {
  const load = useCallback((signal: AbortSignal) => api.getAsset(assetId, signal), [api, assetId])
  const resource = useResource(load)
  return (
    <section aria-labelledby="operator-media-asset-detail-title" className="operator-media-library__detail">
      <h5 id="operator-media-asset-detail-title"><LocalizedMessage resolution={message('operator.media.asset.detail')} /></h5>
      <ResourceBoundary message={message} resource={resource} retry={resource.reload}>
        {(asset) => (
          <AssetDetail
            actions={actions}
            api={api}
            asset={asset}
            createCommandId={createCommandId}
            message={message}
            onChanged={() => { resource.reload(); onChanged() }}
            rightsAttestation={rightsAttestation}
          />
        )}
      </ResourceBoundary>
    </section>
  )
}

function AssetDetail({ actions, api, asset, createCommandId, message, onChanged, rightsAttestation }: Readonly<{
  actions: readonly OperatorMediaAction[]
  api: OperatorMediaLibraryApi
  asset: OperatorMediaAssetDetail
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
  rightsAttestation: OperatorMediaManifest['rights_attestation'] | null
}>) {
  return (
    <div className="operator-media-library__detail-body">
      <dl className="operator-media-library__facts">
        <div><dt>{message('operator.media.field.state').text}</dt><dd>{asset.lifecycle.state} · v{asset.lifecycle.version}</dd></div>
        <div><dt>{message('operator.media.field.kind').text}</dt><dd>{asset.asset_kind}</dd></div>
        <div><dt>{message('operator.media.field.size').text}</dt><dd>{formatBytes(asset.byte_size)}</dd></div>
        <div><dt>{message('operator.media.field.hash').text}</dt><dd><code>{asset.asset_hash}</code></dd></div>
      </dl>
      <PrivatePreview api={api} kind={asset.asset_kind} message={message} subjectId={asset.media_asset_id} subject="asset" />
      {actions.includes('manage_metadata') && asset.lifecycle.state === 'active' ? (
        <AccessibilityEditor actions={actions} api={api} asset={asset} createCommandId={createCommandId} message={message} onChanged={onChanged} />
      ) : null}
      <GovernanceControls actions={actions} api={api} asset={asset} createCommandId={createCommandId} message={message} onChanged={onChanged} rightsAttestation={rightsAttestation} />
      <EvidenceSummary asset={asset} message={message} />
    </div>
  )
}

function AccessibilityEditor({ actions, api, asset, createCommandId, message, onChanged }: Readonly<{
  actions: readonly OperatorMediaAction[]
  api: OperatorMediaLibraryApi
  asset: OperatorMediaAssetDetail
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
}>) {
  const [label, setLabel] = useState(asset.metadata?.operator_label ?? '')
  const [transcript, setTranscript] = useState(asset.metadata?.transcript ?? '')
  const [alternative, setAlternative] = useState(asset.metadata?.text_alternative ?? '')
  const [knTranscript, setKnTranscript] = useState(asset.kannada_variant?.transcript ?? '')
  const [knAlternative, setKnAlternative] = useState(asset.kannada_variant?.text_alternative ?? '')
  const [englishCommand, setEnglishCommand] = useState<CommandState>({ status: 'idle' })
  const [kannadaCommand, setKannadaCommand] = useState<CommandState>({ status: 'idle' })
  const saveEnglish = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setEnglishCommand({ status: 'busy' })
    void api.appendMetadata(asset.media_asset_id, {
      expected_head_revision_id: asset.metadata?.id ?? null,
      expected_metadata_version: asset.metadata?.metadata_version ?? 0,
      operator_label: label,
      text_alternative: asset.asset_kind === 'lesson_video' ? alternative : null,
      transcript,
    }, createCommandId()).then(() => {
      setEnglishCommand({ status: 'success' })
      onChanged()
    }).catch((error: unknown) => setEnglishCommand({ status: 'error', kind: errorKind(error) }))
  }
  const saveKannada = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (asset.metadata === null) return
    setKannadaCommand({ status: 'busy' })
    const body: AppendOperatorMediaKannadaRequest = {
      expected_head_variant_id: asset.kannada_variant?.id ?? null,
      expected_variant_version: asset.kannada_variant?.variant_version ?? 0,
      source_content_hash: asset.metadata.content_hash,
      text_alternative: asset.asset_kind === 'lesson_video' ? knAlternative : null,
      transcript: knTranscript,
    }
    void api.appendKannada(asset.metadata.id, body, createCommandId()).then(() => {
      setKannadaCommand({ status: 'success' })
      onChanged()
    }).catch((error: unknown) => setKannadaCommand({ status: 'error', kind: errorKind(error) }))
  }
  return (
    <div className="operator-media-library__editor-columns">
      <form className="operator-media-library__form" onSubmit={saveEnglish}>
        <h6><LocalizedMessage resolution={message('operator.media.metadata.english')} /></h6>
        <MediaField label={message('operator.media.field.label').text}><input maxLength={160} onChange={(event) => setLabel(event.target.value)} required value={label} /></MediaField>
        <MediaField label={message('operator.media.field.transcript').text}><textarea maxLength={65536} onChange={(event) => setTranscript(event.target.value)} required value={transcript} /></MediaField>
        {asset.asset_kind === 'lesson_video' ? <MediaField label={message('operator.media.field.alternative').text}><textarea maxLength={8000} onChange={(event) => setAlternative(event.target.value)} required value={alternative} /></MediaField> : null}
        <button disabled={englishCommand.status === 'busy'} type="submit"><LocalizedMessage resolution={message('operator.media.metadata.save')} /></button>
        <CommandFeedback command={englishCommand} message={message} />
      </form>
      {asset.metadata === null || !actions.includes('translate_metadata') ? null : (
        <form className="operator-media-library__form" onSubmit={saveKannada}>
          <h6><LocalizedMessage resolution={message('operator.media.metadata.kannada')} /></h6>
          <MediaField label={message('operator.media.field.transcript').text}><textarea maxLength={65536} onChange={(event) => setKnTranscript(event.target.value)} required value={knTranscript} /></MediaField>
          {asset.asset_kind === 'lesson_video' ? <MediaField label={message('operator.media.field.alternative').text}><textarea maxLength={8000} onChange={(event) => setKnAlternative(event.target.value)} required value={knAlternative} /></MediaField> : null}
          <button disabled={kannadaCommand.status === 'busy'} type="submit"><LocalizedMessage resolution={message('operator.media.metadata.save')} /></button>
          <CommandFeedback command={kannadaCommand} message={message} />
        </form>
      )}
    </div>
  )
}

function GovernanceControls({ actions, api, asset, createCommandId, message, onChanged, rightsAttestation }: Readonly<{
  actions: readonly OperatorMediaAction[]
  api: OperatorMediaLibraryApi
  asset: OperatorMediaAssetDetail
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
  rightsAttestation: OperatorMediaManifest['rights_attestation'] | null
}>) {
  return (
    <div className="operator-media-library__governance">
      {asset.metadata === null || (!actions.includes('review_metadata_en') && !actions.includes('review_metadata_kn')) ? null : <MetadataReviewForm actions={actions} api={api} asset={asset} createCommandId={createCommandId} message={message} onChanged={onChanged} />}
      {actions.includes('record_rights_decision') && rightsAttestation !== null ? <RightsForm api={api} asset={asset} createCommandId={createCommandId} message={message} onChanged={onChanged} rightsAttestation={rightsAttestation} /> : null}
      {actions.includes('attach_asset') && asset.asset_kind !== 'lesson_audio' && asset.metadata !== null && asset.kannada_variant !== null ? <AttachmentForm api={api} asset={asset} createCommandId={createCommandId} message={message} onChanged={onChanged} /> : null}
      {actions.includes('record_replacement') && asset.lifecycle.state === 'active' ? <ReplacementForm api={api} asset={asset} createCommandId={createCommandId} message={message} onChanged={onChanged} /> : null}
      {actions.includes('retire_asset') && asset.lifecycle.state === 'active' ? <RetirementForm api={api} asset={asset} createCommandId={createCommandId} message={message} onChanged={onChanged} /> : null}
    </div>
  )
}

function MetadataReviewForm({ actions, api, asset, createCommandId, message, onChanged }: Readonly<{
  actions: readonly OperatorMediaAction[]
  api: OperatorMediaLibraryApi
  asset: OperatorMediaAssetDetail
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
}>) {
  const [program, setProgram] = useState('')
  const [appointment, setAppointment] = useState('')
  const [kind, setKind] = useState<'academic_en' | 'kannada'>(
    actions.includes('review_metadata_en') ? 'academic_en' : 'kannada',
  )
  const [decision, setDecision] = useState<'approved' | 'rejected' | 'withdrawn'>('approved')
  const [rationale, setRationale] = useState('')
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (asset.metadata === null || (kind === 'kannada' && asset.kannada_variant === null)) return
    const body: ReviewOperatorMediaMetadataRequest = {
      authority_appointment_id: appointment,
      decision,
      metadata_revision_id: asset.metadata.id,
      metadata_variant_id: kind === 'kannada' ? asset.kannada_variant?.id ?? null : null,
      program_id: program,
      rationale_key: decision === 'approved' ? null : rationale,
      review_kind: kind,
      source_content_hash: asset.metadata.content_hash,
      variant_content_hash: kind === 'kannada' ? asset.kannada_variant?.content_hash ?? null : null,
    }
    setCommand({ status: 'busy' })
    void api.reviewMetadata(body, createCommandId()).then(() => {
      setCommand({ status: 'success' }); onChanged()
    }).catch((error: unknown) => setCommand({ status: 'error', kind: errorKind(error) }))
  }
  return (
    <details className="operator-media-library__disclosure">
      <summary><LocalizedMessage resolution={message('operator.media.review.title')} /></summary>
      <form className="operator-media-library__form" onSubmit={submit}>
        <MediaField label={message('operator.media.field.program').text}><input onChange={(event) => setProgram(event.target.value)} pattern="[0-9A-HJKMNP-TV-Z]{26}" required value={program} /></MediaField>
        <MediaField label={message('operator.media.field.appointment').text}><input onChange={(event) => setAppointment(event.target.value)} pattern="[0-9A-HJKMNP-TV-Z]{26}" required value={appointment} /></MediaField>
        <MediaField label={message('operator.media.field.review_kind').text}><select onChange={(event) => setKind(event.target.value as 'academic_en' | 'kannada')} value={kind}>{actions.includes('review_metadata_en') ? <option value="academic_en">academic_en</option> : null}{actions.includes('review_metadata_kn') ? <option disabled={asset.kannada_variant === null} value="kannada">kannada</option> : null}</select></MediaField>
        <MediaField label={message('operator.media.field.decision').text}><select onChange={(event) => setDecision(event.target.value as typeof decision)} value={decision}><option value="approved">approved</option><option value="rejected">rejected</option><option value="withdrawn">withdrawn</option></select></MediaField>
        {decision === 'approved' ? null : <MediaField label={message('operator.media.field.reason').text}><input onChange={(event) => setRationale(event.target.value)} pattern="[a-z][a-z0-9_.-]*" required value={rationale} /></MediaField>}
        <button disabled={command.status === 'busy'} type="submit"><LocalizedMessage resolution={message('operator.media.review.record')} /></button>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function RightsForm({ api, asset, createCommandId, message, onChanged, rightsAttestation }: Readonly<{
  api: OperatorMediaLibraryApi
  asset: OperatorMediaAssetDetail
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
  rightsAttestation: OperatorMediaManifest['rights_attestation']
}>) {
  const [program, setProgram] = useState('')
  const [appointment, setAppointment] = useState('')
  const [holder, setHolder] = useState('project-owner')
  const [provenance, setProvenance] = useState(rightsAttestation.approval_reference)
  const [license, setLicense] = useState(rightsAttestation.approval_reference)
  const [sharing, setSharing] = useState(false)
  const [derivative, setDerivative] = useState(false)
  const [confirmed, setConfirmed] = useState(false)
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const body: DecideOperatorMediaRightsRequest = {
      action: 'confirm',
      authority_appointment_id: appointment,
      deletion_constraint_key: 'preserve_while_referenced',
      derivative_use_allowed: derivative,
      effective_at: new Date().toISOString().replace(/\.\d{3}Z$/u, '+00:00'),
      evidence_hash: rightsAttestation.evidence_sha256,
      expires_at: null,
      learner_delivery_allowed: true,
      license_or_contract_reference: license,
      media_asset_id: asset.media_asset_id,
      parent_revision_id: null,
      permitted_release_channels: ['android', 'web'],
      permitted_territories: ['IN'],
      program_id: program,
      provenance_reference: provenance,
      retention_policy_key: 'accepted_media',
      rights_basis_key: 'owner_attestation',
      rights_holder_reference: holder,
      selected_asset_hash: asset.asset_hash,
      sharing_allowed: sharing,
    }
    setCommand({ status: 'busy' })
    void api.decideRights(body, createCommandId()).then(() => {
      setCommand({ status: 'success' }); setConfirmed(false); onChanged()
    }).catch((error: unknown) => setCommand({ status: 'error', kind: errorKind(error) }))
  }
  return (
    <details className="operator-media-library__disclosure">
      <summary><LocalizedMessage resolution={message('operator.media.rights.title')} /></summary>
      <form className="operator-media-library__form" onSubmit={submit}>
        <p><LocalizedMessage resolution={message('operator.media.attestation.warning')} /></p>
        <MediaField label={message('operator.media.field.program').text}><input onChange={(event) => setProgram(event.target.value)} pattern="[0-9A-HJKMNP-TV-Z]{26}" required value={program} /></MediaField>
        <MediaField label={message('operator.media.field.appointment').text}><input onChange={(event) => setAppointment(event.target.value)} pattern="[0-9A-HJKMNP-TV-Z]{26}" required value={appointment} /></MediaField>
        <MediaField label={message('operator.media.rights.holder').text}><input maxLength={120} onChange={(event) => setHolder(event.target.value)} required value={holder} /></MediaField>
        <MediaField label={message('operator.media.rights.provenance').text}><input maxLength={120} onChange={(event) => setProvenance(event.target.value)} required value={provenance} /></MediaField>
        <MediaField label={message('operator.media.rights.license').text}><input maxLength={240} onChange={(event) => setLicense(event.target.value)} required value={license} /></MediaField>
        <label className="operator-media-library__checkbox"><input checked={sharing} onChange={(event) => setSharing(event.target.checked)} type="checkbox" /><LocalizedMessage resolution={message('operator.media.rights.sharing')} /></label>
        <label className="operator-media-library__checkbox"><input checked={derivative} onChange={(event) => setDerivative(event.target.checked)} type="checkbox" /><LocalizedMessage resolution={message('operator.media.rights.derivative')} /></label>
        <label className="operator-media-library__checkbox"><input checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} required type="checkbox" /><LocalizedMessage resolution={message('operator.media.rights.confirm')} /></label>
        <button disabled={!confirmed || command.status === 'busy'} type="submit"><LocalizedMessage resolution={message('operator.media.rights.record')} /></button>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function AttachmentForm({ api, asset, createCommandId, message, onChanged }: Readonly<{
  api: OperatorMediaLibraryApi
  asset: OperatorMediaAssetDetail
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
}>) {
  const [program, setProgram] = useState('')
  const [version, setVersion] = useState('')
  const [source, setSource] = useState('')
  const [workspaceVersion, setWorkspaceVersion] = useState(1)
  const [englishReview, setEnglishReview] = useState('')
  const [kannadaReview, setKannadaReview] = useState('')
  const [rights, setRights] = useState('')
  const [confirmed, setConfirmed] = useState(false)
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (
      asset.asset_kind === 'lesson_audio'
      || asset.metadata === null
      || asset.kannada_variant === null
    ) return
    const body: AttachOperatorMediaRequest = {
      curriculum_version_id: version,
      english_review_id: englishReview,
      expected_lifecycle_version: asset.lifecycle.version,
      expected_workspace_version: workspaceVersion,
      kannada_review_id: kannadaReview,
      media_rights_revision_id: rights,
      metadata_content_hash: asset.metadata.content_hash,
      metadata_revision_id: asset.metadata.id,
      metadata_variant_id: asset.kannada_variant.id,
      program_id: program,
      source_revision_id: source,
      usage_key: asset.asset_kind,
      variant_content_hash: asset.kannada_variant.content_hash,
    }
    setCommand({ status: 'busy' })
    void api.attach(asset.media_asset_id, body, createCommandId()).then(() => {
      setCommand({ status: 'success' }); setConfirmed(false); onChanged()
    }).catch((error: unknown) => setCommand({ status: 'error', kind: errorKind(error) }))
  }
  return (
    <details className="operator-media-library__disclosure">
      <summary><LocalizedMessage resolution={message('operator.media.attachment.title')} /></summary>
      <form className="operator-media-library__form" onSubmit={submit}>
        <MediaField label={message('operator.media.field.program').text}><input onChange={(event) => setProgram(event.target.value)} required value={program} /></MediaField>
        <MediaField label={message('operator.media.attachment.version').text}><input onChange={(event) => setVersion(event.target.value)} required value={version} /></MediaField>
        <MediaField label={message('operator.media.attachment.source').text}><input onChange={(event) => setSource(event.target.value)} required value={source} /></MediaField>
        <MediaField label={message('operator.media.attachment.workspace_version').text}><input min={1} onChange={(event) => setWorkspaceVersion(event.target.valueAsNumber)} required type="number" value={workspaceVersion} /></MediaField>
        <EvidenceSelect items={asset.metadata_reviews.filter((review) => review.review_kind === 'academic_en' && review.decision === 'approved')} label={message('operator.media.attachment.english_review').text} onChange={setEnglishReview} value={englishReview} />
        <EvidenceSelect items={asset.metadata_reviews.filter((review) => review.review_kind === 'kannada' && review.decision === 'approved')} label={message('operator.media.attachment.kannada_review').text} onChange={setKannadaReview} value={kannadaReview} />
        <RightsSelect items={asset.rights_revisions.filter((item) => item.evidence_status === 'approved')} label={message('operator.media.attachment.rights').text} onChange={setRights} value={rights} />
        <label className="operator-media-library__checkbox"><input checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} required type="checkbox" /><LocalizedMessage resolution={message('operator.media.attachment.confirm')} /></label>
        <button disabled={!confirmed || command.status === 'busy'} type="submit"><LocalizedMessage resolution={message('operator.media.attachment.record')} /></button>
        <CommandFeedback command={command} message={message} />
      </form>
    </details>
  )
}

function EvidenceSelect({ items, label, onChange, value }: Readonly<{
  items: readonly OperatorMediaMetadataReview[]
  label: string
  onChange: (value: string) => void
  value: string
}>) {
  return <MediaField label={label}><select onChange={(event) => onChange(event.target.value)} required value={value}><option value="">—</option>{items.map((item) => <option key={item.id} value={item.id}>{shortId(item.id)} · {shortId(item.program_id)}</option>)}</select></MediaField>
}

function RightsSelect({ items, label, onChange, value }: Readonly<{
  items: readonly OperatorMediaRightsRevision[]
  label: string
  onChange: (value: string) => void
  value: string
}>) {
  return <MediaField label={label}><select onChange={(event) => onChange(event.target.value)} required value={value}><option value="">—</option>{items.map((item) => <option key={item.id} value={item.id}>{shortId(item.id)} · {shortId(item.program_id)}</option>)}</select></MediaField>
}

function ReplacementForm({ api, asset, createCommandId, message, onChanged }: Readonly<{
  api: OperatorMediaLibraryApi
  asset: OperatorMediaAssetDetail
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
}>) {
  const [successor, setSuccessor] = useState('')
  const [successorVersion, setSuccessorVersion] = useState(1)
  const [confirmed, setConfirmed] = useState(false)
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); setCommand({ status: 'busy' })
    void api.replace(asset.media_asset_id, successor, asset.lifecycle.version, successorVersion, createCommandId()).then(() => {
      setCommand({ status: 'success' }); setConfirmed(false); onChanged()
    }).catch((error: unknown) => setCommand({ status: 'error', kind: errorKind(error) }))
  }
  return <details className="operator-media-library__disclosure"><summary><LocalizedMessage resolution={message('operator.media.replacement.title')} /></summary><form className="operator-media-library__form" onSubmit={submit}><MediaField label={message('operator.media.replacement.successor').text}><input onChange={(event) => setSuccessor(event.target.value)} pattern="[0-9A-HJKMNP-TV-Z]{26}" required value={successor} /></MediaField><MediaField label={message('operator.media.replacement.version').text}><input min={1} onChange={(event) => setSuccessorVersion(event.target.valueAsNumber)} required type="number" value={successorVersion} /></MediaField><label className="operator-media-library__checkbox"><input checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} required type="checkbox" /><LocalizedMessage resolution={message('operator.media.replacement.confirm')} /></label><button disabled={!confirmed || command.status === 'busy'} type="submit"><LocalizedMessage resolution={message('operator.media.replacement.record')} /></button><CommandFeedback command={command} message={message} /></form></details>
}

function RetirementForm({ api, asset, createCommandId, message, onChanged }: Readonly<{
  api: OperatorMediaLibraryApi
  asset: OperatorMediaAssetDetail
  createCommandId: () => string
  message: MessageResolver
  onChanged: () => void
}>) {
  const [reason, setReason] = useState('superseded')
  const [confirmed, setConfirmed] = useState(false)
  const [command, setCommand] = useState<CommandState>({ status: 'idle' })
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault(); setCommand({ status: 'busy' })
    void api.retire(asset.media_asset_id, asset.lifecycle.version, reason, createCommandId()).then(() => {
      setCommand({ status: 'success' }); setConfirmed(false); onChanged()
    }).catch((error: unknown) => setCommand({ status: 'error', kind: errorKind(error) }))
  }
  return <details className="operator-media-library__disclosure"><summary><LocalizedMessage resolution={message('operator.media.retirement.title')} /></summary><form className="operator-media-library__form" onSubmit={submit}><MediaField label={message('operator.media.field.reason').text}><input maxLength={100} onChange={(event) => setReason(event.target.value)} pattern="[a-z][a-z0-9_.-]*" required value={reason} /></MediaField><label className="operator-media-library__checkbox"><input checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} required type="checkbox" /><LocalizedMessage resolution={message('operator.media.retirement.confirm')} /></label><button disabled={!confirmed || command.status === 'busy'} type="submit"><LocalizedMessage resolution={message('operator.media.retirement.record')} /></button><CommandFeedback command={command} message={message} /></form></details>
}

function EvidenceSummary({ asset, message }: Readonly<{
  asset: OperatorMediaAssetDetail
  message: MessageResolver
}>) {
  return (
    <div className="operator-media-library__evidence">
      <section><h6><LocalizedMessage resolution={message('operator.media.evidence.reviews')} /></h6><HistoryList items={asset.metadata_reviews} message={message} /></section>
      <section><h6><LocalizedMessage resolution={message('operator.media.evidence.rights')} /></h6><HistoryList items={asset.rights_revisions} message={message} /></section>
      <section><h6><LocalizedMessage resolution={message('operator.media.evidence.usage')} /></h6><HistoryList items={asset.usages} message={message} /></section>
      <section><h6><LocalizedMessage resolution={message('operator.media.evidence.history')} /></h6><HistoryList items={asset.events} message={message} /></section>
    </div>
  )
}

function PrivatePreview({ api, kind, message, subject, subjectId }: Readonly<{
  api: OperatorMediaLibraryApi
  kind: OperatorMediaAssetKind
  message: MessageResolver
  subject: 'asset' | 'upload'
  subjectId: string
}>) {
  const [url, setUrl] = useState<string | null>(null)
  const [state, setState] = useState<'idle' | 'busy' | 'error'>('idle')
  const request = useRef<AbortController | null>(null)
  useEffect(() => () => request.current?.abort(), [])
  useEffect(() => () => {
    if (url !== null) URL.revokeObjectURL(url)
  }, [url])
  const load = () => {
    setState('busy')
    request.current?.abort()
    const controller = new AbortController()
    request.current = controller
    const preview = subject === 'asset'
      ? api.previewAsset(subjectId, controller.signal)
      : api.previewUpload(subjectId, controller.signal)
    void preview.then((value) => {
      if (controller.signal.aborted) return
      setUrl((current) => {
        if (current !== null) URL.revokeObjectURL(current)
        return URL.createObjectURL(value.blob)
      })
      setState('idle')
    }).catch(() => {
      if (!controller.signal.aborted) setState('error')
    }).finally(() => {
      if (request.current === controller) request.current = null
    })
  }
  return (
    <div className="operator-media-library__preview">
      <button disabled={state === 'busy'} onClick={load} type="button"><LocalizedMessage resolution={message('operator.media.preview.load')} /></button>
      {state === 'error' ? <p role="alert"><LocalizedMessage resolution={message('operator.media.preview.error')} /></p> : null}
      {url === null ? null : kind === 'lesson_video' ? <video controls playsInline preload="metadata" src={url}><LocalizedMessage resolution={message('operator.media.preview.unsupported')} /></video> : <audio controls src={url}><LocalizedMessage resolution={message('operator.media.preview.unsupported')} /></audio>}
    </div>
  )
}

function HistoryList({ items, message }: Readonly<{
  items: readonly object[]
  message: MessageResolver
}>) {
  if (items.length === 0) return <p><LocalizedMessage resolution={message('operator.media.evidence.empty')} /></p>
  return (
    <ul className="operator-media-library__history">
      {items.map((item, index) => {
        const row = item as Readonly<Record<string, unknown>>
        const id = typeof row.id === 'string' ? row.id : String(index)
        const label = ['event_type', 'review_kind', 'evidence_status', 'usage_key']
          .map((key) => row[key])
          .find((value) => typeof value === 'string')
        const state = ['decision', 'state', 'reason_key']
          .map((key) => row[key])
          .find((value) => typeof value === 'string')
        return <li key={id}><span>{typeof label === 'string' ? label : message('operator.media.evidence.record').text}</span>{typeof state === 'string' ? <span>{state}</span> : null}<code>{shortId(id)}</code></li>
      })}
    </ul>
  )
}

function MediaField({ children, label }: Readonly<{
  children: ReactNode
  label: string
}>) {
  return <label className="operator-media-library__field"><span>{label}</span>{children}</label>
}

function CommandFeedback({ command, message }: Readonly<{
  command: CommandState
  message: MessageResolver
}>) {
  if (command.status === 'idle') return null
  if (command.status === 'busy') return <p role="status"><LocalizedMessage resolution={message('operator.media.command.busy')} /></p>
  if (command.status === 'success') return <p role="status"><LocalizedMessage resolution={message('operator.media.command.success')} /></p>
  const conflict = ['concurrent_change', 'duplicate_use', 'stale_lifecycle', 'stale_metadata', 'stale_upload', 'stale_workspace'].includes(command.kind)
  return <p role="alert"><LocalizedMessage resolution={message(conflict ? 'operator.media.command.conflict' : 'operator.media.command.error')} /></p>
}

function shortId(value: string): string {
  return value.length > 10 ? `${value.slice(0, 6)}…${value.slice(-4)}` : value
}

function maximumBytesForKind(
  manifest: OperatorMediaManifest,
  kind: OperatorMediaAssetKind,
): number {
  switch (kind) {
    case 'model_audio':
      return manifest.limits.model_audio_maximum_bytes
    case 'lesson_audio':
      return manifest.limits.lesson_audio_maximum_bytes
    case 'lesson_video':
      return manifest.limits.lesson_video_maximum_bytes
  }
}

function formatBytes(value: number): string {
  if (value < 1_000) return `${String(value)} B`
  if (value < 1_000_000) return `${(value / 1_000).toFixed(1)} kB`
  return `${(value / 1_000_000).toFixed(1)} MB`
}

async function sha256(blob: Blob): Promise<string> {
  const digest = await crypto.subtle.digest('SHA-256', await blob.arrayBuffer())
  return [...new Uint8Array(digest)]
    .map((byte) => byte.toString(16).padStart(2, '0'))
    .join('')
}
