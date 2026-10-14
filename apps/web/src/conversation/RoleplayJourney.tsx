import type {
  MessageKey,
  MessageParameters,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useRef, useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import {
  playConversationSpeechResult,
  speakSessionSpeech,
} from './speakSessionSpeech'
import { stopSpeechPlayback } from '../shared/stopSpeechPlayback'
import type {
  ConversationJourneyApi,
  FriendSession,
  FriendTurn,
  StartFriendSessionInput,
} from './ConversationJourneyApi'
import { ROLEPLAY_CATALOG, type RoleplayCatalogEntry } from './roleplayCatalog'
import { VoicePracticePanel } from './VoicePracticePanel'
import { TutorPresence } from '../tutor/TutorPresence'
import { defaultConversationJourneyApi } from './WebConversationJourneyApi'
import { conversationErrorMessageKey } from './conversationErrorMessage'
import { speechLatencySample } from './speechLatencyTelemetry'
import {
  beginSpeakLoop,
  completeSpeakLoop,
  markSpeakLoop,
  peekSpeakLoop,
} from './speakLoopTimings'
import type { SpeakLoopSnapshot } from './speakLoopTimings'
import type { AvatarPlaybackState } from '../tutor/avatar/types'
import './ConversationJourney.css'
import { InteractiveDialogue, RecoveryAcknowledgement, RECOVERY_NOTICE_VERSION } from './InteractiveDialogue'
import { ConversationProcessingConsent } from './ConversationProcessingConsent'

function defaultRoleplayEntry(): RoleplayCatalogEntry {
  const entry = ROLEPLAY_CATALOG[0]
  if (entry === undefined) {
    throw new Error('roleplay_catalog_empty')
  }
  return entry
}

const DEFAULT_ROLEPLAY_ENTRY = defaultRoleplayEntry()

const ROLEPLAY_SCENE_ICONS: Readonly<Record<string, string>> = {
  'clinic-reception': '🏥',
  'job-interview': '💼',
  'bank-counter': '🏦',
  'restaurant-order': '🍽️',
  'hotel-checkin': '🏨',
  'phone-customer-support': '📞',
  'grocery-shop': '🛒',
  'school-parent-meeting': '🏫',
  'doctor-appointment': '🩺',
  'airport-checkin': '✈️',
  'office-meeting': '📋',
  'bus-train-ticket': '🚌',
}


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
      readonly catalogId: string
    }
  | {
      readonly kind: 'advancing'
      readonly session: FriendSession
      readonly catalogId: string
    }
  | {
      readonly kind: 'error'
      readonly session?: FriendSession
      readonly catalogId?: string
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
  perform: 'conversation.voice.optional',
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
  const [selected, setSelected] = useState<RoleplayCatalogEntry>(
    DEFAULT_ROLEPLAY_ENTRY,
  )
  const [state, setState] = useState<RoleplayState>({ kind: 'idle' })
  const [recoveryAccepted, setRecoveryAccepted] = useState(false)
  const [providerGranted, setProviderGranted] = useState(false)
  const startRequest = useRef<AbortController | null>(null)
  const startCommand = useRef<{ fingerprint: string; command: string } | null>(null)
  const [utterance, setUtterance] = useState('')
  const [performed, setPerformed] = useState(false)
  const [lastTurn, setLastTurn] = useState<FriendTurn | null>(null)
  const [inputMode, setInputMode] = useState<'type' | 'speak'>('speak')
  const [speakingReply, setSpeakingReply] = useState(false)
  const [holdResetEpoch, setHoldResetEpoch] = useState(0)
  const [avatarPlayback, setAvatarPlayback] =
    useState<AvatarPlaybackState | null>(null)
  const message = (
    key: MessageKey,
    parameters: MessageParameters = {},
  ) => resolveInterfaceMessage(key, locale, localizationPolicy, parameters)
  const text = (key: MessageKey, parameters: MessageParameters = {}) =>
    message(key, parameters).text

  useEffect(() => {
    return () => {
      startRequest.current?.abort()
      stopSpeechPlayback()
    }
  }, [])

  const playEnglish = (sessionId: string, lines: readonly string[]) => {
    setSpeakingReply(true)
    void speakSessionSpeech({
      api,
      locale: 'en-IN',
      onPlaybackChange: setAvatarPlayback,
      purpose: 'model_en',
      sessionId,
      text: lines.join(' '),
    })
      .catch(() => undefined)
      .finally(() => {
        setAvatarPlayback(null)
        setSpeakingReply(false)
      })
  }

  const start = async () => {
    if (!recoveryAccepted || !providerGranted || state.kind === 'starting') return
    const request = new AbortController()
    startRequest.current = request
    const fingerprint = `${selected.id}:${locale}`
    if (startCommand.current?.fingerprint !== fingerprint) {
      startCommand.current = { fingerprint, command: createClientId() }
    }
    setState({ kind: 'starting' })
    setUtterance('')
    setPerformed(false)
    setLastTurn(null)

    try {
      const session = await api.startSession(
        {
          coaching_mode: 'gentle',
          persona_key: selected.personaKey as StartFriendSessionInput['persona_key'],
          scenario_key:
            selected.scenarioKey as StartFriendSessionInput['scenario_key'],
          ui_locale: locale,
          interaction_flow: 'roleplay_dialogue',
          recovery_notice_version: RECOVERY_NOTICE_VERSION,
          recovery_notice_accepted: true,
        },
        startCommand.current.command,
        request.signal,
      )
      if (request.signal.aborted) return
      const stage = asRoleplayStage(session.stage)
      setState({ kind: 'ready', session, catalogId: selected.id })
      if (session.interaction) {
        return
      } else if (stage === 'observe') {
        playEnglish(session.id, selected.pedagogy.observeModel)
      } else if (stage === 'rehearse') {
        playEnglish(session.id, selected.pedagogy.rehearseChunks)
      } else if (stage === 'perform') {
        playEnglish(session.id, [selected.pedagogy.performPrompt])
      }
    } catch (error: unknown) {
      if (request.signal.aborted) return
      setState({
        kind: 'error',
        messageKey: conversationErrorMessageKey(
          error,
          'conversation.roleplay.error',
        ),
      })
    }
  }

  const advance = async (session: FriendSession, catalogId: string) => {
    const current = asRoleplayStage(session.stage)
    const next = current === null ? null : (NEXT_STAGE[current] ?? null)

    if (next === null || api.advanceStage === undefined) {
      setState({
        kind: 'error',
        session,
        catalogId,
        messageKey: 'conversation.roleplay.error',
      })
      return
    }

    setState({ kind: 'advancing', session, catalogId })

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
    if (peekSpeakLoop() === null) {
      beginSpeakLoop()
    }
    markSpeakLoop('ack')

    try {
      const turn = await api.createTurn(
        session.id,
        { prototype_utterance: prototypeUtterance },
        createClientId(),
      )
      markSpeakLoop('turn_json_ready')
      setLastTurn(turn)
      setPerformed(turn.status === 'completed')
      setUtterance('')
      setState({ kind: 'ready', session, catalogId })
      onLiveAiUsageChanged?.()
      setSpeakingReply(true)
      void playRoleplayTurn(turn, setAvatarPlayback, (snapshot, outcome) => {
        if (api.recordSpeechLatency === undefined) {
          return
        }
        void api
          .recordSpeechLatency(
            turn.id,
            speechLatencySample(snapshot, outcome),
            createClientId(),
          )
          .catch(() => undefined)
      })
        .catch(() => undefined)
        .finally(() => {
          setAvatarPlayback(null)
          setSpeakingReply(false)
          setHoldResetEpoch((epoch) => epoch + 1)
        })
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
  const catalogId =
    state.kind === 'ready' ||
    state.kind === 'advancing' ||
    (state.kind === 'error' && state.catalogId !== undefined)
      ? state.catalogId
      : selected.id
  const resolvedCatalogId = catalogId ?? selected.id
  const selectedEntry =
    ROLEPLAY_CATALOG.find((entry) => entry.id === resolvedCatalogId) ?? selected
  const selectedLabel = message(selectedEntry.labelKey)
  const selectedPedagogy = selectedEntry.pedagogy
  const stage = asRoleplayStage(session?.stage ?? null)
  const nextStage = stage === null ? null : (NEXT_STAGE[stage] ?? null)

  if (session?.interaction?.flow === 'roleplay_dialogue') {
    return <InteractiveDialogue
      api={api} createClientId={createClientId} initialSession={session}
      locale={locale} localizationPolicy={localizationPolicy}
      onLiveAiUsageChanged={onLiveAiUsageChanged}
      onExited={() => {
        setState({ kind: 'idle' })
        setRecoveryAccepted(false)
        startCommand.current = null
        onBack?.()
      }}
    />
  }

  return (
    <section
      aria-labelledby="roleplay-journey-title"
      className="conversation-journey conversation-journey--embedded"
      data-mode="roleplay"
      data-review-status="synthetic_unreviewed"
    >
      <header className="conversation-journey__heading">
        <TutorPresence
          locale={locale}
          localizationPolicy={localizationPolicy}
          mood={speakingReply ? 'speaking' : 'idle'}
          size="lg"
          showName={false}
          enableAvatar3d
          avatarPlayback={avatarPlayback}
        />
        <p className="conversation-journey__eyebrow">
          <LocalizedMessage
            resolution={message(session === undefined ? 'conversation.eyebrow' : 'conversation.roleplay.eyebrow')}
          />
        </p>
        <h1 id="roleplay-journey-title">
          <LocalizedMessage
            resolution={message('conversation.roleplay.title')}
          />
        </h1>
        <p className="conversation-journey__lead">
          <LocalizedMessage resolution={selectedLabel} />
        </p>
        {session === undefined ? <p>
          <LocalizedMessage resolution={message('conversation.interactive.roles')} />{' '}
          <LocalizedMessage resolution={message(selected.rolesKey)} />
        </p> : null}
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
            resolution={message(session === undefined ? 'conversation.disclosure_title' : 'conversation.roleplay.disclosure_title')}
          />
        </h2>
        <p>
          <LocalizedMessage
            resolution={message(session === undefined ? 'conversation.interactive.advisory' : 'conversation.roleplay.prototype_notice')}
          />
        </p>
        {session !== undefined ? <p>
          <LocalizedMessage
            resolution={message('conversation.roleplay.privacy_notice')}
          />
        </p> : null}
      </div>

      {state.kind === 'idle' || state.kind === 'starting' || (state.kind === 'error' && !state.session) ? (
        <div className="conversation-journey__start">
          <ConversationProcessingConsent api={api} createClientId={createClientId} locale={locale} localizationPolicy={localizationPolicy} onGrantedChange={setProviderGranted} disabled={state.kind === 'starting'} />
          <RecoveryAcknowledgement accepted={recoveryAccepted} onChange={setRecoveryAccepted} locale={locale} localizationPolicy={localizationPolicy} />
          <ul className="roleplay-scenes">
            {ROLEPLAY_CATALOG.map((entry) => (
              <li key={entry.id}>
                <button
                  aria-pressed={selected.id === entry.id}
                  disabled={state.kind === 'starting'}
                  onClick={() => setSelected(entry)}
                  type="button"
                >
                  <span aria-hidden="true" className="roleplay-scenes__icon">
                    {ROLEPLAY_SCENE_ICONS[entry.id] ?? '🎭'}
                  </span>
                  <LocalizedMessage resolution={message(entry.labelKey)} />
                </button>
              </li>
            ))}
          </ul>
          <label
            className="visually-hidden"
            htmlFor="roleplay-scenario"
          >
            <LocalizedMessage
              resolution={message('conversation.roleplay.choose')}
            />
            <select
              className="visually-hidden"
              disabled={state.kind === 'starting'}
              id="roleplay-scenario"
              onChange={(event) => {
                const next = ROLEPLAY_CATALOG.find(
                  (entry) => entry.id === event.target.value,
                )
                if (next !== undefined) {
                  setSelected(next)
                }
              }}
              value={selected.id}
            >
              {ROLEPLAY_CATALOG.map((entry) => (
                <option key={entry.id} value={entry.id}>
                  {message(entry.labelKey).text}
                </option>
              ))}
            </select>
          </label>
          <button
            disabled={state.kind === 'starting' || !recoveryAccepted || !providerGranted}
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
                resetEpoch={holdResetEpoch}
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
              className="conversation-journey__chat conversation-journey__roleplay-chat"
            >
              <h3 className="visually-hidden" id="roleplay-perform-result">
                <LocalizedMessage
                  resolution={message('conversation.reply_title')}
                />
              </h3>
              {lastTurn.transcript !== null ? (
                <div className="conversation-journey__bubble conversation-journey__bubble--you">
                  <p className="conversation-journey__bubble-speaker">
                    <LocalizedMessage
                      resolution={message('conversation.chat.you')}
                    />
                  </p>
                  <p className="conversation-journey__bubble-message" lang="en-IN">
                  {lastTurn.transcript}
                  </p>
                </div>
              ) : null}
              {lastTurn.assistant_reply !== null ? (
                <div className="conversation-journey__bubble conversation-journey__bubble--teacher">
                  <p className="conversation-journey__bubble-speaker">
                    <LocalizedMessage
                      resolution={message('conversation.chat.teacher')}
                    />
                  </p>
                  <p className="conversation-journey__bubble-message" lang="en-IN">
                  {lastTurn.assistant_reply}
                  </p>
                  {lastTurn.priority_correction?.explanation_l1 ? (
                    <div className="conversation-journey__roleplay-correction">
                      {lastTurn.priority_correction.needed ? (
                        <p lang="en-IN">
                          <strong>
                            <LocalizedMessage
                              resolution={message(
                                'conversation.correction.suggestion_label',
                              )}
                            />
                            {': '}
                          </strong>
                          {lastTurn.priority_correction.correction_en}
                        </p>
                      ) : null}
                      <p lang={lastTurn.priority_correction.explanation_locale}>
                        {lastTurn.priority_correction.explanation_l1}
                      </p>
                    </div>
                  ) : null}
                </div>
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
                {session.feedback.map((item) => {
                  const objective = session.objectives?.find(
                    (entry) => entry.id === item.scenario_objective_id,
                  )
                  return (
                    <li key={item.id}>
                      {objective?.title ?? item.completion_state}
                      {' · '}
                      <LocalizedMessage
                        resolution={message(
                          item.completion_state === 'met'
                            ? 'conversation.roleplay.objective_met'
                            : 'conversation.roleplay.objective_unmet',
                        )}
                      />
                    </li>
                  )
                })}
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

          {nextStage !== null && (stage !== 'perform' || performed) ? (
            <div className="conversation-journey__controls">
              <button
                disabled={state.kind === 'advancing'}
                onClick={() => {
                  if (session === undefined) {
                    return
                  }
                  if (stage === 'observe') {
                    playEnglish(session.id, selectedPedagogy.rehearseChunks)
                  } else if (stage === 'rehearse') {
                    stopSpeechPlayback()
                  }
                  void advance(session, resolvedCatalogId)
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

async function playRoleplayTurn(
  turn: FriendTurn,
  onPlaybackChange: (playback: AvatarPlaybackState | null) => void,
  onLatency?: (
    snapshot: SpeakLoopSnapshot,
    outcome: 'started' | 'unavailable' | 'failed' | 'cancelled',
  ) => void,
): Promise<void> {
  const speech = turn.speech
  if (speech == null) {
    const snapshot = completeSpeakLoop()
    if (snapshot !== null) {
      onLatency?.(snapshot, 'unavailable')
    }
    return
  }

  try {
    await playConversationSpeechResult({
      onPlaybackChange,
      onPlaybackStarted: () => {
        const snapshot = markSpeakLoop('first_audio')
        if (snapshot !== null) {
          onLatency?.(snapshot, 'started')
        }
      },
      purpose: 'model_en',
      speech,
      text: turn.assistant_reply,
    })
    if (peekSpeakLoop() !== null) {
      const snapshot = completeSpeakLoop()
      if (snapshot !== null) {
        onLatency?.(snapshot, 'unavailable')
      }
    }
    await playConversationSpeechResult({
      onPlaybackChange,
      purpose: 'guidance_l1',
      speech,
      text: turn.priority_correction?.explanation_l1,
    })
  } catch {
    const snapshot = completeSpeakLoop()
    if (snapshot !== null) {
      onLatency?.(snapshot, 'failed')
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
