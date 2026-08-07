import type {
  MessageKey,
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
} from './ConversationJourneyApi'
import { defaultConversationJourneyApi } from './WebConversationJourneyApi'
import './ConversationJourney.css'

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
}: RoleplayJourneyProps) {
  const [state, setState] = useState<RoleplayState>({ kind: 'idle' })
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  const start = async () => {
    setState({ kind: 'starting' })

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
      setState({ kind: 'ready', session })
    } catch {
      setState({
        kind: 'error',
        messageKey: 'conversation.roleplay.error',
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
      setState({ kind: 'ready', session: updated })
    } catch {
      setState({
        kind: 'error',
        session,
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

          {session.objectives !== undefined && session.objectives.length > 0 ? (
            <ul className="conversation-journey__model-lines">
              {session.objectives.map((objective) => (
                <li key={objective.id} lang={locale}>
                  {objective.title}
                </li>
              ))}
            </ul>
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
