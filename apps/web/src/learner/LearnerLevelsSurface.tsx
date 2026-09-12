import type {
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useState } from 'react'
import type { ConversationJourneyApi } from '../conversation/ConversationJourneyApi'
import { DemoPhase1Shell } from '../demo/phase1/DemoPhase1Shell'
import type { AudioRecorder } from './BrowserAudioRecorder'
import { LearnerJourney } from './LearnerJourney'
import type { LearnerJourneyApi } from './LearnerJourneyApi'
import { LevelPathPanel } from './LevelPathPanel'
import type { LevelSessionsApi } from './LevelSessionsApi'
import type {
  OfflineLearningStore,
  OfflinePersonalDeviceResume,
  OfflineProfileBinding,
} from '../offline/OfflineLearningStore'

export interface LearnerLevelsSurfaceProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly conversationApi?: ConversationJourneyApi
  readonly demoMode?: boolean
  readonly demoOrganization?: string
  readonly levelSessionsApi: LevelSessionsApi
  readonly learnerJourneyApi: LearnerJourneyApi
  readonly audioRecorder?: AudioRecorder
  readonly createClientId?: () => string
  readonly offlineBinding?: OfflineProfileBinding | null
  readonly offlineStore?: OfflineLearningStore
  readonly resumeBundle?: OfflinePersonalDeviceResume['storedBundle']
  readonly onLocaleChange?: (locale: SupportedLocale) => void
}

/**
 * Prefer commissioned LessonPractice levels from the Laravel API.
 * Demo mode falls back to authored Phase 1 rooms. Production keeps the
 * existing LearnerJourney mission path when levels are uncommissioned.
 */
export function LearnerLevelsSurface({
  locale,
  localizationPolicy,
  conversationApi,
  demoMode = false,
  demoOrganization,
  levelSessionsApi,
  learnerJourneyApi,
  audioRecorder,
  createClientId,
  offlineBinding = null,
  offlineStore,
  resumeBundle,
  onLocaleChange,
}: LearnerLevelsSurfaceProps) {
  const [mode, setMode] = useState<
    'probing' | 'onboarding' | 'api' | 'fallback'
  >('probing')

  useEffect(() => {
    const controller = new AbortController()
    void learnerJourneyApi
      .home(controller.signal)
      .then((home) => {
        if (controller.signal.aborted) {
          return
        }
        if (!home.onboarding.completed) {
          setMode('onboarding')
          return
        }
        return levelSessionsApi.listLevels(controller.signal)
      })
      .then((payload) => {
        if (controller.signal.aborted || payload === undefined) {
          return
        }
        const ready = payload.levels.some(
          (level) => level.availability === 'available',
        )
        setMode(ready ? 'api' : 'fallback')
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setMode('fallback')
        }
      })
    return () => {
      controller.abort()
    }
  }, [learnerJourneyApi, levelSessionsApi])

  if (mode === 'probing') {
    return (
      <p className="shell-checkpoint-notice" role="status">
        Loading levels…
      </p>
    )
  }

  if (mode === 'onboarding') {
    return (
      <LearnerJourney
        api={learnerJourneyApi}
        audioRecorder={audioRecorder}
        createClientId={createClientId}
        locale={locale}
        localizationPolicy={localizationPolicy}
        offlineBinding={offlineBinding}
        offlineStore={offlineStore}
        resumeBundle={resumeBundle}
      />
    )
  }

  if (mode === 'api') {
    return (
      <LevelPathPanel
        api={levelSessionsApi}
        conversationApi={conversationApi}
        demoMode={demoMode}
        locale={locale}
        localizationPolicy={localizationPolicy}
      />
    )
  }

  if (demoMode) {
    return (
      <DemoPhase1Shell
        conversationApi={conversationApi}
        demoMode={demoMode}
        demoOrganization={demoOrganization}
        forcedModule="levels"
        levelSessionsApi={levelSessionsApi}
        locale={locale}
        localizationPolicy={localizationPolicy}
        onLocaleChange={onLocaleChange}
      />
    )
  }

  return (
    <LearnerJourney
      api={learnerJourneyApi}
      audioRecorder={audioRecorder}
      createClientId={createClientId}
      locale={locale}
      localizationPolicy={localizationPolicy}
      offlineBinding={offlineBinding}
      offlineStore={offlineStore}
      resumeBundle={resumeBundle}
    />
  )
}
