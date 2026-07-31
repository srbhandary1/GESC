import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import {
  useRef,
  useState,
  type FormEvent,
} from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import type {
  ConversationJourneyApi,
  FriendSession,
  FriendTurn,
  FriendTurnEvent,
} from './ConversationJourneyApi'
import { defaultConversationJourneyApi } from './WebConversationJourneyApi'
import { VoicePracticePanel } from './VoicePracticePanel'
import './ConversationJourney.css'

export interface ConversationJourneyProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly api?: ConversationJourneyApi
  readonly createClientId?: () => string
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

const createRandomClientId = () => crypto.randomUUID()

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
}: ConversationJourneyProps) {
  const [state, setState] = useState<ConversationState>({ kind: 'idle' })
  const [utterance, setUtterance] = useState('')
  const sessionCommandId = useRef(createClientId())
  const turnCommandId = useRef(createClientId())
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const text = (key: MessageKey) => message(key).text

  const start = () => {
    setState({ kind: 'starting' })
    api
      .startSession(
        {
          coaching_mode: 'gentle',
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
      })
      .catch(() => setState({ kind: 'error', session }))
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
    >
      <div className="conversation-journey__heading">
        <p className="conversation-journey__eyebrow">
          <LocalizedMessage
            resolution={message('conversation.eyebrow')}
          />
        </p>
        <h2 id="conversation-title">
          <LocalizedMessage resolution={message('conversation.title')} />
        </h2>
      </div>

      <aside
        className="conversation-journey__notice"
        aria-labelledby="conversation-disclosure"
      >
        <h3 id="conversation-disclosure">
          <LocalizedMessage
            resolution={message('conversation.disclosure_title')}
          />
        </h3>
        <p>
          <LocalizedMessage
            resolution={message('conversation.prototype_notice')}
          />
        </p>
        <p>
          <LocalizedMessage
            resolution={message('conversation.privacy_notice')}
          />
        </p>
      </aside>

      {state.kind === 'idle' || state.kind === 'starting' ? (
        <button
          type="button"
          disabled={state.kind === 'starting'}
          onClick={start}
        >
          <LocalizedMessage
            resolution={message(
              state.kind === 'starting'
                ? 'conversation.starting'
                : 'conversation.start',
            )}
          />
        </button>
      ) : null}

      {session === undefined ? null : (
        <>
          <p
            className="conversation-journey__identity"
            lang="en-IN"
          >
            {session.persona.disclosure}
          </p>
          <VoicePracticePanel
            api={api}
            createClientId={createClientId}
            locale={locale}
            onTranscriptSelected={setUtterance}
            sessionId={session.id}
            text={text}
          />
          <form onSubmit={send}>
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
            <button
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
              <p>
                <LocalizedMessage
                  resolution={message(
                    'conversation.speech_unavailable',
                  )}
                />
              </p>
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

      {state.kind === 'error' ? (
        <p role="alert">
          <LocalizedMessage resolution={message('conversation.error')} />
        </p>
      ) : null}
    </section>
  )
}
