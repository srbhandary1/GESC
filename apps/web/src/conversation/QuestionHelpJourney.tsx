import type { MessageKey, ResolutionPolicy, SupportedLocale } from '@global-english/localization'
import { useEffect, useRef, useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { isAbortError } from '../shared/isAbortError'
import type { ConversationJourneyApi, FriendSession } from './ConversationJourneyApi'
import { conversationErrorMessageKey } from './conversationErrorMessage'
import { InteractiveDialogue, RecoveryAcknowledgement, RECOVERY_NOTICE_VERSION } from './InteractiveDialogue'
import { ConversationProcessingConsent } from './ConversationProcessingConsent'
import { AiMemoryPanel } from './AiMemoryPanel'

export function QuestionHelpJourney({ api, createClientId, locale, localizationPolicy, onLiveAiUsageChanged, flow = 'question_help', startLessonSession }: {
  readonly flow?: 'question_help' | 'free_speech'
  readonly api: ConversationJourneyApi
  readonly createClientId: () => string
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly onLiveAiUsageChanged?: () => void
  readonly startLessonSession?: (command: string, signal: AbortSignal) => Promise<FriendSession>
}) {
  const [accepted, setAccepted] = useState(false)
  const [coachingMode, setCoachingMode] = useState<'gentle' | 'tutor' | 'fluency'>('gentle')
  const [providerGranted, setProviderGranted] = useState(false)
  const [session, setSession] = useState<FriendSession | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<MessageKey | null>(null)
  const command = useRef(createClientId())
  const commandLocale = useRef(locale)
  const commandMode = useRef(coachingMode)
  const request = useRef<AbortController | null>(null)
  const locked = useRef(false)
  const message = (key: MessageKey) => resolveInterfaceMessage(key, locale, localizationPolicy)
  useEffect(() => () => request.current?.abort(), [])

  const start = async () => {
    if (!accepted || !providerGranted || locked.current) return
    if (commandLocale.current !== locale || commandMode.current !== coachingMode) {
      command.current = createClientId()
      commandLocale.current = locale
      commandMode.current = coachingMode
    }
    locked.current = true
    const controller = new AbortController()
    request.current = controller
    setBusy(true)
    setError(null)
    try {
      const created = startLessonSession ? await startLessonSession(command.current, controller.signal) : await api.startSession({
        coaching_mode: flow === 'free_speech' ? coachingMode : 'gentle', persona_key: 'patient-friend-prototype',
        scenario_key: 'everyday-friend-prototype', ui_locale: locale,
        interaction_flow: flow, recovery_notice_accepted: true,
        recovery_notice_version: RECOVERY_NOTICE_VERSION,
      }, command.current, controller.signal)
      if (controller.signal.aborted) return
      if (created.interaction?.flow !== flow) {
        setError('conversation.interactive.recovery_error')
        return
      }
      setSession(created)
    } catch (failure) {
      if (!isAbortError(failure)) setError(conversationErrorMessageKey(failure, 'conversation.interactive.turn_error'))
    } finally {
      locked.current = false
      if (!controller.signal.aborted) setBusy(false)
    }
  }
  if (session) return <>
    <InteractiveDialogue api={api} createClientId={createClientId} initialSession={session} locale={locale} localizationPolicy={localizationPolicy} onExited={() => { setSession(null); command.current = createClientId() }} onLiveAiUsageChanged={onLiveAiUsageChanged} />
    {flow === 'free_speech' ? <AiMemoryPanel api={api} createClientId={createClientId} locale={locale} localizationPolicy={localizationPolicy} text={(key) => message(key).text} /> : null}
  </>

  return <section aria-labelledby="question-help-title" className="conversation-journey conversation-journey--embedded">
    <header className="conversation-journey__heading">
      <h1 id="question-help-title"><LocalizedMessage resolution={message(flow === 'free_speech' ? 'speak.mode.everyday' : 'conversation.help.title')} /></h1>
      <p><LocalizedMessage resolution={message(flow === 'free_speech' ? 'conversation.confidence.warmup_lead' : 'conversation.help.lead')} /></p>
    </header>
    {flow === 'free_speech' ? <div className="conversation-journey__start">
      <label htmlFor="coaching-mode"><LocalizedMessage resolution={message('conversation.coaching_mode.label')} /></label>
      <select id="coaching-mode" disabled={busy} value={coachingMode} onChange={(event) => setCoachingMode(event.currentTarget.value as 'gentle' | 'tutor' | 'fluency')}>
        {(['gentle', 'tutor', 'fluency'] as const).map((mode) => <option key={mode} value={mode}>{message(`conversation.coaching_mode.${mode}`).text}</option>)}
      </select>
    </div> : null}
    <ConversationProcessingConsent api={api} createClientId={createClientId} locale={locale} localizationPolicy={localizationPolicy} onGrantedChange={setProviderGranted} disabled={busy} />
    <RecoveryAcknowledgement accepted={accepted} locale={locale} localizationPolicy={localizationPolicy} onChange={setAccepted} />
    {error ? <p role="alert"><LocalizedMessage resolution={message(error)} /></p> : null}
    <button className="ge-button ge-button--primary" disabled={!accepted || !providerGranted || busy} onClick={() => void start()} type="button"><LocalizedMessage resolution={message(busy ? 'conversation.roleplay.starting' : flow === 'free_speech' ? 'conversation.start' : 'conversation.help.start')} /></button>
  </section>
}
