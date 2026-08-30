import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import { speakSessionSpeech } from './speakSessionSpeech'
import { stopSpeechPlayback } from '../shared/stopSpeechPlayback'
import type {
  ConversationJourneyApi,
  FriendSession,
  StartFriendSessionInput,
} from './ConversationJourneyApi'
import { ROLEPLAY_CATALOG, type RoleplayCatalogEntry } from './roleplayCatalog'
import { VoicePracticePanel } from './VoicePracticePanel'
import { TutorPresence } from '../tutor/TutorPresence'
import { defaultConversationJourneyApi } from './WebConversationJourneyApi'
import './ConversationJourney.css'

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
}: RoleplayJourneyProps) {
  const [selected, setSelected] = useState<RoleplayCatalogEntry>(
    DEFAULT_ROLEPLAY_ENTRY,
  )
  const [state, setState] = useState<RoleplayState>({ kind: 'idle' })
  const [utterance, setUtterance] = useState('')
  const [performed, setPerformed] = useState(false)
  const [inputMode, setInputMode] = useState<'type' | 'speak'>('speak')
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const text = (key: MessageKey) => message(key).text

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

    try {
      let session = await api.startSession(
        {
          coaching_mode: 'gentle',
          persona_key: selected.personaKey as StartFriendSessionInput['persona_key'],
          scenario_key:
            selected.scenarioKey as StartFriendSessionInput['scenario_key'],
          ui_locale: locale,
        },
        createClientId(),
      )

      let stage = asRoleplayStage(session.stage)
      while (
        (stage === 'observe' || stage === 'rehearse') &&
        api.advanceStage !== undefined
      ) {
        const next = NEXT_STAGE[stage]
        if (next === undefined || next === 'reflect') {
          break
        }
        session = await api.advanceStage(
          session.id,
          { to_stage: next },
          createClientId(),
        )
        stage = asRoleplayStage(session.stage)
      }

      setState({ kind: 'ready', session, catalogId: selected.id })
      playEnglish(session.id, [selected.pedagogy.performPrompt])
    } catch {
      setState({
        kind: 'error',
        messageKey: 'conversation.roleplay.error',
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
    } catch {
      setState({
        kind: 'error',
        session,
        catalogId,
        messageKey: 'conversation.roleplay.error',
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
      await api.createTurn(
        session.id,
        { prototype_utterance: prototypeUtterance },
        createClientId(),
      )
      setPerformed(true)
      setUtterance('')
      setState({ kind: 'ready', session, catalogId })
    } catch {
      setState({
        kind: 'error',
        session,
        catalogId,
        messageKey: 'conversation.roleplay.error',
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
  const selectedLabel = selectedEntry.displayName
  const selectedPedagogy = selectedEntry.pedagogy
  const stage = asRoleplayStage(session?.stage ?? null)
  const nextStage = stage === null ? null : (NEXT_STAGE[stage] ?? null)

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
          mood="idle"
          size="md"
        />
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
        <p className="conversation-journey__lead" lang="en-IN">
          {selectedLabel}
        </p>
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
                  {entry.displayName}
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
                  {entry.displayName}
                </option>
              ))}
            </select>
          </label>
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
