import { enINCatalogue } from '@global-english/localization'
import { ROLEPLAY_CATALOG } from './roleplayCatalog'
import { archiveConversationOpening, archiveConversationTurn, type SavedAudio } from './deviceConversationHistory'
import { DeviceHistorySaveStatus } from './DeviceConversationHistoryPanel'
import type { MessageKey, MessageParameters, ResolutionPolicy, SupportedLocale } from '@global-english/localization'
import { useCallback, useEffect, useRef, useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { isAbortError } from '../shared/isAbortError'
import { stopSpeechPlayback } from '../shared/stopSpeechPlayback'
import { TutorPresence } from '../tutor/TutorPresence'
import type { AvatarPlaybackState } from '../tutor/avatar/types'
import type { ConversationJourneyApi, CreateFriendTurnInput, FriendSession, FriendTurn } from './ConversationJourneyApi'
import { conversationErrorMessageKey } from './conversationErrorMessage'
import { playConversationSpeechResult } from './speakSessionSpeech'
import { VoicePracticePanel, type SelectedVoiceTranscript } from './VoicePracticePanel'
import type { SpeakLoopPhase } from './speakLoopTimings'
import './ConversationJourney.css'

export const RECOVERY_NOTICE_VERSION = 'conversation-recovery-2026-09-25' as const

export function RecoveryAcknowledgement({ accepted, onChange, locale, localizationPolicy }: {
  readonly accepted: boolean
  readonly onChange: (accepted: boolean) => void
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}) {
  const message = (key: MessageKey) => resolveInterfaceMessage(key, locale, localizationPolicy)
  return <div className="conversation-journey__notice">
    <p><LocalizedMessage resolution={message('conversation.interactive.privacy')} /></p>
    <p><LocalizedMessage resolution={message('conversation.interactive.session_limit')} /></p>
    <label className="conversation-interactive__consent">
      <input checked={accepted} onChange={(event) => onChange(event.currentTarget.checked)} type="checkbox" />
      <LocalizedMessage resolution={message('conversation.interactive.accept_notice')} />
    </label>
  </div>
}

interface InteractiveDialogueProps {
  readonly api: ConversationJourneyApi
  readonly createClientId: () => string
  readonly initialSession: FriendSession
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly onExited: () => void
  readonly onLiveAiUsageChanged?: () => void
}

/** One volatile, shared web/Android controller; the API owns every dialogue gate. */
export function InteractiveDialogue({ api, createClientId, initialSession, locale, localizationPolicy, onExited, onLiveAiUsageChanged }: InteractiveDialogueProps) {
  const [session, setSession] = useState(initialSession)
  const [turns, setTurns] = useState<readonly FriendTurn[]>(initialSession.latest_turn ? [initialSession.latest_turn] : [])
  const [utterance, setUtterance] = useState('')
  const [reviewingVoice, setReviewingVoice] = useState(false)
  const [inputMode, setInputMode] = useState<'speak' | 'type'>('speak')
  const [phase, setPhase] = useState<SpeakLoopPhase>('waiting')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<MessageKey | null>(null)
  const [audioUnavailable, setAudioUnavailable] = useState(false)
  const [repairRequired, setRepairRequired] = useState(false)
  const [translatedQuestion, setTranslatedQuestion] = useState(false)
  const [canRetrySubmission, setCanRetrySubmission] = useState(false)
  const [processingWithdrawn, setProcessingWithdrawn] = useState(false)
  const [outcomeUnknown, setOutcomeUnknown] = useState(false)
  const [resetEpoch, setResetEpoch] = useState(0)
  const [avatarPlayback, setAvatarPlayback] = useState<AvatarPlaybackState | null>(null)
  const sessionRef = useRef(session)
  const mounted = useRef(false)
  const controller = useRef<AbortController | null>(null)
  const operationLocked = useRef(false)
  const openingStarted = useRef(false)
  const exited = useRef(false)
  const exitCommand = useRef(createClientId())
  const pendingAudio = useRef<SavedAudio | undefined>(undefined)
  const pending = useRef<{ input: CreateFriendTurnInput; command: string } | null>(null)
  const unknownRevision = useRef<number | null>(null)
  const field = useRef<HTMLTextAreaElement | null>(null)
  const nextAction = useRef<HTMLHeadingElement | null>(null)
  const message = (key: MessageKey, parameters: MessageParameters = {}) => resolveInterfaceMessage(key, locale, localizationPolicy, parameters)
  const text = (key: MessageKey, parameters: MessageParameters = {}) => message(key, parameters).text
  const interaction = session.interaction
  const questionHelp = interaction?.flow === 'question_help'
  const freeSpeech = interaction?.flow === 'free_speech'
  const terminal = processingWithdrawn || interaction === undefined || interaction === null || ['complete', 'expired', 'closed'].includes(interaction.state)
  const permissionPending = interaction?.state === 'awaiting_explanation_permission'
  const retryRequired = interaction?.state === 'retry_required'
  const lastTurn = turns.findLast((turn) => !turn.explanation_target)

  useEffect(() => { sessionRef.current = session }, [session])
  useEffect(() => {
    mounted.current = true
    const cleanupCommand = exitCommand.current
    return () => {
      mounted.current = false
      controller.current?.abort()
      stopSpeechPlayback()
      // StrictMode's immediate remount is not a learner exit. Real navigation
      // deletes private recovery best-effort; the server TTL covers disconnection.
      setTimeout(() => {
        if (!mounted.current && !exited.current) {
          void api.exitSession?.(initialSession.id, cleanupCommand).catch(() => undefined)
        }
      }, 0)
    }
  }, [api, initialSession.id])
  useEffect(() => {
    if (inputMode === 'type' && !busy) field.current?.focus()
  }, [inputMode, busy])
  useEffect(() => {
    if (!busy) nextAction.current?.focus()
  }, [busy, interaction?.revision])

  const stopAudio = useCallback(() => {
    controller.current?.abort()
    stopSpeechPlayback()
    setAvatarPlayback(null)
    setPhase('waiting')
  }, [])

  const clearVolatileContent = useCallback(() => {
    stopAudio()
    controller.current = null
    operationLocked.current = false
    pending.current = null
    pendingAudio.current = undefined
    unknownRevision.current = null
    setTurns([])
    setUtterance('')
    setCanRetrySubmission(false)
    setOutcomeUnknown(false)
    setRepairRequired(false)
    setTranslatedQuestion(false)
    setAudioUnavailable(false)
    setError(null)
    setBusy(false)
    setResetEpoch((value) => value + 1)
  }, [stopAudio])

  const endLocalSession = useCallback((current: FriendSession, state: 'expired' | 'closed') => {
    clearVolatileContent()
    const ended = { ...current, latest_turn: null, interaction: current.interaction ? { ...current.interaction, state, opening_reply: null, recovery_expires_at: null } : current.interaction }
    sessionRef.current = ended
    setSession(ended)
    if (state === 'closed') exited.current = true
  }, [clearVolatileContent])

  const checkExpiry = useCallback(() => {
    const current = sessionRef.current
    if (current.interaction?.state === 'closed' || current.interaction?.state === 'expired') return true
    const deadline = current.interaction?.recovery_expires_at
    if (deadline && Date.parse(deadline) <= Date.now()) {
      endLocalSession(current, 'expired')
      return true
    }
    return false
  }, [endLocalSession])

  useEffect(() => {
    const deadline = interaction?.recovery_expires_at
    if (!deadline || interaction.state === 'closed' || interaction.state === 'expired') return
    const delay = Date.parse(deadline) - Date.now()
    if (!Number.isFinite(delay)) return
    const timer = setTimeout(checkExpiry, Math.max(0, Math.min(delay, 2_147_483_647)))
    const onVisible = () => { if (document.visibilityState === 'visible') checkExpiry() }
    document.addEventListener('visibilitychange', onVisible)
    window.addEventListener('focus', checkExpiry)
    return () => {
      clearTimeout(timer)
      document.removeEventListener('visibilitychange', onVisible)
      window.removeEventListener('focus', checkExpiry)
    }
  }, [checkExpiry, interaction?.recovery_expires_at, interaction?.state])

  const play = async (turn?: FriendTurn) => {
    if (checkExpiry()) return
    stopAudio()
    const playback = new AbortController()
    controller.current = playback
    setAudioUnavailable(false)
    setPhase('speaking')
    try {
      const retry = turn?.interaction?.state === 'retry_required'
      const correction = turn?.priority_correction
      const reply = turn?.reply ?? (turn === undefined ? sessionRef.current.latest_turn?.explanation_source ?? sessionRef.current.interaction?.opening_reply : null)
      // Never speak a next character line when the server requires a retry.
      const mainText = retry ? correction?.correction_en : reply?.text
      let played = false
      if (mainText) {
        const speech = turn?.speech ?? await api.synthesizeSpeech?.(
          initialSession.id, { purpose: 'model_en', text: mainText }, createClientId(), playback.signal,
        )
        if (playback.signal.aborted) return
        if (!turn && speech) void archiveConversationOpening(sessionRef.current, speech)
        if (speech) played = await playConversationSpeechResult({
          purpose: 'model_en', speech, text: mainText, signal: playback.signal,
          onPlaybackChange: (value) => { if (mounted.current) setAvatarPlayback(value) },
        })
      }
      if (retry && correction?.explanation_l1 && !playback.signal.aborted) {
        const speech = turn?.speech
        if (speech) await playConversationSpeechResult({
          purpose: 'guidance_l1', speech, text: correction.explanation_l1, signal: playback.signal,
          onPlaybackChange: (value) => { if (mounted.current) setAvatarPlayback(value) },
        })
      }
      if (mounted.current && !playback.signal.aborted) setAudioUnavailable(!played)
    } catch (failure) {
      if (mounted.current && !isAbortError(failure)) setAudioUnavailable(true)
    } finally {
      if (mounted.current && controller.current === playback) {
        setAvatarPlayback(null)
        setPhase('waiting')
        setResetEpoch((value) => value + 1)
      }
    }
  }

  useEffect(() => {
    if (openingStarted.current || initialSession.latest_turn || !initialSession.interaction?.opening_reply) return
    openingStarted.current = true
    // Delay so StrictMode's rehearsal cleanup cannot cancel the only opening.
    const timer = setTimeout(() => { if (mounted.current) void play() }, 0)
    return () => { clearTimeout(timer); openingStarted.current = false }
    // The opening belongs to the immutable initial session, not render state.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialSession.id])

  const applyTurn = (turn: FriendTurn) => {
    setTurns((previous) => previous.some((item) => item.id === turn.id) ? previous : [...previous, turn].slice(-20))
    if (turn.interaction && turn.interaction.revision >= (sessionRef.current.interaction?.revision ?? 0)) {
      const next = { ...sessionRef.current, interaction: turn.interaction, latest_turn: turn }
      sessionRef.current = next
      setSession(next)
    }
  }

  const recover = async () => {
    if (checkExpiry() || !api.readSession || operationLocked.current) return
    stopAudio()
    operationLocked.current = true
    setBusy(true)
    setError(null)
    const request = new AbortController()
    controller.current = request
    try {
      const recovered = await api.readSession(initialSession.id, request.signal)
      if (!mounted.current || request.signal.aborted || checkExpiry()) return
      if (recovered.interaction?.state === 'closed' || recovered.interaction?.state === 'expired') {
        endLocalSession(recovered, recovered.interaction.state)
        return
      }
      sessionRef.current = recovered
      setSession(recovered)
      if (checkExpiry()) return
      if (recovered.latest_turn) {
        applyTurn(recovered.latest_turn)
        if (recovered.latest_turn.status === 'completed' && recovered.latest_turn.safety_state === 'ok') {
          void archiveConversationTurn(recovered, recovered.latest_turn, pendingAudio.current)
          pendingAudio.current = undefined
          if (!recovered.latest_turn.explanation_target) void play(recovered.latest_turn)
        }
      }
      if (unknownRevision.current !== null) {
        if (recovered.latest_turn && (recovered.interaction?.revision ?? 0) > unknownRevision.current) {
          unknownRevision.current = null
          setOutcomeUnknown(false)
        } else {
          setError('conversation.turn_outcome_unknown')
        }
      }
      if (pending.current && (recovered.interaction?.revision ?? 0) > (pending.current.input.expected_revision ?? 0)) {
        const wasExplanation = pending.current.input.explanation_target !== undefined
        pending.current = null
        setCanRetrySubmission(false)
        if (!wasExplanation) setUtterance('')
      }
    } catch (failure) {
      if (mounted.current && !isAbortError(failure) && !checkExpiry()) setError('conversation.interactive.recovery_error')
    } finally {
      operationLocked.current = false
      if (mounted.current) { setBusy(false); if (controller.current === request) setPhase('waiting') }
    }
  }

  const submit = async (input: CreateFriendTurnInput) => {
    if (checkExpiry() || operationLocked.current || terminal || unknownRevision.current !== null) return
    stopAudio()
    const serialized = JSON.stringify(input)
    if (pending.current === null || JSON.stringify(pending.current.input) !== serialized) {
      pending.current = { input, command: createClientId() }
    }
    const command = pending.current.command
    setCanRetrySubmission(true)
    operationLocked.current = true
    setBusy(true)
    setPhase('preparing')
    setError(null)
    const request = new AbortController()
    controller.current = request
    try {
      const turn = await api.createTurn(initialSession.id, input, command, request.signal)
      if (!mounted.current || request.signal.aborted || checkExpiry()) return
      if (turn.interaction?.state === 'expired' || turn.interaction?.state === 'closed') {
        endLocalSession({ ...sessionRef.current, interaction: turn.interaction }, turn.interaction.state)
        return
      }
      applyTurn(turn)
      pending.current = null
      setCanRetrySubmission(false)
      const usable = turn.status === 'completed' && turn.safety_state === 'ok'
      if (usable) { void archiveConversationTurn(sessionRef.current, turn, pendingAudio.current); pendingAudio.current = undefined }
      if (!turn.explanation_target) setUtterance(usable && (questionHelp || freeSpeech || turn.assessment === 'accepted') ? '' : input.prototype_utterance)
      setRepairRequired(false)
      onLiveAiUsageChanged?.()
      window.dispatchEvent(new Event('global-english:ai-usage-changed'))
      if (usable && !turn.explanation_target) void play(turn)
      else if (usable) setPhase('waiting')
      else {
        // A rejected answer is a completed provider call, not a lost outcome.
        // An explicit retry is a new logical turn at the new server revision.
        pending.current = { input: { ...input, expected_revision: turn.interaction?.revision ?? input.expected_revision }, command: createClientId() }
        setCanRetrySubmission(true)
        setError('conversation.interactive.answer_unavailable')
        setPhase('waiting')
      }
      if (turn.interaction?.state === 'complete' && api.readSession) {
        const completed = await api.readSession(initialSession.id)
        if (mounted.current && !checkExpiry()) {
          if (completed.interaction?.state === 'closed' || completed.interaction?.state === 'expired') endLocalSession(completed, completed.interaction.state)
          else { sessionRef.current = completed; setSession(completed) }
        }
      }
    } catch (failure) {
      if (mounted.current && !isAbortError(failure) && !checkExpiry()) {
        const key = conversationErrorMessageKey(failure, 'conversation.interactive.turn_error')
        if (key === 'conversation.turn_outcome_unknown') {
          unknownRevision.current = input.expected_revision ?? 0
          pending.current = null
          setCanRetrySubmission(false)
          setOutcomeUnknown(true)
        }
        setError(key)
      }
      if (mounted.current) setPhase('waiting')
    } finally {
      operationLocked.current = false
      if (mounted.current) { setBusy(false); setResetEpoch((value) => value + 1) }
    }
  }

  const send = (value: string, voice?: SelectedVoiceTranscript) => {
    if (checkExpiry()) return
    const current = sessionRef.current.interaction
    if (!current || !value.trim()) return
    pendingAudio.current = voice?.audio
    setTranslatedQuestion(questionHelp && voice?.source === 'translated_speech')
    if (voice && !questionHelp && (voice.source === 'translated_speech' || (voice.source === 'original_speech' && voice.locale !== 'en-IN'))) {
      stopAudio()
      setUtterance(value)
      setInputMode('type')
      setRepairRequired(true)
      return
    }
    void submit({
      prototype_utterance: value.trim(),
      action: current.state === 'retry_required' ? 'retry' : questionHelp ? 'question' : 'reply',
      expected_revision: current.revision,
      transcript_locale: voice?.locale ?? 'unknown',
      transcript_source: voice?.source ?? 'typed',
      ...(voice?.recordingId ? { voice_recording_id: voice.recordingId } : {}),
    })
  }

  const helpAction = (action: 'confused' | 'accept_explanation' | 'decline_explanation', key: MessageKey) => {
    if (!sessionRef.current.interaction) return
    void submit({ prototype_utterance: text(key), action, expected_revision: sessionRef.current.interaction.revision, transcript_source: 'typed', transcript_locale: locale })
  }

  const explain = (target: string) => {
    if (!sessionRef.current.interaction) return
    void submit({ prototype_utterance: text('conversation.interactive.read_translation'), action: 'accept_explanation',
      explanation_target: target, expected_revision: sessionRef.current.interaction.revision, transcript_source: 'typed', transcript_locale: locale })
  }

  const readingAid = (target: string) => {
    if (!freeSpeech || locale === 'en-IN' || terminal) return null
    const explanation = turns.find((turn) => turn.explanation_target === target && turn.status === 'completed' && turn.safety_state === 'ok')
    return <div className="conversation-interactive__reading-aid">
      {explanation?.reply ? <p lang={explanation.reply.locale}>{explanation.reply.text}</p>
        : <button className="ge-button ge-button--ghost" disabled={busy || outcomeUnknown} onClick={() => explain(target)} type="button"><LocalizedMessage resolution={message('conversation.interactive.read_translation')} /></button>}
    </div>
  }

  const exit = async () => {
    if (operationLocked.current || !api.exitSession) return
    stopAudio()
    operationLocked.current = true
    setBusy(true)
    setError(null)
    try {
      const closed = await api.exitSession(initialSession.id, exitCommand.current)
      if (mounted.current) endLocalSession(closed, 'closed')
    } catch {
      if (mounted.current) setError('conversation.interactive.exit_error')
    } finally {
      operationLocked.current = false
      if (mounted.current) setBusy(false)
    }
  }

  const cancel = () => {
    stopAudio()
    setPhase('waiting')
    setError(pending.current ? 'conversation.interactive.cancelled' : null)
  }

  const rolePair = `${interaction?.ai_role} / ${interaction?.learner_role}`
  const roleEntry = ROLEPLAY_CATALOG.find(entry => enINCatalogue[entry.rolesKey] === rolePair)

  return <section aria-labelledby="interactive-dialogue-title" className="conversation-journey conversation-journey--embedded conversation-interactive" data-mode={interaction?.flow}>
    <header className="conversation-journey__heading">
      <TutorPresence avatarPlayback={avatarPlayback} enableAvatar3d locale={locale} localizationPolicy={localizationPolicy} mood={phase === 'speaking' ? 'speaking' : busy ? 'processing' : 'idle'} showName={false} size="lg" />
      <h1 id="interactive-dialogue-title"><LocalizedMessage resolution={message(questionHelp ? 'conversation.help.title' : freeSpeech ? 'speak.mode.everyday' : 'conversation.roleplay.title')} /></h1>
      {!questionHelp && !freeSpeech ? <p><LocalizedMessage resolution={message('conversation.interactive.roles')} /> <span lang={roleEntry ? message(roleEntry.rolesKey).resolvedLocale : 'en-IN'}>{roleEntry ? message(roleEntry.rolesKey).text : rolePair}</span></p> : null}
      <details className="conversation-interactive__options"><summary><LocalizedMessage resolution={message('conversation.interactive.options')} /></summary>
      <button className="ge-button ge-button--ghost" disabled={busy} onClick={() => interaction?.state === 'closed' ? onExited() : void exit()} type="button"><LocalizedMessage resolution={message(interaction?.state === 'closed' ? 'conversation.interactive.done' : 'conversation.interactive.exit')} /></button>
      </details>
    </header>
    <div aria-live="polite" className="conversation-journey__chat" role="log">
      {!terminal && interaction?.opening_reply ? <div className="conversation-journey__bubble conversation-journey__bubble--teacher"><p lang={interaction.opening_reply.locale}>{interaction.opening_reply.text}</p>{readingAid('opening')}</div> : null}
      {turns.filter((turn) => !turn.explanation_target).map((turn) => <div className="conversation-interactive__exchange" key={turn.id}>
        {turn.transcript ? <div className="conversation-journey__bubble conversation-journey__bubble--you"><p>{turn.transcript}</p></div> : null}
        {turn.status === 'completed' && turn.safety_state === 'ok' && turn.interaction?.state !== 'retry_required' && turn.reply ? <div className="conversation-journey__bubble conversation-journey__bubble--teacher"><p lang={turn.reply.locale}>{turn.reply.text}</p>{readingAid(turn.id)}</div> : null}
        {turn.status === 'completed' && turn.safety_state === 'ok' && (turn.interaction?.state === 'retry_required' || freeSpeech) && turn.priority_correction?.needed ? <aside className="conversation-journey__correction">
          <h2><LocalizedMessage resolution={message('conversation.correction.suggestion_label')} /></h2>
          <p lang="en-IN">{turn.priority_correction.correction_en}</p>
          <p lang={turn.priority_correction.explanation_locale}>{turn.priority_correction.explanation_l1}</p>
          {turn.priority_correction.encouragement_l1 ? <p lang={turn.priority_correction.explanation_locale}>{turn.priority_correction.encouragement_l1}</p> : null}
        </aside> : null}
      </div>)}
      {turns.filter((turn) => turn.explanation_target && turn.explanation_target !== 'opening' && !turns.some((source) => source.id === turn.explanation_target)).map((turn) =>
        turn.explanation_source ? <div className="conversation-journey__bubble conversation-journey__bubble--teacher" key={turn.id}>
          <p lang={turn.explanation_source.locale}>{turn.explanation_source.text}</p>{readingAid(turn.explanation_target!)}
        </div> : null)}
    </div>
    <DeviceHistorySaveStatus text={text} />
    {error ? <p role="alert"><LocalizedMessage resolution={message(error)} /></p> : null}
    {audioUnavailable ? <p role="status"><LocalizedMessage resolution={message('conversation.interactive.audio_unavailable')} /></p> : null}
    {repairRequired ? <p role="status"><LocalizedMessage resolution={message('conversation.interactive.original_repair')} /></p> : null}
    {translatedQuestion && !terminal ? <p role="status"><LocalizedMessage resolution={message('conversation.interactive.translated_question')} /></p> : null}
    <div className="conversation-journey__controls">
      {!terminal && !outcomeUnknown && ((lastTurn?.safety_state === 'ok' && lastTurn.reply) || (!lastTurn && interaction?.opening_reply)) ? <button className="ge-button" disabled={busy} onClick={() => void play(lastTurn)} type="button"><LocalizedMessage resolution={message('conversation.interactive.replay')} /></button> : null}
      {error && canRetrySubmission ? <button className="ge-button" disabled={busy} onClick={() => { if (pending.current) void submit(pending.current.input) }} type="button"><LocalizedMessage resolution={message('conversation.interactive.retry_send')} /></button> : null}
      {error && error !== 'conversation.interactive.answer_unavailable' && api.readSession ? <button className="ge-button" disabled={busy} onClick={() => void recover()} type="button"><LocalizedMessage resolution={message('conversation.interactive.recover')} /></button> : null}
      {busy || phase === 'speaking' ? <button className="ge-button" onClick={cancel} type="button"><LocalizedMessage resolution={message('conversation.interactive.cancel')} /></button> : null}
    </div>
    {terminal ? <div className="conversation-journey__result">
      <h2 ref={nextAction} tabIndex={-1}><LocalizedMessage resolution={message(interaction?.state === 'complete' || interaction?.state === 'closed' ? 'conversation.interactive.complete' : 'conversation.interactive.expired')} /></h2>
      {session.feedback?.map((item) => <p key={item.id}>{session.objectives?.find((objective) => objective.id === item.scenario_objective_id)?.title}{': '}
        <LocalizedMessage resolution={message(item.completion_state === 'met' ? 'conversation.roleplay.objective_met' : item.completion_state === 'unmet' ? 'conversation.roleplay.objective_unmet' : 'conversation.interactive.unassessed')} />
      </p>)}
    </div> : outcomeUnknown ? null : <>
      <h2 ref={nextAction} tabIndex={-1}><LocalizedMessage resolution={message(permissionPending ? 'conversation.help.permission' : retryRequired ? 'conversation.interactive.retry' : questionHelp ? 'conversation.help.question' : 'conversation.interactive.your_turn')} /></h2>
      {permissionPending ? <div className="conversation-journey__controls">
        <button className="ge-button" disabled={busy} onClick={() => helpAction('accept_explanation', 'conversation.help.yes')} type="button"><LocalizedMessage resolution={message('conversation.help.yes')} /></button>
        <button className="ge-button" disabled={busy} onClick={() => helpAction('decline_explanation', 'conversation.help.no')} type="button"><LocalizedMessage resolution={message('conversation.help.no')} /></button>
      </div> : questionHelp && lastTurn?.reply ? <button className="ge-button" disabled={busy} onClick={() => helpAction('confused', 'conversation.help.confused')} type="button"><LocalizedMessage resolution={message('conversation.help.confused')} /></button> : null}
      <VoicePracticePanel api={api} compact hideTypingFallback createClientId={createClientId} includeTranscriptProvenance requireTranscriptConfirmation locale={locale} onCancelInFlight={cancel} onLoopPhase={(next) => { setPhase(next); if (next === 'listening') setInputMode('speak') }} onReviewStateChange={setReviewingVoice} onPreferTyping={() => setInputMode('type')} onProcessingConsentWithdrawn={() => {
        endLocalSession(sessionRef.current, 'closed')
        setProcessingWithdrawn(true)
      }} onTranscriptSelected={(value, metadata) => send(value, metadata)} resetEpoch={resetEpoch} sessionId={initialSession.id} speakLoopPhase={phase} text={text} />
      {!reviewingVoice && !busy && phase !== 'speaking' ? <button className="ge-button ge-button--ghost" onClick={() => setInputMode(inputMode === 'type' ? 'speak' : 'type')} type="button"><LocalizedMessage resolution={message(inputMode === 'type' ? 'learner.recording.tap_speak' : 'conversation.confidence.type_instead')} /></button> : null}
      {inputMode === 'type' && !reviewingVoice ? <form className="conversation-journey__form" onSubmit={(event) => { event.preventDefault(); send(utterance) }}>
        <label htmlFor="interactive-utterance"><LocalizedMessage resolution={message(questionHelp ? 'conversation.help.question' : 'conversation.utterance_label')} /></label>
        <textarea disabled={busy} id="interactive-utterance" maxLength={500} onChange={(event) => setUtterance(event.currentTarget.value)} ref={field} required rows={3} value={utterance} />
        <button className="ge-button ge-button--primary" disabled={busy || !utterance.trim()} type="submit"><LocalizedMessage resolution={message(busy ? 'conversation.sending' : 'conversation.send')} /></button>
      </form> : null}
    </>}
  </section>
}
