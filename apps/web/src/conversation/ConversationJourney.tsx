import type {
  MessageKey,
  MessageParameters,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import {
  useEffect,
  useRef,
  useState,
  type FormEvent,
  type ReactNode,
} from 'react'
import {
  browserSpeechRecognitionAvailable,
  recognizeWithBrowserSpeech,
  speakWithBrowserSpeech,
} from '../demo/browserSpeech'
import { speakDemoText, stopDemoSpeech } from '../demo/demoTts'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import type {
  ConversationJourneyApi,
  FriendSession,
  FriendTurn,
  FriendTurnEvent,
} from './ConversationJourneyApi'
import { defaultConversationJourneyApi } from './WebConversationJourneyApi'
import { AiMemoryPanel } from './AiMemoryPanel'
import { VoicePracticePanel } from './VoicePracticePanel'
import { TutorPresence, type TutorMood } from '../tutor/TutorPresence'
import { SpeakLoopTimingOverlay } from './SpeakLoopTimingOverlay'
import {
  abandonSpeakLoop,
  completeSpeakLoop,
  markSpeakLoop,
  type SpeakLoopPhase,
} from './speakLoopTimings'
import { isAbortError } from '../shared/isAbortError'
import { stopSpeechPlayback } from '../shared/stopSpeechPlayback'
import { conversationErrorMessageKey } from './conversationErrorMessage'
import {
  CONFIDENCE_LISTEN_LINES,
  CONFIDENCE_MIN_TURNS_BEFORE_REFLECT,
} from './confidenceContent'
import {
  PRACTICE_ABOUT_STORAGE_KEY,
  persistFirstRunDismissed,
  readFirstRunDismissed,
} from '../ui/firstRunPreference'
import { AvatarSlot } from '../ui/AvatarSlot'
import { TapToPlay } from '../ui/TapToPlay'
import './ConversationJourney.css'

export interface ConversationJourneyProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly api?: ConversationJourneyApi
  readonly createClientId?: () => string
  /**
   * When true, enables demo Azure-first TTS (local `/demo/tts`) with browser
   * speech fallback, plus on-device recognition for spoken turns.
   * Product path (ADR-0011) keeps text primary and may use on-device English
   * browser speech for model lines as a non-evidence interim aid.
   */
  readonly demoMode?: boolean
  readonly listenLines?: readonly string[]
  /** Restored from 9818fe2: embed inside a level room without the Speak chrome. */
  readonly embedded?: boolean
  readonly openingLine?: string
  readonly onLiveAiUsageChanged?: () => void
  readonly voiceUnavailableMessageKey?: MessageKey
}

type ConfidenceStage = 'warmup' | 'listen' | 'talk' | 'reflect'

type ConversationState =
  | { readonly kind: 'idle' }
  | { readonly kind: 'starting' }
  | {
      readonly kind: 'ready'
      readonly session: FriendSession
      readonly stage: ConfidenceStage
      readonly completedTurns: number
      readonly lastTurn?: FriendTurn
    }
  | {
      readonly kind: 'sending'
      readonly session: FriendSession
      readonly stage: 'talk'
      readonly completedTurns: number
      readonly lastTurn?: FriendTurn
    }
  | {
      readonly kind: 'result'
      readonly session: FriendSession
      readonly stage: 'talk'
      readonly turn: FriendTurn
      readonly events: readonly FriendTurnEvent[]
      readonly completedTurns: number
    }
  | {
      readonly kind: 'error'
      readonly session?: FriendSession
      readonly stage?: ConfidenceStage
      readonly completedTurns?: number
      readonly lastTurn?: FriendTurn
      readonly messageKey?: MessageKey
    }

const eventMessages: Readonly<Record<string, MessageKey>> = {
  accepted: 'conversation.state.accepted',
  completed: 'conversation.state.completed',
  failed: 'conversation.state.failed',
  generating_reply: 'conversation.state.generating_reply',
  synthesizing: 'conversation.state.synthesizing',
  transcribing: 'conversation.state.transcribing',
}

const stageKeys: Readonly<Record<ConfidenceStage, MessageKey>> = {
  warmup: 'conversation.confidence.stage.warmup',
  listen: 'conversation.confidence.stage.listen',
  talk: 'conversation.confidence.stage.talk',
  reflect: 'conversation.confidence.stage.reflect',
}

export function ConversationJourney({
  locale,
  localizationPolicy,
  api = defaultConversationJourneyApi,
  createClientId = createRandomClientId,
  demoMode = false,
  listenLines,
  embedded = false,
  openingLine,
  onLiveAiUsageChanged,
  voiceUnavailableMessageKey,
}: ConversationJourneyProps) {
  const modelLines =
    listenLines !== undefined && listenLines.length > 0
      ? listenLines
      : openingLine !== undefined && openingLine.trim() !== ''
        ? [openingLine]
        : CONFIDENCE_LISTEN_LINES
  const [state, setState] = useState<ConversationState>(() =>
    initialSession === undefined
      ? { kind: 'idle' }
      : {
          kind: 'ready',
          session: initialSession,
          stage: 'talk',
          completedTurns: 0,
        },
  )
  const [utterance, setUtterance] = useState('')
  const [coachingMode, setCoachingMode] = useState<
    'gentle' | 'tutor' | 'fluency'
  >('gentle')
  const [listening, setListening] = useState(false)
  const [speakingReply, setSpeakingReply] = useState(false)
  const [speechError, setSpeechError] = useState(false)
  const [showHint, setShowHint] = useState(false)
  const [inputMode, setInputMode] = useState<'type' | 'speak'>('speak')
  const [loopPhase, setLoopPhase] = useState<SpeakLoopPhase>('waiting')
  const [holdResetEpoch, setHoldResetEpoch] = useState(0)
  const [chatTurns, setChatTurns] = useState<readonly FriendTurn[]>([])
  const sessionCommandId = useRef(createClientId())
  const turnCommandId = useRef(createClientId())
  const stopRecognition = useRef<(() => void) | null>(null)
  const utteranceFieldRef = useRef<HTMLTextAreaElement | null>(null)
  const openingSpoken = useRef(false)
  const inFlightAbort = useRef<AbortController | null>(null)
  const stateRef = useRef(state)
  const message = (
    key: MessageKey,
    parameters: MessageParameters = {},
  ) => resolveInterfaceMessage(key, locale, localizationPolicy, parameters)
  const text = (key: MessageKey, parameters: MessageParameters = {}) =>
    message(key, parameters).text
  const sessionContent = FRIEND_SESSION_CONTENT[sessionType]

  useEffect(() => {
    stateRef.current = state
  }, [state])

  useEffect(() => {
    return () => {
      stopRecognition.current?.()
      stopDemoSpeech()
      inFlightAbort.current?.abort()
    }
  }, [])

  const speakModel = useCallback(
    (sessionId: string, reply: string, reportError = true) => {
      const trimmed = reply.trim()
      if (trimmed === '') {
        return
      }

      setSpeechError(false)
      setSpeakingReply(true)

      void speakSessionSpeech({
        api,
        demoMode,
        locale: 'en-IN',
        purpose: 'model_en',
        sessionId,
        text: trimmed,
      })
        .then(() => onLiveAiUsageChanged?.())
        .catch(() => {
          if (reportError) {
            setSpeechError(true)
          }
        })
        .finally(() => setSpeakingReply(false))
    },
    [api, demoMode, onLiveAiUsageChanged],
  )

  useEffect(() => {
    if (state.kind !== 'ready' || state.stage !== 'talk') {
      setShowSilenceNudge(false)
      return
    }

    if (utterance.trim() !== '' || listening) {
      setShowSilenceNudge(false)
      return
    }

  const settleHold = useCallback(() => {
    setLoopPhase('waiting')
    setHoldResetEpoch((epoch) => epoch + 1)
  }, [])

  const abortInFlight = useCallback(() => {
    inFlightAbort.current?.abort()
    inFlightAbort.current = null
    stopSpeechPlayback()
    abandonSpeakLoop()
    setSpeakingReply(false)
    settleHold()
  }, [settleHold])

  const speakTurnAudio = async (
    turn: FriendTurn,
    signal?: AbortSignal,
  ) => {
    const speech = turn.speech
    const modelAudio = speech?.model_audio_base64
    const guidanceAudio = speech?.guidance_audio_base64

    setSpeechError(false)
    setSpeakingReply(true)
    const hasModelAudio = typeof modelAudio === 'string' && modelAudio !== ''
    if (hasModelAudio || (demoMode && turn.assistant_reply !== null)) {
      setLoopPhase('speaking')
    }

    try {
      if (typeof modelAudio === 'string' && modelAudio !== '') {
        await playBase64Audio(
          modelAudio,
          speech?.model_audio_mime_type ?? 'audio/wav',
          {
            signal,
            onStarted: () => {
              markSpeakLoop('first_audio')
            },
          },
        )
      } else if (turn.assistant_reply !== null && demoMode) {
        markSpeakLoop('first_audio')
        await speakSessionSpeech({
          api,
          demoMode: true,
          locale: 'en-IN',
          purpose: 'model_en',
          sessionId: turn.conversation_session_id,
          text: turn.assistant_reply,
        })
      } else {
        completeSpeakLoop()
      }

      if (signal?.aborted) {
        return
      }

      if (typeof guidanceAudio === 'string' && guidanceAudio !== '') {
        await playBase64Audio(
          guidanceAudio,
          speech?.guidance_audio_mime_type ?? 'audio/wav',
          { signal },
        )
      } else {
        const guidance = turn.priority_correction?.explanation_l1
        const guidanceLocale =
          turn.priority_correction?.explanation_locale ?? locale
        if (
          typeof guidance === 'string' &&
          guidance.trim() !== '' &&
          demoMode
        ) {
          await speakSessionSpeech({
            api,
            demoMode: true,
            locale: guidanceLocale,
            purpose: 'guidance_l1',
            sessionId: turn.conversation_session_id,
            text: guidance,
          })
        }
      }
    } catch (error) {
      if (!isAbortError(error)) {
        setSpeechError(true)
        completeSpeakLoop()
      }
    } finally {
      setSpeakingReply(false)
      if (signal === undefined || !signal.aborted) {
        settleHold()
      }
    }
  }

  const start = () => {
    setState({ kind: 'starting' })
    setShowHint(false)
    setUtterance('')
    setChatTurns([])
    api
      .startSession(
        {
          coaching_mode: coachingMode,
          persona_key: 'patient-friend-prototype',
          scenario_key: 'everyday-friend-prototype',
          ui_locale: locale,
        },
        sessionCommandId.current,
      )
      .then((session) => {
        setState({
          kind: 'ready',
          session,
          stage: initialStage,
          completedTurns: 0,
        })
        const spoken = openingLine?.trim() || modelLines[0]
        if (
          speakOpening &&
          !openingSpoken.current &&
          spoken !== undefined &&
          spoken.trim() !== '' &&
          (autoStart || embedded)
        ) {
          openingSpoken.current = true
          speakEnglish(spoken)
        }
      })
      .catch((error: unknown) =>
        setState({
          kind: 'error',
          messageKey: conversationErrorMessageKey(
            error,
            'conversation.error',
          ),
        }),
      )
  }

  const submitTurn = (prototypeUtterance: string) => {
    const current = stateRef.current
    if (
      (current.kind !== 'ready' && current.kind !== 'result') ||
      current.stage !== 'talk' ||
      prototypeUtterance.trim() === ''
    ) {
      return
    }

    const session = current.session
    const completedTurns = current.completedTurns
    const nextUtterance = prototypeUtterance.trim()
    const lastTurn = current.kind === 'result' ? current.turn : current.lastTurn
    inFlightAbort.current?.abort()
    const controller = new AbortController()
    inFlightAbort.current = controller
    setLoopPhase((current) =>
      current === 'listening' || current === 'processing'
        ? current
        : 'preparing',
    )
    setState({
      kind: 'sending',
      session,
      stage: 'talk',
      completedTurns,
      lastTurn,
    })
    setSpeechError(false)
    setShowHint(false)
    api
      .createTurn(
        session.id,
        { prototype_utterance: prototypeUtterance },
        turnCommandId.current,
        controller.signal,
      )
      .then(async (turn) => {
        if (controller.signal.aborted) {
          return
        }

        onLiveAiUsageChanged?.()

        markSpeakLoop('turn_json_ready')
        const nextCompleted =
          turn.status === 'completed' ? completedTurns + 1 : completedTurns
        const eventsPromise = api.events(turn.events_url, controller.signal)

        if (turn.status === 'completed' && turn.assistant_reply !== null) {
          void speakTurnAudio(turn, controller.signal)
        } else {
          completeSpeakLoop()
          settleHold()
        }

        const events = await eventsPromise
        if (controller.signal.aborted) {
          return
        }

        setState({
          events,
          kind: 'result',
          session,
          stage: 'talk',
          turn,
          completedTurns: nextCompleted,
        })
        if (turn.status === 'completed') {
          setChatTurns((previous) => [...previous, turn])
        }
        setUtterance('')
        turnCommandId.current = createClientId()
      })
      .catch((error: unknown) => {
        if (isAbortError(error) || controller.signal.aborted) {
          setState({
            kind: 'ready',
            session,
            stage: 'talk',
            completedTurns,
          })
          return
        }

        completeSpeakLoop()
        settleHold()
        setState({
          kind: 'error',
          session,
          stage: 'talk',
          completedTurns,
          messageKey: conversationErrorMessageKey(
            error,
            'conversation.error',
          ),
        })
      })
  }

  const startListening = () => {
    if (!demoMode || !browserSpeechRecognitionAvailable()) {
      setSpeechError(true)
      return
    }

    setSpeechError(false)
    setListening(true)
    setInputMode('speak')
    const recognition = recognizeWithBrowserSpeech('en-IN')
    stopRecognition.current = recognition.stop
    void recognition.promise
      .then(({ transcript }) => {
        if (transcript !== '') {
          setUtterance(transcript)
        }
      })
      .catch(() => setSpeechError(true))
      .finally(() => {
        setListening(false)
        stopRecognition.current = null
      })
  }

  const stopListening = () => {
    stopRecognition.current?.()
    setListening(false)
  }

  const playReply = () => {
    if (state.kind !== 'result' || state.turn.assistant_reply === null) {
      return
    }

    speakEnglish(state.turn.assistant_reply)
  }

  const playModelLines = () => {
    speakEnglish(modelLines.join(' '))
  }

  const advanceStage = (stage: ConfidenceStage) => {
    if (state.kind !== 'ready' && state.kind !== 'result') {
      return
    }

    setState({
      kind: 'ready',
      session: state.session,
      stage,
      completedTurns: state.completedTurns,
      lastTurn: state.kind === 'result' ? state.turn : state.lastTurn,
    })
    setShowHint(false)
    setShowSilenceNudge(false)
  }

  const retryUtterance = () => {
    if (state.kind === 'result' && state.turn.transcript !== null) {
      setUtterance(state.turn.transcript)
    }
    utteranceFieldRef.current?.focus()
  }

  const typeInstead = () => {
    setInputMode('type')
    stopListening()
    utteranceFieldRef.current?.focus()
  }

  const skipTurn = () => {
    setUtterance('')
    setShowHint(false)
    if (state.kind === 'result') {
      setState({
        kind: 'ready',
        session: state.session,
        stage: 'talk',
        completedTurns: state.completedTurns,
        lastTurn: state.turn,
      })
    }
  }

  const finishToReflect = () => {
    if (state.kind !== 'ready' && state.kind !== 'result') {
      return
    }

    setState({
      kind: 'ready',
      session: state.session,
      stage: 'reflect',
      completedTurns: state.completedTurns,
      lastTurn: state.kind === 'result' ? state.turn : state.lastTurn,
    })
  }

  const restart = () => {
    sessionCommandId.current = createClientId()
    turnCommandId.current = createClientId()
    setState({ kind: 'idle' })
    setUtterance('')
    setShowHint(false)
    setChatTurns([])
  }

  const session =
    state.kind === 'ready' ||
    state.kind === 'sending' ||
    state.kind === 'result'
      ? state.session
      : state.kind === 'error'
        ? state.session
        : undefined

  const stage: ConfidenceStage | undefined =
    state.kind === 'ready' ||
    state.kind === 'sending' ||
    state.kind === 'result'
      ? state.stage
      : state.kind === 'error'
        ? state.stage
        : undefined

  const lastTurn =
    state.kind === 'result'
      ? state.turn
      : state.kind === 'ready' || state.kind === 'sending'
        ? state.lastTurn
        : undefined

  const completedTurns =
    state.kind === 'ready' ||
    state.kind === 'sending' ||
    state.kind === 'result'
      ? state.completedTurns
      : 0

  const carrySentence =
    lastTurn?.priority_correction?.correction_en ??
    lastTurn?.assistant_reply ??
    modelLines[0]

  return (
    <section
      className={
        embedded
          ? 'conversation-journey conversation-journey--embedded'
          : 'conversation-journey'
      }
      aria-labelledby="conversation-title"
      data-demo-speech={demoMode ? 'browser' : undefined}
      data-confidence-stage={stage}
      data-session-type={sessionType}
      data-speak-loop={loopPhase}
    >
      <div className="conversation-journey__heading">
        <TutorPresence
          locale={locale}
          localizationPolicy={localizationPolicy}
          mood={tutorMood(loopPhase, speakingReply, listening)}
          size={embedded ? 'lg' : 'md'}
        />
        <SpeakLoopTimingOverlay />
        <p className="conversation-journey__eyebrow">
          <LocalizedMessage
            resolution={message(
              demoMode
                ? 'conversation.demo.eyebrow'
                : 'conversation.eyebrow',
            )}
          />
        </p>
        <h2 id="conversation-title">
          <LocalizedMessage
            resolution={message(
              demoMode ? 'conversation.demo.title' : 'conversation.title',
            )}
          />
        </h2>
        {embedded ? null : (
        <button
          className="ge-button ge-button--quiet conversation-journey__about"
          onClick={() => setAboutOpen(true)}
          type="button"
        >
          <LocalizedMessage
            resolution={message('conversation.about_action')}
          />
        </button>
        )}
      </div>

      {aboutOpen ? (
        <div className="ge-sheet-backdrop">
          <aside
            aria-labelledby="conversation-disclosure"
            aria-modal="true"
            className="ge-sheet conversation-journey__notice"
            role="dialog"
          >
            <h3 id="conversation-disclosure">
              <LocalizedMessage
                resolution={message(
                  demoMode
                    ? 'conversation.demo.disclosure_title'
                    : 'conversation.disclosure_title',
                )}
              />
            </h3>
            <p>
              <LocalizedMessage
                resolution={message(
                  demoMode
                    ? 'conversation.demo.prototype_notice'
                    : 'conversation.prototype_notice',
                )}
              />
            </p>
            <p>
              <LocalizedMessage
                resolution={message(
                  demoMode
                    ? 'conversation.demo.browser_speech_notice'
                    : 'conversation.privacy_notice',
                )}
              />
            </p>
            <button
              className="ge-button ge-button--primary"
              onClick={() => {
                persistFirstRunDismissed(PRACTICE_ABOUT_STORAGE_KEY)
                setAboutOpen(false)
              }}
              type="button"
            >
              <LocalizedMessage
                resolution={message('conversation.about_continue')}
              />
            </button>
          </aside>
        </div>
      ) : null}

      {!embedded && (state.kind === 'idle' || state.kind === 'starting') ? (
        <div className="conversation-journey__start rounded-xl border border-line/70 bg-surface p-5 shadow-elevation-1">
          <label htmlFor="coaching-mode">
            <LocalizedMessage
              resolution={message('conversation.coaching_mode.label')}
            />
          </label>
          <select
            id="coaching-mode"
            value={coachingMode}
            disabled={state.kind === 'starting'}
            onChange={(event) =>
              setCoachingMode(
                event.currentTarget.value as 'gentle' | 'tutor' | 'fluency',
              )
            }
          >
            <option value="gentle">
              {text('conversation.coaching_mode.gentle')}
            </option>
            <option value="tutor">
              {text('conversation.coaching_mode.tutor')}
            </option>
            <option value="fluency">
              {text('conversation.coaching_mode.fluency')}
            </option>
          </select>
          <button
            className="ge-button ge-button--primary"
            type="button"
            disabled={state.kind === 'starting'}
            onClick={start}
          >
            <LocalizedMessage
              resolution={message(
                state.kind === 'starting'
                  ? 'conversation.starting'
                  : demoMode
                    ? 'conversation.demo.start'
                    : 'conversation.start',
              )}
            />
          </button>
        </div>
      ) : null}

      {session !== undefined && stage !== undefined ? (
        <p className="conversation-journey__progress" role="status">
          <LocalizedMessage
            resolution={message('conversation.confidence.progress')}
          />
          {': '}
          <LocalizedMessage resolution={message(stageKeys[stage])} />
        </p>
      ) : null}

      {session === undefined ? null : (
        <p className="conversation-journey__identity" lang="en-IN">
          {session.persona.disclosure}
        </p>
      )}

      {session !== undefined && stage === 'warmup' ? (
        <article
          className="conversation-journey__stage"
          aria-labelledby="confidence-warmup"
        >
          <h3 id="confidence-warmup">
            <LocalizedMessage
              resolution={message('conversation.confidence.warmup_title')}
            />
          </h3>
          <p lang={locale}>
            <LocalizedMessage
              resolution={message('conversation.confidence.warmup_lead')}
            />
          </p>
          <p lang="en-IN">
            <LocalizedMessage
              resolution={message('conversation.confidence.warmup_opener')}
            />
          </p>
          <button
            className="ge-button ge-button--primary"
            type="button"
            onClick={() => advanceStage('listen')}
          >
            <LocalizedMessage
              resolution={message('conversation.confidence.warmup_continue')}
            />
          </button>
        </article>
      ) : null}

      {session !== undefined && stage === 'listen' ? (
        <article
          className="conversation-journey__stage"
          aria-labelledby="confidence-listen"
        >
          <h3 id="confidence-listen">
            <LocalizedMessage
              resolution={message('conversation.confidence.listen_title')}
            />
          </h3>
          <p lang={locale}>
            <LocalizedMessage
              resolution={message('conversation.confidence.listen_lead')}
            />
          </p>
          <div className="conversation-journey__prompt">
            <TapToPlay
              idleLabel={
                <LocalizedMessage resolution={message('shell.listen')} />
              }
              onPlay={playModelLines}
              playingLabel={
                <LocalizedMessage
                  resolution={message('shell.listen.playing')}
                />
              }
              unavailableLabel={
                <LocalizedMessage
                  resolution={message('shell.listen.unavailable')}
                />
              }
            />
            <p className="conversation-journey__prompt-text" lang="en-IN">
              {modelLines[0]}
            </p>
          </div>
          {modelLines.length > 1 ? (
            <ol className="conversation-journey__model-lines" lang="en-IN">
              {modelLines.slice(1).map((line) => (
                <li key={line}>{line}</li>
              ))}
            </ol>
          ) : null}
          <p lang={locale}>
            <LocalizedMessage
              resolution={message('conversation.confidence.listen_text_first')}
            />
          </p>
          <button
            className="ge-button"
            type="button"
            disabled={speakingReply}
            onClick={playModelLines}
          >
            <LocalizedMessage
              resolution={message('conversation.confidence.listen_play')}
            />
          </button>
          {speechError ? (
            <p role="status" lang={locale}>
              <LocalizedMessage
                resolution={message(
                  'conversation.confidence.listen_playback_failed',
                )}
              />
            </p>
          ) : null}
          <button
            className="ge-button ge-button--primary"
            type="button"
            onClick={() => advanceStage('talk')}
          >
            <LocalizedMessage
              resolution={message('conversation.confidence.listen_continue')}
            />
          </button>
        </article>
      ) : null}

      {session !== undefined && stage === 'reflect' ? (
        <article
          className="conversation-journey__stage"
          aria-labelledby="confidence-reflect"
        >
          <h3 id="confidence-reflect">
            <LocalizedMessage
              resolution={message('conversation.confidence.reflect_title')}
            />
          </h3>
          <p lang={locale}>
            <LocalizedMessage
              resolution={message('conversation.confidence.reflect_lead')}
            />
          </p>
          <p lang={locale}>
            <LocalizedMessage
              resolution={message('conversation.confidence.reflect_summary')}
            />
          </p>
          <div>
            <p>
              <LocalizedMessage
                resolution={message('conversation.confidence.carry_label')}
              />
            </p>
            <p lang="en-IN">{carrySentence}</p>
          </div>
          <button
            className="ge-button ge-button--primary"
            type="button"
            onClick={restart}
          >
            <LocalizedMessage
              resolution={message('conversation.confidence.reflect_continue')}
            />
          </button>
        </article>
      ) : null}

      {session !== undefined && stage === 'talk' ? (
        <>
          <SpeakChatLog
            chatTurns={chatTurns}
            message={message}
            openingLine={openingLine}
          />
          {demoMode ? null : (
            <>
              <VoicePracticePanel
                api={api}
                createClientId={createClientId}
                locale={locale}
                onPreferTyping={typeInstead}
                onTranscriptSelected={(transcript) => {
                  setUtterance(transcript)
                  submitTurn(transcript)
                }}
                onLoopPhase={setLoopPhase}
                onCancelInFlight={abortInFlight}
                resetEpoch={holdResetEpoch}
                sessionId={session.id}
                text={text}
                unavailableMessageKey={voiceUnavailableMessageKey}
              />
              {embedded ? null : (
              <AiMemoryPanel
                api={api}
                createClientId={createClientId}
                locale={locale}
                localizationPolicy={localizationPolicy}
                text={text}
              />
              )}
            </>
          )}

          <div
            className="conversation-journey__controls"
            role="group"
            aria-label={text('conversation.confidence.controls_label')}
          >
            <button
              className="ge-button"
              type="button"
              onClick={retryUtterance}
            >
              <LocalizedMessage
                resolution={message('conversation.confidence.retry')}
              />
            </button>
            <button className="ge-button" type="button" onClick={typeInstead}>
              <LocalizedMessage
                resolution={message('conversation.confidence.type_instead')}
              />
            </button>
            <button className="ge-button" type="button" onClick={skipTurn}>
              <LocalizedMessage
                resolution={message('conversation.confidence.skip')}
              />
            </button>
            <button
              className="ge-button"
              type="button"
              onClick={() => setShowHint(true)}
            >
              <LocalizedMessage
                resolution={message('conversation.confidence.hint')}
              />
            </button>
            {completedTurns >= CONFIDENCE_MIN_TURNS_BEFORE_REFLECT ? (
              <button
                className="ge-button ge-button--primary"
                type="button"
                onClick={finishToReflect}
              >
                <LocalizedMessage
                  resolution={message('conversation.confidence.finish')}
                />
              </button>
            ) : null}
          </div>

          {showHint ? (
            <p lang={locale} role="status">
              <LocalizedMessage
                resolution={message('conversation.confidence.hint_text')}
              />
            </p>
          ) : null}

          {showSilenceNudge ? (
            <p lang={locale} role="status">
              <LocalizedMessage
                resolution={message('conversation.confidence.silence_nudge')}
              />
            </p>
          ) : null}

          <form className="conversation-journey__form" onSubmit={send}>
            <label htmlFor="prototype-utterance">
              <LocalizedMessage
                resolution={message('conversation.utterance_label')}
              />
            </label>
            <textarea
              id="prototype-utterance"
              ref={utteranceFieldRef}
              maxLength={500}
              required
              rows={4}
              value={utterance}
              data-input-mode={inputMode}
              onChange={(event) => setUtterance(event.currentTarget.value)}
            />
            {demoMode ? (
              <div className="conversation-journey__mic-dock">
                <p className="conversation-journey__mic-hint">
                  <LocalizedMessage
                    resolution={message(
                      listening
                        ? 'conversation.demo.listening'
                        : 'conversation.demo.hold_to_speak',
                    )}
                  />
                </p>
                <button
                  className="ge-mic-button"
                  data-listening={listening ? 'true' : undefined}
                  type="button"
                  disabled={state.kind === 'sending'}
                  onClick={listening ? stopListening : startListening}
                >
                  <span aria-hidden="true" className="ge-mic-button__glyph">
                    <svg
                      fill="none"
                      stroke="currentColor"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      viewBox="0 0 24 24"
                    >
                      <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                      <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                      <line x1="12" x2="12" y1="19" y2="23" />
                      <line x1="8" x2="16" y1="23" y2="23" />
                    </svg>
                  </span>
                  <span className="visually-hidden">
                    <LocalizedMessage
                      resolution={message(
                        listening
                          ? 'conversation.demo.stop_listening'
                          : 'conversation.demo.speak_turn',
                      )}
                    />
                  </span>
                </button>
              </div>
            ) : null}
            <button
              className="ge-button ge-button--primary"
              type="submit"
              disabled={state.kind === 'sending' || utterance.trim() === ''}
            >
              <LocalizedMessage
                resolution={message(
                  state.kind === 'sending'
                    ? 'conversation.sending'
                    : 'conversation.send',
                )}
              />
            </button>
          </form>
        </>
      ) : null}

      {state.kind === 'result' && stage === 'talk' ? (
        <article
          className="conversation-journey__result"
          aria-labelledby="conversation-reply"
        >
          {state.turn.status === 'completed' ? (
            <>
              <h3 id="conversation-reply" className="visually-hidden">
                <LocalizedMessage
                  resolution={message('conversation.reply_title')}
                />
              </h3>
              <div className="conversation-journey__speech-actions">
                <p lang={locale}>
                  <LocalizedMessage
                    resolution={message(
                      'conversation.confidence.listen_text_first',
                    )}
                  />
                </p>
                <button
                  className="ge-button ge-button--primary"
                  type="button"
                  disabled={
                    speakingReply || state.turn.assistant_reply === null
                  }
                  onClick={playReply}
                >
                  <LocalizedMessage
                    resolution={message(
                      demoMode
                        ? 'conversation.demo.listen_reply'
                        : 'conversation.confidence.listen_play',
                    )}
                  />
                </button>
                {speechError ? (
                  <p role="status" lang={locale}>
                    <LocalizedMessage
                      resolution={message(
                        'conversation.confidence.listen_playback_failed',
                      )}
                    />
                  </p>
                ) : null}
              </div>
            </>
          ) : (
            <h3 id="conversation-reply">
              <LocalizedMessage resolution={message('conversation.failed')} />
            </h3>
          )}

          <details className="conversation-journey__turn-events">
            <summary>
              <LocalizedMessage
                resolution={message('conversation.events_title')}
              />
            </summary>
            <ol className="conversation-journey__events">
              {state.events.map((turnEvent) => (
                <li key={turnEvent.sequence}>
                  <LocalizedMessage
                    resolution={message(
                      eventMessages[turnEvent.state] ??
                        'conversation.state.failed',
                    )}
                  />
                </li>
              ))}
            </ol>
          </details>
        </article>
      ) : null}

      {state.kind === 'error' ? (
        <p role="alert">
          <LocalizedMessage
            resolution={message(state.messageKey ?? 'conversation.error')}
          />
        </p>
      ) : speechError ? (
        <p role="alert">
          <LocalizedMessage
            resolution={message(
              demoMode
                ? 'conversation.demo.recognition_unavailable'
                : 'conversation.confidence.listen_playback_failed',
            )}
          />
        </p>
      ) : null}
    </section>
  )
}

function tutorMood(
  loopPhase: SpeakLoopPhase,
  speakingReply: boolean,
  listening: boolean,
): TutorMood {
  if (speakingReply || loopPhase === 'speaking') {
    return 'speaking'
  }

  if (listening || loopPhase === 'listening') {
    return 'listening'
  }

  if (loopPhase === 'processing') {
    return 'processing'
  }

  if (loopPhase === 'preparing') {
    return 'preparing'
  }

  return 'idle'
}

type SpeakChatLogProps = {
  readonly chatTurns: readonly FriendTurn[]
  readonly message: (
    key: MessageKey,
    parameters?: MessageParameters,
  ) => ReturnType<typeof resolveInterfaceMessage>
  readonly openingLine?: string
}

function SpeakChatLog({
  chatTurns,
  message,
  openingLine,
}: SpeakChatLogProps) {
  const hasOpening =
    openingLine !== undefined && openingLine.trim() !== ''

  if (!hasOpening && chatTurns.length === 0) {
    return null
  }

  return (
    <div className="conversation-journey__chat" role="log">
      {hasOpening ? (
        <ChatBubble role="teacher" message={message}>
          {openingLine}
        </ChatBubble>
      ) : null}
      {chatTurns.map((turn) => (
        <SpeakChatTurn key={turn.id} message={message} turn={turn} />
      ))}
    </div>
  )
}

type ChatBubbleProps = {
  readonly children: ReactNode
  readonly message: SpeakChatLogProps['message']
  readonly role: 'teacher' | 'you'
  readonly aside?: ReactNode
}

function ChatBubble({ aside, children, message, role }: ChatBubbleProps) {
  return (
    <div
      className={`conversation-journey__bubble conversation-journey__bubble--${role}`}
    >
      <div className="conversation-journey__bubble-row">
        <div className="conversation-journey__bubble-content">
          <p className="conversation-journey__bubble-speaker">
            <LocalizedMessage
              resolution={message(
                role === 'teacher'
                  ? 'conversation.chat.teacher'
                  : 'conversation.chat.you',
              )}
            />
          </p>
          <p className="conversation-journey__bubble-message" lang="en-IN">
            {children}
          </p>
        </div>
        {aside}
      </div>
    </div>
  )
}

type SpeakChatTurnProps = {
  readonly message: SpeakChatLogProps['message']
  readonly turn: FriendTurn
}

function SpeakChatTurn({ message, turn }: SpeakChatTurnProps) {
  const correction = turn.priority_correction
  const explanation =
    correction?.explanation_l1?.trim() ??
    correction?.encouragement_l1?.trim() ??
    ''
  const showExplanation = correction !== undefined && explanation !== ''

  return (
    <>
      {turn.transcript !== null && turn.transcript.trim() !== '' ? (
        <ChatBubble
          aside={
            showExplanation ? (
              <TurnExplanationAside correction={correction!} message={message} />
            ) : null
          }
          message={message}
          role="you"
        >
          {turn.transcript}
        </ChatBubble>
      ) : null}
      {turn.assistant_reply !== null &&
      turn.assistant_reply.trim() !== '' ? (
        <ChatBubble message={message} role="teacher">
          {turn.assistant_reply}
        </ChatBubble>
      ) : null}
    </>
  )
}

type TurnExplanationAsideProps = {
  readonly correction: NonNullable<FriendTurn['priority_correction']>
  readonly message: SpeakChatLogProps['message']
}

function TurnExplanationAside({
  correction,
  message,
}: TurnExplanationAsideProps) {
  return (
    <details className="conversation-journey__explanation">
      <summary
        aria-label={message('conversation.correction.explanation_label').text}
        className="conversation-journey__explanation-toggle"
        title={message('conversation.correction.explanation_label').text}
      />
      <div className="conversation-journey__explanation-body">
        {correction.needed ? (
          <>
            <p className="conversation-journey__explanation-label">
              <LocalizedMessage
                resolution={message(
                  'conversation.correction.suggestion_label',
                )}
              />
            </p>
            <p lang="en-IN">{correction.correction_en}</p>
          </>
        ) : null}
        <p lang={correction.explanation_locale}>
          {correction.encouragement_l1 ?? correction.explanation_l1}
        </p>
        {correction.needed && correction.retry_recommended ? (
          <p>
            <LocalizedMessage
              resolution={message('conversation.correction.retry')}
            />
          </p>
        ) : null}
      </div>
    </details>
  )
}
