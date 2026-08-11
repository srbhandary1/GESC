import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useId, useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import type {
  LearnerJourneyApi,
  LearnerLevelVideo,
} from './LearnerJourneyApi'
import { LessonVideoPlayer } from './LessonVideoPlayer'

export interface LevelVideosPanelProps {
  readonly api: LearnerJourneyApi
  readonly enrollmentId: string
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}

type CatalogState =
  | {
      readonly api: LearnerJourneyApi
      readonly attempt: number
      readonly enrollmentId: string
      readonly kind: 'error'
    }
  | {
      readonly api: LearnerJourneyApi
      readonly attempt: number
      readonly enrollmentId: string
      readonly kind: 'ready'
      readonly videos: readonly LearnerLevelVideo[]
    }

export function LevelVideosPanel({
  api,
  enrollmentId,
  locale,
  localizationPolicy,
}: LevelVideosPanelProps) {
  const titleId = useId()
  const [attempt, setAttempt] = useState(0)
  const [catalog, setCatalog] = useState<CatalogState | null>(null)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  useEffect(() => {
    const controller = new AbortController()

    void api
      .listLevelVideos(enrollmentId, controller.signal)
      .then((catalog) => {
        if (controller.signal.aborted) {
          return
        }

        setCatalog({
          api,
          attempt,
          enrollmentId,
          kind: 'ready',
          videos: catalog.videos,
        })
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setCatalog({ api, attempt, enrollmentId, kind: 'error' })
        }
      })

    return () => controller.abort()
  }, [api, attempt, enrollmentId])

  const currentCatalog =
    catalog?.api === api &&
    catalog.enrollmentId === enrollmentId &&
    catalog.attempt === attempt
      ? catalog
      : null

  if (currentCatalog?.kind === 'ready' && currentCatalog.videos.length === 0) {
    return null
  }

  if (currentCatalog === null || currentCatalog.kind === 'error') {
    return (
      <section
        aria-labelledby={titleId}
        className="learner-card"
        data-testid="level-videos-panel"
      >
        <h3
          className="learner-card__title"
          id={titleId}
        >
          <LocalizedMessage
            resolution={message('learner.level_videos.title')}
          />
        </h3>
        {currentCatalog === null ? (
          <p
            aria-live="polite"
            className="learner-status"
            role="status"
          >
            <LocalizedMessage resolution={message('learner.loading')} />
          </p>
        ) : (
          <div>
            <p role="alert">
              <LocalizedMessage
                resolution={message('learner.error.message')}
              />
            </p>
            <button
              className="ge-button"
              onClick={() => setAttempt((current) => current + 1)}
              type="button"
            >
              <LocalizedMessage resolution={message('learner.retry')} />
            </button>
          </div>
        )}
      </section>
    )
  }

  const videos = currentCatalog.videos
  const selected =
    videos.find((video) => video.video.asset_id === selectedId) ?? videos[0]

  if (selected === undefined) {
    return null
  }

  return (
    <section
      aria-labelledby={titleId}
      className="learner-card"
      data-testid="level-videos-panel"
    >
      <h3
        className="learner-card__title"
        id={titleId}
      >
        <LocalizedMessage
          resolution={message('learner.level_videos.title')}
        />
      </h3>
      <p className="learner-notice">
        <LocalizedMessage
          resolution={message('learner.level_videos.summary')}
        />
      </p>
      <ul className="learner-video-list">
        {videos.map((video) => (
          <li key={`${video.lesson_id}:${video.video.asset_id}`}>
            <button
              aria-current={
                video.video.asset_id === selected.video.asset_id
                  ? 'true'
                  : undefined
              }
              className="ge-button"
              onClick={() => setSelectedId(video.video.asset_id)}
              type="button"
            >
              {video.level_label}
            </button>
          </li>
        ))}
      </ul>
      <LessonVideoPlayer
        byteSize={selected.video.byte_size}
        errorLabel={
          <LocalizedMessage resolution={message('learner.error.message')} />
        }
        label={selected.title.text}
        loadingLabel={
          <LocalizedMessage resolution={message('learner.loading')} />
        }
        loadSource={api.downloadLessonVideo}
        mimeType={selected.video.mime_type}
        retryLabel={
          <LocalizedMessage resolution={message('learner.retry')} />
        }
        source={selected.video.stream_url}
      />
    </section>
  )
}
