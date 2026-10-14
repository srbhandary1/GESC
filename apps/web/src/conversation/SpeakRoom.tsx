import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import { ConversationJourney } from './ConversationJourney'
import type {
  ConversationJourneyApi,
  FriendSession,
} from './ConversationJourneyApi'
import { RoleplayJourney } from './RoleplayJourney'
import { QuestionHelpJourney } from './QuestionHelpJourney'

export type SpeakMode = 'everyday' | 'situation' | 'question_help'

export interface SpeakRoomProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly api: ConversationJourneyApi
  readonly createClientId?: () => string
  readonly demoMode?: boolean
  readonly initialMode?: SpeakMode
  readonly initialSession?: FriendSession
  readonly allowsRoleplay?: boolean
  readonly onLiveAiUsageChanged?: () => void
  readonly roleplayStatusMessageKey?: MessageKey
  readonly voiceUnavailableMessageKey?: MessageKey
}

export function SpeakRoom({
  locale,
  localizationPolicy,
  api,
  createClientId = createRandomClientId,
  demoMode = false,
  initialMode = 'everyday',
  initialSession,
  allowsRoleplay = true,
  onLiveAiUsageChanged,
  roleplayStatusMessageKey,
  voiceUnavailableMessageKey,
}: SpeakRoomProps) {
  const [requestedMode, setRequestedMode] = useState<SpeakMode>(
    !allowsRoleplay && initialMode === 'situation' ? 'everyday' : initialMode,
  )
  const mode: SpeakMode = !allowsRoleplay && requestedMode === 'situation' ? 'everyday' : requestedMode
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  return (
    <div className="speak-room">
      <div
        aria-label={message('shell.practice.speak').text}
        className="shell-practice-switch speak-room__modes"
        role="tablist"
      >
        {(
          [
            { id: 'everyday', key: 'speak.mode.everyday' },
            { id: 'situation', key: 'speak.mode.situation' },
            { id: 'question_help', key: 'conversation.help.title' },
          ] as const
        ).map((tab) => {
          const selected = mode === tab.id
          const locked = tab.id === 'situation' && !allowsRoleplay
          return (
            <button
              aria-controls={`speak-panel-${tab.id}`}
              aria-selected={selected}
              disabled={locked}
              id={`speak-tab-${tab.id}`}
              key={tab.id}
              onClick={() => {
                if (!locked) {
                  setRequestedMode(tab.id)
                }
              }}
              onKeyDown={(event) => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
                event.preventDefault()
                const available: SpeakMode[] = allowsRoleplay ? ['everyday', 'situation', 'question_help'] : ['everyday', 'question_help']
                const index = available.indexOf(mode)
                const next = event.key === 'Home' ? available[0]
                  : event.key === 'End' ? available.at(-1)
                  : available[(index + (event.key === 'ArrowRight' ? 1 : available.length - 1)) % available.length]
                if (!next) return
                setRequestedMode(next)
                event.currentTarget.parentElement?.querySelector<HTMLButtonElement>(`#speak-tab-${next}`)?.focus()
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
      {roleplayStatusMessageKey !== undefined ? (
        <p className="speak-room__upgrade" role="status">
          <LocalizedMessage
            resolution={message(roleplayStatusMessageKey)}
          />
        </p>
      ) : null}
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
            onLiveAiUsageChanged={onLiveAiUsageChanged}
            onBack={() => {
              setRequestedMode('everyday')
            }}
          />
        ) : mode === 'question_help' ? (
          <QuestionHelpJourney key="question-help"
            api={api}
            createClientId={createClientId}
            locale={locale}
            localizationPolicy={localizationPolicy}
            onLiveAiUsageChanged={onLiveAiUsageChanged}
          />
        ) : !demoMode && initialSession === undefined ? (
          <QuestionHelpJourney key="free-speech" flow="free_speech" api={api} createClientId={createClientId} locale={locale} localizationPolicy={localizationPolicy} onLiveAiUsageChanged={onLiveAiUsageChanged} />
        ) : (
          <ConversationJourney
            api={api}
            createClientId={createClientId}
            demoMode={demoMode}
            initialSession={initialSession}
            key={initialSession?.id ?? 'unbound-speak'}
            locale={locale}
            localizationPolicy={localizationPolicy}
            onLiveAiUsageChanged={onLiveAiUsageChanged}
            voiceUnavailableMessageKey={voiceUnavailableMessageKey}
          />
        )}
      </div>
    </div>
  )
}
