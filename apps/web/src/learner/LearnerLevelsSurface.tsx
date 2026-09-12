import type {
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useState } from 'react'
import type { ConversationJourneyApi } from '../conversation/ConversationJourneyApi'
import { DemoPhase1Shell } from '../demo/phase1/DemoPhase1Shell'
import { LevelPathPanel } from './LevelPathPanel'
import type { LevelSessionsApi } from './LevelSessionsApi'

export interface LearnerLevelsSurfaceProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly conversationApi?: ConversationJourneyApi
  readonly demoMode?: boolean
  readonly demoOrganization?: string
  readonly levelSessionsApi: LevelSessionsApi
  readonly onLocaleChange?: (locale: SupportedLocale) => void
}

/**
 * Prefer commissioned LessonPractice levels from the Laravel API.
 * When the API is down or no level is attached, fall back to the authored
 * Phase 1 rooms (TTS + chapter conversation).
 */
export function LearnerLevelsSurface({
  locale,
  localizationPolicy,
  conversationApi,
  demoMode = false,
  demoOrganization,
  levelSessionsApi,
  onLocaleChange,
}: LearnerLevelsSurfaceProps) {
  const [mode, setMode] = useState<'probing' | 'api' | 'fallback'>('probing')

  useEffect(() => {
    const controller = new AbortController()
    void levelSessionsApi
      .listLevels(controller.signal)
      .then((payload) => {
        if (controller.signal.aborted) {
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
  }, [levelSessionsApi])

  if (mode === 'probing') {
    return (
      <p className="shell-checkpoint-notice" role="status">
        Loading levels…
      </p>
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
