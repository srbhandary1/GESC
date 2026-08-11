import {
  type ReactNode,
  useEffect,
  useId,
  useRef,
  useState,
} from 'react'

export interface LessonVideoPlayerProps {
  readonly byteSize: number
  readonly errorLabel: ReactNode
  readonly label: string
  readonly loadingLabel: ReactNode
  readonly loadSource?: (
    source: string,
    expectedBytes: number,
    signal?: AbortSignal,
  ) => Promise<Blob>
  readonly mimeType: string
  readonly retryLabel: ReactNode
  readonly source: string
}

export function LessonVideoPlayer(props: LessonVideoPlayerProps) {
  const titleId = useId()
  const [attempt, setAttempt] = useState(0)

  return (
    <LessonVideoAttempt
      {...props}
      key={`${props.source}\0${props.mimeType}\0${props.byteSize}\0${attempt}`}
      onRetry={() => setAttempt((current) => current + 1)}
      titleId={titleId}
    />
  )
}

type LoadedSourceState =
  | {
      readonly byteSize: number
      readonly kind: 'error'
      readonly loader: NonNullable<LessonVideoPlayerProps['loadSource']>
      readonly source: string
    }
  | {
      readonly byteSize: number
      readonly kind: 'ready'
      readonly loader: NonNullable<LessonVideoPlayerProps['loadSource']>
      readonly objectUrl: string
      readonly source: string
    }

interface LessonVideoAttemptProps extends LessonVideoPlayerProps {
  readonly onRetry: () => void
  readonly titleId: string
}

function LessonVideoAttempt({
  byteSize,
  errorLabel,
  label,
  loadingLabel,
  loadSource,
  mimeType,
  onRetry,
  retryLabel,
  source,
  titleId,
}: LessonVideoAttemptProps) {
  const media = useRef<HTMLVideoElement | null>(null)
  const objectUrl = useRef<string | null>(null)
  const [directError, setDirectError] = useState(false)
  const [loadedSource, setLoadedSource] =
    useState<LoadedSourceState | null>(null)

  useEffect(() => {
    if (loadSource === undefined) {
      return
    }

    const controller = new AbortController()
    let createdObjectUrl: string | null = null

    void loadSource(source, byteSize, controller.signal)
      .then((video) => {
        if (controller.signal.aborted) {
          return
        }

        createdObjectUrl = URL.createObjectURL(video)
        objectUrl.current = createdObjectUrl
        setLoadedSource({
          byteSize,
          kind: 'ready',
          loader: loadSource,
          objectUrl: createdObjectUrl,
          source,
        })
      })
      .catch(() => {
        if (controller.signal.aborted) {
          return
        }

        releaseObjectUrl(createdObjectUrl, objectUrl)
        setLoadedSource({
          byteSize,
          kind: 'error',
          loader: loadSource,
          source,
        })
      })

    return () => {
      controller.abort()
      releaseObjectUrl(createdObjectUrl, objectUrl)
    }
  }, [byteSize, loadSource, source])

  const currentLoadedSource =
    loadedSource !== null &&
    loadedSource.loader === loadSource &&
    loadedSource.source === source &&
    loadedSource.byteSize === byteSize
      ? loadedSource
      : null
  const failed =
    loadSource === undefined
      ? directError
      : currentLoadedSource?.kind === 'error'
  const playableSource =
    loadSource === undefined
      ? directError
        ? null
        : source
      : currentLoadedSource?.kind === 'ready'
        ? currentLoadedSource.objectUrl
        : null

  useEffect(() => {
    const element = media.current

    return () => resetMediaElement(element)
  }, [playableSource])

  const handleMediaError = () => {
    resetMediaElement(media.current)
    releaseObjectUrl(objectUrl.current, objectUrl)

    if (loadSource === undefined) {
      setDirectError(true)
      return
    }

    setLoadedSource({
      byteSize,
      kind: 'error',
      loader: loadSource,
      source,
    })
  }

  return (
    <figure className="learner-video">
      <figcaption
        className="learner-video__caption"
        id={titleId}
      >
        {label}
      </figcaption>
      {loadSource !== undefined && currentLoadedSource === null ? (
        <p
          aria-live="polite"
          className="learner-status"
          role="status"
        >
          {loadingLabel}
        </p>
      ) : null}
      {failed ? (
        <div>
          <p role="alert">{errorLabel}</p>
          <button
            className="ge-button"
            onClick={onRetry}
            type="button"
          >
            {retryLabel}
          </button>
        </div>
      ) : null}
      {playableSource === null ? null : (
        <video
          aria-labelledby={titleId}
          className="learner-video__element"
          controls
          controlsList="nodownload"
          data-testid="lesson-video-element"
          onError={handleMediaError}
          playsInline
          preload="metadata"
          ref={media}
        >
          <source
            src={playableSource}
            type={mimeType}
          />
        </video>
      )}
    </figure>
  )
}

function releaseObjectUrl(
  candidate: string | null,
  current: React.RefObject<string | null>,
): void {
  if (candidate === null || current.current !== candidate) {
    return
  }

  URL.revokeObjectURL(candidate)

  if (current.current === candidate) {
    current.current = null
  }
}

function resetMediaElement(element: HTMLVideoElement | null): void {
  if (element === null) {
    return
  }

  try {
    element.pause()
  } catch {
    // The source attributes still need clearing if pause is unavailable.
  }

  element.removeAttribute('src')

  for (const source of element.querySelectorAll('source')) {
    source.removeAttribute('src')
  }

  try {
    element.load()
  } catch {
    // Detached test/browser elements may not implement load.
  }
}
