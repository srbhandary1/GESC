import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type FormEvent,
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
import { playBase64Audio } from './playBase64Audio'
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
import { isAbortError } from '../shared/isAbortError'
import { stopSpeechPlayback } from '../shared/stopSpeechPlayback'
import {
  FRIEND_SESSION_CONTENT,
  type FriendSessionType,
} from './confidenceContent'
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
  const [speechError, setSpeechError] = useState(false)
  const [showHint, setShowHint] = useState(false)
  const [inputMode, setInputMode] = useState<'type' | 'speak'>('speak')
  const [loopPhase, setLoopPhase] = useState<SpeakLoopPhase>('waiting')
  const [holdResetEpoch, setHoldResetEpoch] = useState(0)
  const sessionCommandId = useRef(createClientId())
  const turnCommandId = useRef(createClientId())
  const stopRecognition = useRef<(() => void) | null>(null)
  const utteranceFieldRef = useRef<HTMLTextAreaElement | null>(null)
  const openingSpoken = useRef(false)
  const inFlightAbort = useRef<AbortController | null>(null)
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const text = (key: MessageKey) => message(key).text
  const sessionContent = FRIEND_SESSION_CONTENT[sessionType]

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
        .catch(() => {
          if (reportError) {
            setSpeechError(true)
          }
        })
        .finally(() => setSpeakingReply(false))
    },
    [api, demoMode],
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
      .catch(() => setState({ kind: 'error' }))
  }

  const submitTurn = (prototypeUtterance: string) => {
    if (
      (state.kind !== 'ready' && state.kind !== 'result') ||
      state.stage !== 'talk' ||
      prototypeUtterance.trim() === ''
    ) {
      return
    }

    const session = state.session
    const completedTurns = state.completedTurns
    const nextUtterance = prototypeUtterance.trim()
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
      lastTurn: state.kind === 'result' ? state.turn : state.lastTurn,
    })
    setSpeechError(false)
    setShowHint(false)
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
      </div>

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
          {openingLine !== undefined && openingLine.trim() !== '' ? (
            <div className="conversation-journey__chat">
              <dl className="conversation-journey__bubble conversation-journey__bubble--teacher">
                <div>
                  <dt>
                    <LocalizedMessage
                      resolution={message('conversation.chat.teacher')}
                    />
                  </dt>
                  <dd lang="en-IN">{openingLine}</dd>
                </div>
              </dl>
            </div>
          ) : null}
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
          </div>

          {showHint ? (
            <p lang={locale} role="status">
              <LocalizedMessage
                resolution={message('conversation.confidence.hint_text')}
              />
            </p>
          ) : null}

          {state.kind === 'ready' &&
          utterance.trim() === '' &&
          !listening ? (
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

      {state.kind === 'result' && stage === 'talk' ? (
        <article
          className="conversation-journey__result"
          aria-labelledby="conversation-reply"
        >
          {state.turn.status === 'completed' ? (
            <>
              <h3 id="conversation-reply">
                <LocalizedMessage
                  resolution={message('conversation.reply_title')}
                />
              </h3>
              <div className="conversation-journey__chat">
              <dl className="conversation-journey__bubble conversation-journey__bubble--you">
                <div>
                  <dt>
                    <LocalizedMessage
                      resolution={message('conversation.chat.you')}
                    />
                  </dt>
                  <dd lang="en-IN">{state.turn.transcript}</dd>
                </div>
              </dl>
              <dl className="conversation-journey__bubble conversation-journey__bubble--teacher">
                <div>
                  <dt>
                    <LocalizedMessage
                      resolution={message('conversation.chat.teacher')}
                    />
                  </dt>
                  <dd lang="en-IN">{state.turn.assistant_reply}</dd>
                </div>
              </dl>
              </div>
              {state.turn.priority_correction ? (
                <aside
                  className="conversation-journey__correction"
                  aria-labelledby="conversation-correction"
                >
                  <h3 id="conversation-correction">
                    <LocalizedMessage
                      resolution={message('conversation.correction.title')}
                    />
                  </h3>
                  {state.turn.priority_correction.needed ? (
                    <dl>
                      <div>
                        <dt>
                          <LocalizedMessage
                            resolution={message(
                              'conversation.correction.suggestion_label',
                            )}
                          />
                        </dt>
                        <dd lang="en-IN">
                          {state.turn.priority_correction.correction_en}
                        </dd>
                      </div>
                      <div>
                        <dt>
                          <LocalizedMessage
                            resolution={message(
                              'conversation.correction.explanation_label',
                            )}
                          />
                        </dt>
                        <dd
                          lang={
                            state.turn.priority_correction.explanation_locale
                          }
                        >
                          {state.turn.priority_correction.explanation_l1}
                        </dd>
                      </div>
                    </dl>
                  ) : (
                    <p
                      lang={
                        state.turn.priority_correction.explanation_locale
                      }
                    >
                      {state.turn.priority_correction.encouragement_l1 ??
                        state.turn.priority_correction.explanation_l1}
                    </p>
                  )}
                  {state.turn.priority_correction.needed &&
                  state.turn.priority_correction.retry_recommended ? (
                    <p>
                      <LocalizedMessage
                        resolution={message('conversation.correction.retry')}
                      />
                    </p>
                  ) : null}
                </aside>
              ) : null}
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

          <h4>
            <LocalizedMessage
              resolution={message('conversation.events_title')}
            />
          </h4>
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
        </article>
      ) : null}

      {state.kind === 'error' ? (
        <p role="alert">
          <LocalizedMessage resolution={message('conversation.error')} />
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
