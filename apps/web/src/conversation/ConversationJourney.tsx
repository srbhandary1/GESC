import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import {
  useEffect,
  useRef,
  useState,
  type FormEvent,
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
import {
  CONFIDENCE_LISTEN_LINES,
  CONFIDENCE_MIN_TURNS_BEFORE_REFLECT,
} from './confidenceContent'
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

export function ConversationJourney({
  locale,
  localizationPolicy,
  api = defaultConversationJourneyApi,
  createClientId = createRandomClientId,
  demoMode = false,
}: ConversationJourneyProps) {
  const [state, setState] = useState<ConversationState>({ kind: 'idle' })
  const [utterance, setUtterance] = useState('')
  const [coachingMode, setCoachingMode] = useState<
    'gentle' | 'tutor' | 'fluency'
  >('gentle')
  const [listening, setListening] = useState(false)
  const [speakingReply, setSpeakingReply] = useState(false)
  const [speechError, setSpeechError] = useState(false)
  const [showHint, setShowHint] = useState(false)
  const [showSilenceNudge, setShowSilenceNudge] = useState(false)
  const [inputMode, setInputMode] = useState<'type' | 'speak'>('type')
  const sessionCommandId = useRef(createClientId())
  const turnCommandId = useRef(createClientId())
  const stopRecognition = useRef<(() => void) | null>(null)
  const utteranceFieldRef = useRef<HTMLTextAreaElement | null>(null)
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const text = (key: MessageKey) => message(key).text

  useEffect(() => {
    return () => {
      stopRecognition.current?.()
      stopDemoSpeech()
    }
  }, [])

  useEffect(() => {
    if (state.kind !== 'ready' || state.stage !== 'talk') {
      setShowSilenceNudge(false)
      return
    }

    if (utterance.trim() !== '' || listening) {
      setShowSilenceNudge(false)
      return
    }

    const timer = globalThis.setTimeout(() => {
      setShowSilenceNudge(true)
    }, 8_000)

    return () => globalThis.clearTimeout(timer)
  }, [state, utterance, listening])

  const speakEnglish = (reply: string) => {
    const trimmed = reply.trim()
    if (trimmed === '') {
      return
    }

    setSpeechError(false)
    setSpeakingReply(true)

    const playback = demoMode
      ? speakDemoText(trimmed, 'en-IN')
      : speakWithBrowserSpeech(trimmed, 'en-IN')

    void playback
      .catch(() => setSpeechError(true))
      .finally(() => setSpeakingReply(false))
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
          scenario_key: 'everyday-friend-prototype',
          ui_locale: locale,
        },
        sessionCommandId.current,
      )
      .then((session) =>
        setState({
          kind: 'ready',
          session,
          stage: 'warmup',
          completedTurns: 0,
        }),
      )
      .catch(() => setState({ kind: 'error' }))
  }

  const send = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (
      (state.kind !== 'ready' && state.kind !== 'result') ||
      state.stage !== 'talk' ||
      utterance.trim() === ''
    ) {
      return
    }

    const session = state.session
    const completedTurns =
      state.kind === 'result' ? state.completedTurns : state.completedTurns
    const prototypeUtterance = utterance.trim()
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
        { prototype_utterance: prototypeUtterance },
        turnCommandId.current,
      )
      .then(async (turn) => {
        const events = await api.events(turn.events_url)
        const nextCompleted =
          turn.status === 'completed' ? completedTurns + 1 : completedTurns
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

        if (turn.status === 'completed' && turn.assistant_reply !== null) {
          speakEnglish(turn.assistant_reply)
        }
      })
      .catch(() =>
        setState({
          kind: 'error',
          session,
          stage: 'talk',
          completedTurns,
        }),
      )
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
    speakEnglish(CONFIDENCE_LISTEN_LINES.join(' '))
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
    CONFIDENCE_LISTEN_LINES[0]

  return (
    <section
      className="conversation-journey"
      aria-labelledby="conversation-title"
      data-demo-speech={demoMode ? 'browser' : undefined}
      data-confidence-stage={stage}
    >
      <div className="conversation-journey__heading">
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
      </aside>

      {state.kind === 'idle' || state.kind === 'starting' ? (
        <div className="conversation-journey__start">
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
          <ol className="conversation-journey__model-lines" lang="en-IN">
            {CONFIDENCE_LISTEN_LINES.map((line) => (
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
          {demoMode ? null : (
            <>
              <VoicePracticePanel
                api={api}
                createClientId={createClientId}
                locale={locale}
                onTranscriptSelected={setUtterance}
                sessionId={session.id}
                text={text}
              />
              <AiMemoryPanel
                api={api}
                createClientId={createClientId}
                locale={locale}
                localizationPolicy={localizationPolicy}
                text={text}
              />
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
              <div className="conversation-journey__speech-actions">
                <button
                  className="ge-button"
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
              <h3 id="conversation-reply">
                <LocalizedMessage
                  resolution={message('conversation.reply_title')}
                />
              </h3>
              <dl>
                <div>
                  <dt>
                    <LocalizedMessage
                      resolution={message('conversation.transcript_label')}
                    />
                  </dt>
                  <dd lang="en-IN">{state.turn.transcript}</dd>
                </div>
                <div>
                  <dt>
                    <LocalizedMessage
                      resolution={message('conversation.friend_label')}
                    />
                  </dt>
                  <dd lang="en-IN">{state.turn.assistant_reply}</dd>
                </div>
              </dl>
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
                  {state.turn.priority_correction.retry_recommended ? (
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

      {state.kind === 'error' || speechError ? (
        <p role="alert">
          <LocalizedMessage
            resolution={message(
              speechError && demoMode
                ? 'conversation.demo.recognition_unavailable'
                : 'conversation.error',
            )}
          />
        </p>
      ) : null}
    </section>
  )
}
