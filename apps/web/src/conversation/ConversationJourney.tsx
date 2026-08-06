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
  browserSpeechSynthesisAvailable,
  recognizeWithBrowserSpeech,
  speakWithBrowserSpeech,
  stopBrowserSpeech,
} from '../demo/browserSpeech'
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
import './ConversationJourney.css'

export interface ConversationJourneyProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly api?: ConversationJourneyApi
  readonly createClientId?: () => string
  /**
   * When true, enables on-device browser TTS/STT for the disclosed demo.
   * Production conversation paths remain unchanged.
   */
  readonly demoMode?: boolean
}

type ConversationState =
  | { readonly kind: 'idle' }
  | { readonly kind: 'starting' }
  | { readonly kind: 'ready'; readonly session: FriendSession }
  | {
      readonly kind: 'sending'
      readonly session: FriendSession
    }
  | {
      readonly kind: 'result'
      readonly session: FriendSession
      readonly turn: FriendTurn
      readonly events: readonly FriendTurnEvent[]
    }
  | { readonly kind: 'error'; readonly session?: FriendSession }

const eventMessages: Readonly<Record<string, MessageKey>> = {
  accepted: 'conversation.state.accepted',
  completed: 'conversation.state.completed',
  failed: 'conversation.state.failed',
  generating_reply: 'conversation.state.generating_reply',
  synthesizing: 'conversation.state.synthesizing',
  transcribing: 'conversation.state.transcribing',
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
  const sessionCommandId = useRef(createClientId())
  const turnCommandId = useRef(createClientId())
  const stopRecognition = useRef<(() => void) | null>(null)
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const text = (key: MessageKey) => message(key).text

  useEffect(() => {
    return () => {
      stopRecognition.current?.()
      stopBrowserSpeech()
    }
  }, [])

  const speakReply = (reply: string) => {
    if (!demoMode || !browserSpeechSynthesisAvailable()) {
      return
    }

    setSpeechError(false)
    setSpeakingReply(true)
    void speakWithBrowserSpeech(reply, 'en-IN')
      .catch(() => setSpeechError(true))
      .finally(() => setSpeakingReply(false))
  }

  const start = () => {
    setState({ kind: 'starting' })
    api
      .startSession(
        {
          coaching_mode: coachingMode,
          persona_key: 'patient-friend-prototype',
          scenario_key: 'everyday-friend-prototype',
        },
        sessionCommandId.current,
      )
      .then((session) => setState({ kind: 'ready', session }))
      .catch(() => setState({ kind: 'error' }))
  }

  const send = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (
      (state.kind !== 'ready' && state.kind !== 'result') ||
      utterance.trim() === ''
    ) {
      return
    }

    const session = state.session
    const prototypeUtterance = utterance.trim()
    setState({ kind: 'sending', session })
    setSpeechError(false)
    api
      .createTurn(
        session.id,
        { prototype_utterance: prototypeUtterance },
        turnCommandId.current,
      )
      .then(async (turn) => {
        const events = await api.events(turn.events_url)
        setState({ events, kind: 'result', session, turn })
        setUtterance('')
        turnCommandId.current = createClientId()

        if (turn.status === 'completed' && turn.assistant_reply !== null) {
          speakReply(turn.assistant_reply)
        }
      })
      .catch(() => setState({ kind: 'error', session }))
  }

  const startListening = () => {
    if (!demoMode || !browserSpeechRecognitionAvailable()) {
      setSpeechError(true)
      return
    }

    setSpeechError(false)
    setListening(true)
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

    speakReply(state.turn.assistant_reply)
  }

  const session =
    state.kind === 'ready' ||
    state.kind === 'sending' ||
    state.kind === 'result'
      ? state.session
      : state.kind === 'error'
        ? state.session
        : undefined

  return (
    <section
      className="conversation-journey"
      aria-labelledby="conversation-title"
      data-demo-speech={demoMode ? 'browser' : undefined}
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

      {session === undefined ? null : (
        <>
          <p
            className="conversation-journey__identity"
            lang="en-IN"
          >
            {session.persona.disclosure}
          </p>
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
          <form
            className="conversation-journey__form"
            onSubmit={send}
          >
            <label htmlFor="prototype-utterance">
              <LocalizedMessage
                resolution={message('conversation.utterance_label')}
              />
            </label>
            <textarea
              id="prototype-utterance"
              maxLength={500}
              required
              rows={4}
              value={utterance}
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
      )}

      {state.kind === 'result' ? (
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
                      resolution={message(
                        'conversation.transcript_label',
                      )}
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
                      <dd lang="kn-IN">
                        {state.turn.priority_correction.explanation_kn}
                      </dd>
                    </div>
                  </dl>
                  {state.turn.priority_correction.retry_recommended ? (
                    <p>
                      <LocalizedMessage
                        resolution={message(
                          'conversation.correction.retry',
                        )}
                      />
                    </p>
                  ) : null}
                </aside>
              ) : null}
              {demoMode ? (
                <div className="conversation-journey__speech-actions">
                  <button
                    className="ge-button ge-button--primary"
                    type="button"
                    disabled={
                      speakingReply ||
                      !browserSpeechSynthesisAvailable() ||
                      state.turn.assistant_reply === null
                    }
                    onClick={playReply}
                  >
                    <LocalizedMessage
                      resolution={message(
                        'conversation.demo.listen_reply',
                      )}
                    />
                  </button>
                  <p>
                    <LocalizedMessage
                      resolution={message(
                        'conversation.demo.browser_speech_notice',
                      )}
                    />
                  </p>
                </div>
              ) : (
                <p>
                  <LocalizedMessage
                    resolution={message(
                      'conversation.speech_unavailable',
                    )}
                  />
                </p>
              )}
            </>
          ) : (
            <h3 id="conversation-reply">
              <LocalizedMessage
                resolution={message('conversation.failed')}
              />
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
