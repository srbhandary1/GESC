import type {
  MessageKey,
  MessageParameters,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import type {
  ConversationJourneyApi,
  FriendSession,
  FriendTurn,
  StartFriendSessionInput,
} from './ConversationJourneyApi'
import { defaultConversationJourneyApi } from './WebConversationJourneyApi'
import { conversationErrorMessageKey } from './conversationErrorMessage'
import { playBase64Audio } from './playBase64Audio'
import './ConversationJourney.css'

export interface RoleplayJourneyProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly api?: ConversationJourneyApi
  readonly createClientId?: () => string
  readonly onBack?: () => void
  readonly onLiveAiUsageChanged?: () => void
}

type RoleplayStage = 'observe' | 'rehearse' | 'perform' | 'reflect'

type RoleplayState =
  | { readonly kind: 'idle' }
  | { readonly kind: 'starting' }
  | {
      readonly kind: 'ready'
      readonly session: FriendSession
    }
  | {
      readonly kind: 'advancing'
      readonly session: FriendSession
    }
  | {
      readonly kind: 'error'
      readonly session?: FriendSession
      readonly messageKey: MessageKey
    }

const STAGE_ORDER: readonly RoleplayStage[] = [
  'observe',
  'rehearse',
  'perform',
  'reflect',
]

const STAGE_TITLE_KEYS: Readonly<Record<RoleplayStage, MessageKey>> = {
  observe: 'conversation.roleplay.stage.observe',
  rehearse: 'conversation.roleplay.stage.rehearse',
  perform: 'conversation.roleplay.stage.perform',
  reflect: 'conversation.roleplay.stage.reflect',
}

const STAGE_LEAD_KEYS: Readonly<Record<RoleplayStage, MessageKey>> = {
  observe: 'conversation.roleplay.observe_lead',
  rehearse: 'conversation.roleplay.rehearse_lead',
  perform: 'conversation.roleplay.perform_lead',
  reflect: 'conversation.roleplay.reflect_lead',
}

const NEXT_STAGE: Readonly<
  Partial<Record<RoleplayStage, RoleplayStage>>
> = {
  observe: 'rehearse',
  rehearse: 'perform',
  perform: 'reflect',
}

export function RoleplayJourney({
  locale,
  localizationPolicy,
  api = defaultConversationJourneyApi,
  createClientId = createRandomClientId,
  onBack,
  onLiveAiUsageChanged,
}: RoleplayJourneyProps) {
  const [state, setState] = useState<RoleplayState>({ kind: 'idle' })
  const [utterance, setUtterance] = useState('')
  const [performed, setPerformed] = useState(false)
  const [lastTurn, setLastTurn] = useState<FriendTurn | null>(null)
  const [inputMode, setInputMode] = useState<'type' | 'speak'>('speak')
  const message = (
    key: MessageKey,
    parameters: MessageParameters = {},
  ) => resolveInterfaceMessage(key, locale, localizationPolicy, parameters)
  const text = (key: MessageKey, parameters: MessageParameters = {}) =>
    message(key, parameters).text

  const playEnglish = (sessionId: string, lines: readonly string[]) => {
    void speakSessionSpeech({
      api,
      locale: 'en-IN',
      purpose: 'model_en',
      sessionId,
      text: lines.join(' '),
    }).catch(() => undefined)
  }

  const start = async () => {
    setState({ kind: 'starting' })
    setUtterance('')
    setPerformed(false)
    setLastTurn(null)

    try {
      const session = await api.startSession(
        {
          coaching_mode: 'gentle',
          persona_key: 'clinic-reception-roleplay-prototype',
          scenario_key: 'clinic-reception-roleplay-scenario',
          ui_locale: locale,
        },
        createClientId(),
      )

      const stage = asRoleplayStage(session.stage)
      setState({ kind: 'ready', session, catalogId: selected.id })
      if (stage === 'observe') {
        playEnglish(session.id, selected.pedagogy.observeModel)
      } else if (stage === 'rehearse') {
        playEnglish(session.id, selected.pedagogy.rehearseChunks)
      } else if (stage === 'perform') {
        playEnglish(session.id, [selected.pedagogy.performPrompt])
      }
    } catch (error: unknown) {
      setState({
        kind: 'error',
        messageKey: conversationErrorMessageKey(
          error,
          'conversation.roleplay.error',
        ),
      })
    }
  }

  const advance = async (session: FriendSession) => {
    const current = asRoleplayStage(session.stage)
    const next = current === null ? null : (NEXT_STAGE[current] ?? null)

    if (next === null || api.advanceStage === undefined) {
      setState({
        kind: 'error',
        session,
        messageKey: 'conversation.roleplay.error',
      })
      return
    }

    setState({ kind: 'advancing', session })

    try {
      const updated = await api.advanceStage(
        session.id,
        { to_stage: next },
        createClientId(),
      )
      setState({ kind: 'ready', session: updated, catalogId })
    } catch (error: unknown) {
      setState({
        kind: 'error',
        session,
        catalogId,
        messageKey: conversationErrorMessageKey(
          error,
          'conversation.roleplay.error',
        ),
      })
    }
  }

  const sendPerformTurn = async (
    session: FriendSession,
    catalogId: string,
    spokenText = utterance.trim(),
  ) => {
    const prototypeUtterance = spokenText.trim()
    if (prototypeUtterance === '') {
      return
    }

    setState({ kind: 'advancing', session, catalogId })

    try {
      const turn = await api.createTurn(
        session.id,
        { prototype_utterance: prototypeUtterance },
        createClientId(),
      )
      setLastTurn(turn)
      setPerformed(turn.status === 'completed')
      setUtterance('')
      setState({ kind: 'ready', session, catalogId })
      onLiveAiUsageChanged?.()
      void playRoleplayTurn(turn).catch(() => undefined)
    } catch (error: unknown) {
      setState({
        kind: 'error',
        session,
        catalogId,
        messageKey: conversationErrorMessageKey(
          error,
          'conversation.roleplay.error',
        ),
      })
    }
  }

  const session =
    state.kind === 'ready' ||
    state.kind === 'advancing' ||
    (state.kind === 'error' && state.session !== undefined)
      ? state.session
      : undefined
  const stage = asRoleplayStage(session?.stage ?? null)
  const nextStage = stage === null ? null : (NEXT_STAGE[stage] ?? null)

  return (
    <section
      aria-labelledby="roleplay-journey-title"
      className="conversation-journey"
      data-mode="roleplay"
      data-review-status="synthetic_unreviewed"
    >
      <header className="conversation-journey__heading">
        <p className="conversation-journey__eyebrow">
          <LocalizedMessage
            resolution={message('conversation.roleplay.eyebrow')}
          />
        </p>
        <h1 id="roleplay-journey-title">
          <LocalizedMessage
            resolution={message('conversation.roleplay.title')}
          />
        </h1>
        {onBack ? (
          <button
            className="conversation-journey__secondary"
            onClick={onBack}
            type="button"
          >
            <LocalizedMessage
              resolution={message('conversation.roleplay.back')}
            />
          </button>
        ) : null}
      </header>

      <div className="conversation-journey__notice">
        <h2>
          <LocalizedMessage
            resolution={message('conversation.roleplay.disclosure_title')}
          />
        </h2>
        <p>
          <LocalizedMessage
            resolution={message('conversation.roleplay.prototype_notice')}
          />
        </p>
        <p>
          <LocalizedMessage
            resolution={message('conversation.roleplay.privacy_notice')}
          />
        </p>
      </div>

      {state.kind === 'idle' || state.kind === 'starting' ? (
        <div className="conversation-journey__start">
          <button
            disabled={state.kind === 'starting'}
            onClick={() => {
              void start()
            }}
            type="button"
          >
            <LocalizedMessage
              resolution={message(
                state.kind === 'starting'
                  ? 'conversation.roleplay.starting'
                  : 'conversation.roleplay.start',
              )}
            />
          </button>
        </div>
      ) : null}

      {state.kind === 'error' ? (
        <p className="conversation-journey__error" role="alert">
          <LocalizedMessage resolution={message(state.messageKey)} />
        </p>
      ) : null}

      {session !== undefined && stage !== null ? (
        <div className="conversation-journey__stage">
          <p className="conversation-journey__progress">
            <LocalizedMessage
              resolution={message('conversation.roleplay.progress')}
            />
            {': '}
            {STAGE_ORDER.map((item) => (
              <span
                data-current={item === stage ? 'true' : 'false'}
                key={item}
              >
                <LocalizedMessage
                  resolution={message(STAGE_TITLE_KEYS[item])}
                />
                {item === 'reflect' ? '' : ' → '}
              </span>
            ))}
          </p>
          <h2>
            <LocalizedMessage
              resolution={message(STAGE_TITLE_KEYS[stage])}
            />
          </h2>
          <p lang={locale}>
            <LocalizedMessage
              resolution={message(STAGE_LEAD_KEYS[stage])}
            />
          </p>
          <p lang="en-IN">{session.persona.disclosure}</p>

          {stage === 'observe' ? (
            <>
              <h3>
                <LocalizedMessage
                  resolution={message('conversation.roleplay.model_title')}
                />
              </h3>
              <ul className="conversation-journey__model-lines">
                {selectedPedagogy.observeModel.map((line) => (
                  <li key={line} lang="en-IN">
                    {line}
                  </li>
                ))}
              </ul>
              <button
                className="ge-button"
                onClick={() => {
                  playEnglish(session.id, selectedPedagogy.observeModel)
                }}
                type="button"
              >
                <LocalizedMessage
                  resolution={message('conversation.confidence.listen_play')}
                />
              </button>
            </>
          ) : null}

          {stage === 'rehearse' ? (
            <>
              <h3>
                <LocalizedMessage
                  resolution={message(
                    'conversation.roleplay.rehearse_chunks_title',
                  )}
                />
              </h3>
              <ul className="conversation-journey__model-lines">
                {selectedPedagogy.rehearseChunks.map((chunk) => (
                  <li key={chunk} lang="en-IN">
                    {chunk}
                  </li>
                ))}
              </ul>
              <button
                className="ge-button"
                onClick={() => {
                  playEnglish(session.id, selectedPedagogy.rehearseChunks)
                }}
                type="button"
              >
                <LocalizedMessage
                  resolution={message('conversation.confidence.listen_play')}
                />
              </button>
            </>
          ) : null}

          {session.objectives !== undefined && session.objectives.length > 0 ? (
            <ul className="conversation-journey__model-lines">
              {session.objectives.map((objective) => (
                <li key={objective.id} lang={locale}>
                  {objective.title}
                </li>
              ))}
            </ul>
          ) : null}

          {stage === 'perform' ? (
            <>
              <p lang="en-IN">{selectedPedagogy.performPrompt}</p>
              <details className="conversation-journey__example">
                <summary>
                  <LocalizedMessage
                    resolution={message('conversation.roleplay.example')}
                  />
                </summary>
                <h3>
                  <LocalizedMessage
                    resolution={message('conversation.roleplay.model_title')}
                  />
                </h3>
                <ul className="conversation-journey__model-lines">
                  {selectedPedagogy.observeModel.map((line) => (
                    <li key={line} lang="en-IN">
                      {line}
                    </li>
                  ))}
                </ul>
                <button
                  className="ge-button"
                  onClick={() => {
                    if (session === undefined) {
                      return
                    }
                    playEnglish(session.id, selectedPedagogy.observeModel)
                  }}
                  type="button"
                >
                  <LocalizedMessage
                    resolution={message('conversation.confidence.listen_play')}
                  />
                </button>
                <h3>
                  <LocalizedMessage
                    resolution={message(
                      'conversation.roleplay.rehearse_chunks_title',
                    )}
                  />
                </h3>
                <ul className="conversation-journey__model-lines">
                  {selectedPedagogy.rehearseChunks.map((chunk) => (
                    <li key={chunk} lang="en-IN">
                      {chunk}
                    </li>
                  ))}
                </ul>
                <button
                  className="ge-button"
                  onClick={() => {
                    if (session === undefined) {
                      return
                    }
                    playEnglish(session.id, selectedPedagogy.rehearseChunks)
                  }}
                  type="button"
                >
                  <LocalizedMessage
                    resolution={message('conversation.confidence.listen_play')}
                  />
                </button>
              </details>
            </>
          ) : null}

          {stage === 'perform' && session !== undefined ? (
            <>
              <VoicePracticePanel
                api={api}
                createClientId={createClientId}
                locale={locale}
                onPreferTyping={() => setInputMode('type')}
                onTranscriptSelected={(transcript) => {
                  setUtterance(transcript)
                  void sendPerformTurn(session, resolvedCatalogId, transcript)
                }}
                sessionId={session.id}
                text={text}
              />
              <button
                className="ge-button"
                type="button"
                onClick={() => setInputMode('type')}
              >
                <LocalizedMessage
                  resolution={message('conversation.confidence.type_instead')}
                />
              </button>
            </>
          ) : null}

          {stage === 'perform' && lastTurn !== null ? (
            <article
              aria-labelledby="roleplay-perform-result"
              className="conversation-journey__result"
            >
              <h3 id="roleplay-perform-result">
                <LocalizedMessage
                  resolution={message('conversation.reply_title')}
                />
              </h3>
              {lastTurn.transcript !== null ? (
                <p lang="en-IN">
                  <strong>
                    <LocalizedMessage
                      resolution={message('conversation.chat.you')}
                    />
                    {': '}
                  </strong>
                  {lastTurn.transcript}
                </p>
              ) : null}
              {lastTurn.assistant_reply !== null ? (
                <p lang="en-IN">
                  <strong>
                    <LocalizedMessage
                      resolution={message('conversation.chat.teacher')}
                    />
                    {': '}
                  </strong>
                  {lastTurn.assistant_reply}
                </p>
              ) : null}
              {lastTurn.priority_correction?.explanation_l1 ? (
                <p lang={lastTurn.priority_correction.explanation_locale}>
                  {lastTurn.priority_correction.explanation_l1}
                </p>
              ) : null}
            </article>
          ) : null}

          {stage === 'perform' &&
          (inputMode === 'type' || utterance.trim() !== '') ? (
            <form
              className="conversation-journey__controls"
              onSubmit={(event) => {
                event.preventDefault()
                if (session === undefined) {
                  return
                }
                void sendPerformTurn(session, resolvedCatalogId)
              }}
            >
              {inputMode === 'type' ? (
                <>
                  <label htmlFor="roleplay-utterance">
                    <LocalizedMessage
                      resolution={message('conversation.utterance_label')}
                    />
                  </label>
                  <textarea
                    id="roleplay-utterance"
                    onChange={(event) =>
                      setUtterance(event.currentTarget.value)
                    }
                    required
                    rows={3}
                    value={utterance}
                  />
                </>
              ) : (
                <p role="status" lang="en-IN">
                  {utterance}
                </p>
              )}
              <button
                disabled={
                  state.kind === 'advancing' || utterance.trim() === ''
                }
                type="submit"
              >
                <LocalizedMessage
                  resolution={message(
                    state.kind === 'advancing'
                      ? 'conversation.sending'
                      : 'conversation.send',
                  )}
                />
              </button>
            </form>
          ) : null}

          {stage === 'reflect' &&
          session.feedback !== undefined &&
          session.feedback.length > 0 ? (
            <div className="conversation-journey__result">
              <h3>
                <LocalizedMessage
                  resolution={message(
                    'conversation.roleplay.feedback_title',
                  )}
                />
              </h3>
              <ul className="conversation-journey__model-lines">
                {session.feedback.map((item) => (
                  <li key={item.id}>
                    {item.completion_state}
                    {' · '}
                    <code>{item.evidence_digest.slice(0, 12)}</code>
                  </li>
                ))}
              </ul>
              <p>
                <LocalizedMessage
                  resolution={message(
                    'conversation.roleplay.reflect_summary',
                  )}
                />
              </p>
            </div>
          ) : null}

          {nextStage !== null ? (
            <div className="conversation-journey__controls">
              <button
                disabled={state.kind === 'advancing'}
                onClick={() => {
                  void advance(session)
                }}
                type="button"
              >
                <LocalizedMessage
                  resolution={message(
                    state.kind === 'advancing'
                      ? 'conversation.roleplay.advancing'
                      : 'conversation.roleplay.continue',
                  )}
                />
              </button>
            </div>
          ) : null}
        </div>
      ) : null}
    </section>
  )
}

async function playRoleplayTurn(turn: FriendTurn): Promise<void> {
  const speech = turn.speech
  const clips = [
    [speech?.model_audio_base64, speech?.model_audio_mime_type],
    [speech?.guidance_audio_base64, speech?.guidance_audio_mime_type],
  ] as const

  for (const [audio, mimeType] of clips) {
    if (typeof audio === 'string' && audio !== '') {
      await playBase64Audio(audio, mimeType ?? 'audio/wav')
    }
  }
}

function asRoleplayStage(
  value: FriendSession['stage'],
): RoleplayStage | null {
  if (
    value === 'observe' ||
    value === 'rehearse' ||
    value === 'perform' ||
    value === 'reflect'
  ) {
    return value
  }

  return null
}
