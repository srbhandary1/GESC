import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useState } from 'react'
import { InteractiveAvatar } from '../components/InteractiveAvatar'
import { CoachSelector } from '../coach/CoachSelector'
import { coachDisplayName } from '../coach/coachPreferences'
import { useCoachPreference } from '../coach/coachPreferenceContextValue'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import { ConversationJourney } from './ConversationJourney'
import type {
  ConversationJourneyApi,
  FriendSession,
} from './ConversationJourneyApi'
import { RoleplayJourney } from './RoleplayJourney'

export type SpeakMode = 'everyday' | 'situation'

export interface SpeakRoomProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly api: ConversationJourneyApi
  readonly createClientId?: () => string
  readonly demoMode?: boolean
  readonly initialMode?: SpeakMode
  readonly initialSession?: FriendSession
}

export function SpeakRoom({
  locale,
  localizationPolicy,
  api,
  createClientId = createRandomClientId,
  demoMode = false,
  initialMode = 'everyday',
  initialSession,
}: SpeakRoomProps) {
  const [mode, setMode] = useState<SpeakMode>(initialMode)
  const [coachSheetOpen, setCoachSheetOpen] = useState(false)
  const { preference } = useCoachPreference()
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const displayName = coachDisplayName(
    preference,
    message('coach.female.name').text,
    message('coach.male.name').text,
  )

  return (
    <div className="speak-room">
      <div className="speak-room__coach-header">
        <InteractiveAvatar
          coachGender={preference.gender}
          name={displayName}
          showName={false}
          size="sm"
        />
        <p className="speak-room__coach-name">{displayName}</p>
        <button
          className="ge-button speak-room__change-coach"
          onClick={() => setCoachSheetOpen(true)}
          type="button"
        >
          <LocalizedMessage resolution={message('conversation.change_coach')} />
        </button>
      </div>
      {coachSheetOpen ? (
        <div
          aria-labelledby="coach-selector-title"
          aria-modal="true"
          className="speak-room__coach-sheet"
          role="dialog"
        >
          <CoachSelector
            locale={locale}
            localizationPolicy={localizationPolicy}
          />
          <button
            className="ge-button"
            onClick={() => setCoachSheetOpen(false)}
            type="button"
          >
            <LocalizedMessage resolution={message('learner.onboarding.back')} />
          </button>
        </div>
      ) : null}
      <div
        aria-label={message('shell.practice.speak').text}
        className="shell-practice-switch speak-room__modes"
        role="tablist"
      >
        {(
          [
            { id: 'everyday', key: 'speak.mode.everyday' },
            { id: 'situation', key: 'speak.mode.situation' },
          ] as const
        ).map((tab) => {
          const selected = mode === tab.id
          return (
            <button
              aria-controls={`speak-panel-${tab.id}`}
              aria-selected={selected}
              id={`speak-tab-${tab.id}`}
              key={tab.id}
              onClick={() => {
                setMode(tab.id)
              }}
              role="tab"
              tabIndex={selected ? 0 : -1}
              type="button"
            >
              <LocalizedMessage resolution={message(tab.key)} />
            </button>
          )
        })}
      </div>
      <div
        aria-labelledby={`speak-tab-${mode}`}
        id={`speak-panel-${mode}`}
        role="tabpanel"
      >
        {mode === 'situation' ? (
          <RoleplayJourney
            api={api}
            createClientId={createClientId}
            locale={locale}
            localizationPolicy={localizationPolicy}
            onBack={() => {
              setMode('everyday')
            }}
          />
        ) : (
          <ConversationJourney
            api={api}
            createClientId={createClientId}
            demoMode={demoMode}
            initialSession={initialSession}
            key={initialSession?.id ?? 'unbound-speak'}
            locale={locale}
            localizationPolicy={localizationPolicy}
          />
        )}
      </div>
    </div>
  )
}
