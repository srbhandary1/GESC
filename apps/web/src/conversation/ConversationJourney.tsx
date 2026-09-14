import type {
  MessageKey,
  MessageParameters,
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
  browserSpeechRecognitionAvailable,
  recognizeWithBrowserSpeech,
} from '../demo/browserSpeech'
import { stopDemoSpeech } from '../demo/demoTts'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import type {
  ConversationJourneyApi,
  FriendSession,
  FriendTurn,
  FriendTurnEvent,
} from './ConversationJourneyApi'
import { playConversationSpeechResult } from './speakSessionSpeech'
import { speakSessionSpeech } from './speakSessionSpeech'
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
import { speechLatencySample } from './speechLatencyTelemetry'
import { isAbortError } from '../shared/isAbortError'
import { stopSpeechPlayback } from '../shared/stopSpeechPlayback'
import { conversationErrorMessageKey } from './conversationErrorMessage'
import {
  FRIEND_SESSION_CONTENT,
  type FriendSessionType,
} from './confidenceContent'
import { lessonDrillDisplayLine } from './lessonDrillStepper'
import './ConversationJourney.css'

export interface ConversationJourneyProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly api?: ConversationJourneyApi
  readonly createClientId?: () => string
  readonly demoMode?: boolean
  readonly initialSession?: FriendSession
  readonly embedded?: boolean
  readonly openingLine?: string
  readonly grammarDrill?: boolean
  readonly lessonRoom?:
    | 'introduction'
    | 'practice'
    | 'conversation'
    | 'interaction'
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

const sessionTypeKeys: Readonly<Record<FriendSessionType, MessageKey>> = {
  confidence: 'conversation.session_type.confidence',
  skill_focus: 'conversation.session_type.skill_focus',
  review: 'conversation.session_type.review',
}

const sessionStartKeys: Readonly<Record<FriendSessionType, MessageKey>> = {
  confidence: 'conversation.start',
  skill_focus: 'conversation.start.skill_focus',
  review: 'conversation.start.review',
}

function DelayedSilenceNudge({
  locale,
  localizationPolicy,
}: Pick<ConversationJourneyProps, 'locale' | 'localizationPolicy'>) {
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const timer = globalThis.setTimeout(() => setVisible(true), 8_000)

    return () => globalThis.clearTimeout(timer)
  }, [])

  if (!visible) {
    return null
  }

  return (
    <p lang={locale} role="status">
      <LocalizedMessage
        resolution={resolveInterfaceMessage(
          'conversation.confidence.silence_nudge',
          locale,
          localizationPolicy,
        )}
      />
    </p>
  )
}

export function ConversationJourney({
  locale,
  localizationPolicy,
  api = defaultConversationJourneyApi,
  createClientId = createRandomClientId,
  demoMode = false,
  initialSession,
  embedded = false,
  openingLine,
  grammarDrill = false,
  lessonRoom,
  onLiveAiUsageChanged,
  voiceUnavailableMessageKey,
}: ConversationJourneyProps) {
  const [state, setState] = useState<ConversationState>(
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
  const [sessionType, setSessionType] =
    useState<FriendSessionType>('confidence')
  const [listening, setListening] = useState(false)
  const [speakingReply, setSpeakingReply] = useState(false)
  const [avatarPlayback, setAvatarPlayback] = useState<{
    readonly timeline: NonNullable<
      NonNullable<FriendTurn['speech']>['model_avatar_viseme_timeline']
    >
    readonly timeMs: number
  } | null>(null)
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
        grammarDrill,
        locale: 'en-IN',
        onPlaybackChange: setAvatarPlayback,
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
    [api, demoMode, grammarDrill, onLiveAiUsageChanged],
  )

  useEffect(() => {
    if (
      openingSpoken.current ||
      initialSession === undefined ||
      openingLine === undefined ||
      openingLine.trim() === ''
    ) {
      return
    }

    openingSpoken.current = true
    speakModel(initialSession.id, openingLine, false)
    // The openingSpoken ref keeps this idempotent even if dependencies change.
  }, [initialSession, openingLine, speakModel])

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
    setAvatarPlayback(null)
    settleHold()
  }, [settleHold])

  const speakTurnAudio = async (
    turn: FriendTurn,
    signal?: AbortSignal,
  ) => {
    const speech = turn.speech
    const modelAudio = speech?.model_audio_base64
    const modelStream = speech?.model_audio_stream_path
    const guidanceAudio = speech?.guidance_audio_base64
    const guidanceStream = speech?.guidance_audio_stream_path
    const coachText = turn.assistant_reply ?? ''
    const modelLine = grammarDrill
      ? lessonDrillDisplayLine(coachText)
      : coachText
    const guidanceText = turn.priority_correction?.explanation_l1?.trim() ?? ''
    const guidanceLocale =
      turn.priority_correction?.explanation_locale ?? locale

    setSpeechError(false)
    setSpeakingReply(true)
    const hasModelAudio =
      (typeof modelAudio === 'string' && modelAudio !== '') ||
      (typeof modelStream === 'string' && modelStream !== '')
    const hasGuidanceAudio =
      (typeof guidanceAudio === 'string' && guidanceAudio !== '') ||
      (typeof guidanceStream === 'string' && guidanceStream !== '')
    if (
      hasModelAudio ||
      hasGuidanceAudio ||
      modelLine.trim() !== '' ||
      guidanceText !== ''
    ) {
      setLoopPhase('speaking')
    }

    try {
      let playedModel = false
      if (hasModelAudio && speech !== undefined && speech !== null) {
        try {
          await playConversationSpeechResult({
            onPlaybackChange: setAvatarPlayback,
            onPlaybackStarted: () => {
              const snapshot = markSpeakLoop('first_audio')
              if (snapshot !== null && api.recordSpeechLatency !== undefined) {
                void api.recordSpeechLatency(
                  turn.id,
                  speechLatencySample(snapshot, 'started'),
                  createClientId(),
                ).catch(() => undefined)
              }
            },
            purpose: 'model_en',
            signal,
            speech,
            text: grammarDrill ? modelLine : turn.assistant_reply,
          })
          playedModel = true
        } catch (error) {
          if (isAbortError(error) || signal?.aborted) {
            throw error
          }
        }
      }

      if (!playedModel && modelLine.trim() !== '') {
        markSpeakLoop('first_audio')
        await speakSessionSpeech({
          api,
          demoMode,
          grammarDrill,
          locale: 'en-IN',
          onPlaybackChange: setAvatarPlayback,
          purpose: 'model_en',
          sessionId: turn.conversation_session_id,
          text: modelLine,
        })
        playedModel = true
      }

      if (!playedModel) {
        const snapshot = completeSpeakLoop()
        if (snapshot !== null && api.recordSpeechLatency !== undefined) {
          void api.recordSpeechLatency(
            turn.id,
            speechLatencySample(snapshot, 'unavailable'),
            createClientId(),
          ).catch(() => undefined)
        }
      }

      if (signal?.aborted) {
        return
      }

      let playedGuidance = false
      if (hasGuidanceAudio && speech !== undefined && speech !== null) {
        try {
          await playConversationSpeechResult({
            onPlaybackChange: setAvatarPlayback,
            purpose: 'guidance_l1',
            signal,
            speech,
            text: guidanceText,
          })
          playedGuidance = true
        } catch {
          // Deferred guidance may not be ready yet; fall back to live TTS.
        }
      }

      if (!playedGuidance && guidanceText !== '') {
        await speakSessionSpeech({
          api,
          demoMode,
          locale: guidanceLocale,
          onPlaybackChange: setAvatarPlayback,
          purpose: 'guidance_l1',
          sessionId: turn.conversation_session_id,
          text: guidanceText,
        })
      }
    } catch (error) {
      if (isAbortError(error)) {
        const snapshot = completeSpeakLoop()
        if (snapshot !== null && api.recordSpeechLatency !== undefined) {
          void api.recordSpeechLatency(
            turn.id,
            speechLatencySample(snapshot, 'cancelled'),
            createClientId(),
          ).catch(() => undefined)
        }
      } else {
        setSpeechError(true)
        const snapshot = completeSpeakLoop()
        if (snapshot !== null && api.recordSpeechLatency !== undefined) {
          void api.recordSpeechLatency(
            turn.id,
            speechLatencySample(snapshot, 'failed'),
            createClientId(),
          ).catch(() => undefined)
        }
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
          scenario_key: sessionContent.scenarioKey,
          ui_locale: locale,
        },
        sessionCommandId.current,
      )
      .then((session) => {
        setState({
          kind: 'ready',
          session,
          stage: 'talk',
          completedTurns: 0,
        })
        speakModel(
          session.id,
          text('conversation.confidence.warmup_opener'),
          false,
        )
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
    markSpeakLoop('ack')
    api
      .createTurn(
        session.id,
        { prototype_utterance: nextUtterance },
        turnCommandId.current,
        controller.signal,
      )
      .then(async (turn) => {
        if (controller.signal.aborted) {
          return
        }

        onLiveAiUsageChanged?.()

        markSpeakLoop('turn_json_ready')
        const drillPatternAdvanced =
          turn.understood_intent === 'lesson_drill_pattern_accepted'
          || turn.understood_intent === 'lesson_drill_complete'
        const nextCompleted =
          turn.status === 'completed'
          && (!grammarDrill || drillPatternAdvanced)
            ? completedTurns + 1
            : completedTurns
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

  const send = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    submitTurn(utterance)
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

    void speakTurnAudio(state.turn)
  }

  const playModelLines = () => {
    if (state.kind !== 'ready' && state.kind !== 'result') {
      return
    }

    speakModel(state.session.id, sessionContent.listenLines.join(' '))
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
    sessionContent.listenLines[0]
  const drillRoom =
    lessonRoom === 'introduction' || lessonRoom === 'practice'
  const hideSpeakStages = lessonRoom !== undefined
  const drillPrompt =
    drillRoom
      ? lessonDrillDisplayLine(
          lastTurn?.assistant_reply ?? openingLine ?? '',
        )
      : null
  const lessonTitleKey: MessageKey | undefined =
    lessonRoom === 'introduction'
      ? 'level.path.part.introduction'
      : lessonRoom === 'practice'
        ? 'level.path.part.practice'
        : lessonRoom === 'conversation'
          ? 'level.path.part.conversation'
          : lessonRoom === 'interaction'
            ? 'level.path.part.interaction'
            : undefined
  const lessonHintKey: MessageKey | undefined =
    lessonRoom === 'introduction'
      ? 'level.path.part.introduction.hint'
      : lessonRoom === 'practice'
        ? 'level.path.part.practice.hint'
        : lessonRoom === 'conversation'
          ? 'level.path.part.conversation.hint'
          : lessonRoom === 'interaction'
            ? 'level.path.part.interaction.hint'
            : undefined

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
      data-lesson-room={lessonRoom}
      data-speak-loop={loopPhase}
    >
      <div className="conversation-journey__heading">
        <TutorPresence
          locale={locale}
          localizationPolicy={localizationPolicy}
          mood={tutorMood(loopPhase, speakingReply, listening)}
          size={embedded ? 'hero' : 'lg'}
          showName={false}
          enableAvatar3d
          avatarPlayback={avatarPlayback}
        />
        <SpeakLoopTimingOverlay />
        {drillRoom ? (
          <h2 id="conversation-title" className="visually-hidden">
            <LocalizedMessage
              resolution={message(
                lessonTitleKey ?? 'conversation.title',
              )}
            />
          </h2>
        ) : (
          <>
        <p className="conversation-journey__eyebrow">
          <LocalizedMessage
            resolution={message(
              lessonHintKey ??
                (demoMode
                  ? 'conversation.demo.eyebrow'
                  : 'conversation.eyebrow'),
            )}
          />
        </p>
        <h2 id="conversation-title">
          <LocalizedMessage
            resolution={message(
              lessonTitleKey ??
                (demoMode ? 'conversation.demo.title' : 'conversation.title'),
            )}
          />
        </h2>
          </>
        )}
      </div>

      {embedded ? null : (
      <aside
        className="conversation-journey__notice"
        aria-labelledby="conversation-disclosure"
      >
        {embedded ? (
          <details>
            <summary id="conversation-disclosure">
              <LocalizedMessage
                resolution={message(
                  demoMode
                    ? 'conversation.demo.disclosure_title'
                    : 'conversation.disclosure_title',
                )}
              />
            </summary>
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
          </details>
        ) : (
          <>
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
          </>
        )}
      </aside>
      )}

      {state.kind === 'idle' || state.kind === 'starting' ? (
        <div className="conversation-journey__start">
          <label htmlFor="friend-session-type">
            <LocalizedMessage
              resolution={message('conversation.session_type.label')}
            />
          </label>
          <select
            id="friend-session-type"
            value={sessionType}
            disabled={state.kind === 'starting'}
            onChange={(event) => {
              const selected = event.currentTarget.value as FriendSessionType
              setSessionType(selected)
              setCoachingMode(FRIEND_SESSION_CONTENT[selected].coachingMode)
            }}
          >
            {(Object.keys(FRIEND_SESSION_CONTENT) as FriendSessionType[]).map(
              (type) => (
                <option key={type} value={type}>
                  {text(sessionTypeKeys[type])}
                </option>
              ),
            )}
          </select>
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
                    : sessionStartKeys[sessionType],
              )}
            />
          </button>
        </div>
      ) : null}

      {session !== undefined &&
      stage !== undefined &&
      !hideSpeakStages ? (
        <p className="conversation-journey__progress" role="status">
          <LocalizedMessage
            resolution={message('conversation.confidence.progress')}
          />
          {': '}
          <LocalizedMessage resolution={message(stageKeys[stage])} />
        </p>
      ) : null}

      {session === undefined || embedded ? null : (
        <p className="conversation-journey__identity" lang="en-IN">
          <LocalizedMessage
            resolution={message('conversation.persona_disclosure')}
          />
        </p>
      )}

      {session !== undefined &&
      !embedded &&
      (stage === 'talk' || stage === 'warmup' || stage === 'listen') ? (
        <details className="conversation-journey__example">
          <summary>
            <LocalizedMessage
              resolution={message('conversation.confidence.example')}
            />
          </summary>
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
          <p lang={locale}>
            <LocalizedMessage
              resolution={message('conversation.confidence.listen_lead')}
            />
          </p>
          <ol className="conversation-journey__model-lines" lang="en-IN">
            {sessionContent.listenLines.map((line) => (
              <li key={line}>{line}</li>
            ))}
          </ol>
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
        </details>
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
          {demoMode ? null : (
            <>
              <VoicePracticePanel
                api={api}
                compact={drillRoom || embedded}
                createClientId={createClientId}
                locale={locale}
                onPreferTyping={typeInstead}
                onTranscriptSelected={(transcript) => {
                  setUtterance(transcript)
                  submitTurn(transcript)
                }}
                onLoopPhase={setLoopPhase}
                onCancelInFlight={abortInFlight}
                requireTranscriptConfirmation={false}
                resetEpoch={holdResetEpoch}
                sessionId={session.id}
                speakLoopPhase={loopPhase}
                text={text}
                unavailableMessageKey={voiceUnavailableMessageKey}
              />
            </>
          )}
          {drillPrompt !== null && drillPrompt !== '' ? (
            <p className="conversation-journey__drill-prompt" lang="en-IN">
              {drillPrompt}
            </p>
          ) : null}
          <SpeakChatLog
            chatTurns={chatTurns}
            drillMode={drillRoom}
            message={message}
            openingLine={openingLine}
          />
          {embedded || demoMode ? null : (
            <AiMemoryPanel
              api={api}
              createClientId={createClientId}
              locale={locale}
              localizationPolicy={localizationPolicy}
              text={text}
            />
          )}

          {drillRoom ? (
            <button
              className="ge-button ge-button--ghost"
              type="button"
              onClick={typeInstead}
            >
              <LocalizedMessage
                resolution={message('conversation.confidence.type_instead')}
              />
            </button>
          ) : (
          <div
            className="conversation-journey__controls"
            role="group"
            aria-label={text('conversation.confidence.controls_label')}
          >
            {drillRoom ? null : (
            <button
              className="ge-button"
              type="button"
              onClick={retryUtterance}
            >
              <LocalizedMessage
                resolution={message('conversation.confidence.retry')}
              />
            </button>
            )}
            <button className="ge-button" type="button" onClick={typeInstead}>
              <LocalizedMessage
                resolution={message('conversation.confidence.type_instead')}
              />
            </button>
            {drillRoom ? null : (
            <>
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
            {completedTurns >= sessionContent.minimumTurnsBeforeReflect ? (
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
            </>
            )}
          </div>
          )}

          {showHint ? (
            <p lang={locale} role="status">
              <LocalizedMessage
                resolution={message('conversation.confidence.hint_text')}
              />
            </p>
          ) : null}

          {state.kind === 'ready' &&
          utterance.trim() === '' &&
          !listening &&
          !drillRoom ? (
            <DelayedSilenceNudge
              locale={locale}
              localizationPolicy={localizationPolicy}
            />
          ) : null}

          {demoMode ? (
            <div className="conversation-journey__speech-actions">
              <button
                className="ge-button ge-button--primary"
                type="button"
                disabled={state.kind === 'sending'}
                onClick={listening ? stopListening : startListening}
              >
                <LocalizedMessage
                  resolution={message(
                    listening
                      ? 'conversation.demo.stop_listening'
                      : 'conversation.demo.speak_turn',
                  )}
                />
              </button>
              {listening ? (
                <p
                  className="conversation-journey__listening"
                  role="status"
                >
                  <LocalizedMessage
                    resolution={message('conversation.demo.listening')}
                  />
                </p>
              ) : null}
            </div>
          ) : null}

          {inputMode === 'type' || utterance.trim() !== '' ? (
            <form className="conversation-journey__form" onSubmit={send}>
              {inputMode === 'type' ? (
                <>
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
                    onChange={(event) =>
                      setUtterance(event.currentTarget.value)
                    }
                  />
                </>
              ) : (
                <p role="status" lang="en-IN">
                  {utterance}
                </p>
              )}
              <button
                className="ge-button ge-button--primary"
                type="submit"
                disabled={
                  state.kind === 'sending' || utterance.trim() === ''
                }
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
          ) : null}
        </>
      ) : null}

      {state.kind === 'result' && stage === 'talk' && !drillRoom ? (
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
  readonly drillMode?: boolean
  readonly message: (
    key: MessageKey,
    parameters?: MessageParameters,
  ) => ReturnType<typeof resolveInterfaceMessage>
  readonly openingLine?: string
}

function SpeakChatLog({
  chatTurns,
  drillMode = false,
  message,
  openingLine,
}: SpeakChatLogProps) {
  const hasOpening =
    !drillMode &&
    openingLine !== undefined &&
    openingLine.trim() !== ''
  const openingDisplay = openingLine

  if (!hasOpening && chatTurns.length === 0) {
    return null
  }

  return (
    <div className="conversation-journey__chat" role="log">
      {hasOpening ? (
        <ChatBubble role="teacher" message={message}>
          {openingDisplay}
        </ChatBubble>
      ) : null}
      {chatTurns.map((turn) => (
        <SpeakChatTurn
          drillMode={drillMode}
          key={turn.id}
          message={message}
          turn={turn}
        />
      ))}
    </div>
  )
}

type ChatBubbleProps = {
  readonly after?: ReactNode
  readonly children: ReactNode
  readonly message: SpeakChatLogProps['message']
  readonly role: 'teacher' | 'you'
}

function ChatBubble({ after, children, message, role }: ChatBubbleProps) {
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
      </div>
      {after}
    </div>
  )
}

type SpeakChatTurnProps = {
  readonly drillMode?: boolean
  readonly message: SpeakChatLogProps['message']
  readonly turn: FriendTurn
}

function SpeakChatTurn({ drillMode = false, message, turn }: SpeakChatTurnProps) {
  const correction = turn.priority_correction
  const explanation =
    correction?.explanation_l1?.trim() ??
    correction?.encouragement_l1?.trim() ??
    ''
  const showExplanation = correction !== undefined && explanation !== ''
  const teacherLine =
    turn.assistant_reply !== null && turn.assistant_reply.trim() !== ''
      ? drillMode
        ? lessonDrillDisplayLine(turn.assistant_reply)
        : turn.assistant_reply
      : null

  return (
    <>
      {turn.transcript !== null && turn.transcript.trim() !== '' ? (
        <ChatBubble
          after={
            drillMode && showExplanation ? (
              <TurnExplanationAside correction={correction!} message={message} />
            ) : null
          }
          message={message}
          role="you"
        >
          {turn.transcript}
        </ChatBubble>
      ) : null}
      {drillMode ? null : teacherLine !== null ? (
        <ChatBubble
          after={
            showExplanation ? (
              <TurnExplanationAside correction={correction!} message={message} />
            ) : null
          }
          message={message}
          role="teacher"
        >
          {teacherLine}
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
    <details className="conversation-journey__explanation" open>
      <summary
        aria-label={message('conversation.correction.explanation_label').text}
        className="conversation-journey__explanation-toggle"
        title={message('conversation.correction.explanation_label').text}
      >
        <LocalizedMessage
          resolution={message('conversation.correction.explanation_label')}
        />
      </summary>
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
