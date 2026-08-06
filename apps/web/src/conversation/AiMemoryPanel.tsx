import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useState, type FormEvent } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import type {
  ConversationJourneyApi,
  LearnerMemoryFact,
} from './ConversationJourneyApi'
import './ConversationJourney.css'

export interface AiMemoryPanelProps {
  readonly api: ConversationJourneyApi
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly createClientId: () => string
  readonly text: (key: MessageKey) => string
}

export function AiMemoryPanel({
  api,
  locale,
  localizationPolicy,
  createClientId,
  text,
}: AiMemoryPanelProps) {
  const [facts, setFacts] = useState<readonly LearnerMemoryFact[]>([])
  const [draft, setDraft] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  const refresh = () => {
    if (api.listAiMemory === undefined) {
      return
    }

    setBusy(true)
    setError(false)
    void api
      .listAiMemory()
      .then((listing) => setFacts(listing.facts))
      .catch(() => setError(true))
      .finally(() => setBusy(false))
  }

  useEffect(() => {
    refresh()
    // eslint-disable-next-line react-hooks/exhaustive-deps -- load once when panel mounts
  }, [])

  if (
    api.listAiMemory === undefined ||
    api.createAiMemoryFact === undefined ||
    api.updateAiMemoryFact === undefined ||
    api.deleteAiMemoryFact === undefined
  ) {
    return null
  }

  const create = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const factText = draft.trim()

    if (factText === '' || busy) {
      return
    }

    setBusy(true)
    setError(false)
    void api
      .createAiMemoryFact!({ fact_text: factText }, createClientId())
      .then(() => {
        setDraft('')
        return api.listAiMemory!()
      })
      .then((listing) => setFacts(listing.facts))
      .catch(() => setError(true))
      .finally(() => setBusy(false))
  }

  const remove = (factId: string) => {
    setBusy(true)
    setError(false)
    void api
      .deleteAiMemoryFact!(factId, createClientId())
      .then(() => api.listAiMemory!())
      .then((listing) => setFacts(listing.facts))
      .catch(() => setError(true))
      .finally(() => setBusy(false))
  }

  return (
    <aside
      className="conversation-journey__memory"
      aria-labelledby="ai-memory-title"
    >
      <h3 id="ai-memory-title">
        <LocalizedMessage resolution={message('conversation.memory.title')} />
      </h3>
      <p>
        <LocalizedMessage
          resolution={message('conversation.memory.notice')}
        />
      </p>
      <ul className="conversation-journey__memory-list">
        {facts.map((fact) => (
          <li key={fact.id}>
            <p lang="en-IN">{fact.fact_text}</p>
            <button
              className="ge-button"
              type="button"
              disabled={busy}
              onClick={() => remove(fact.id)}
            >
              {text('conversation.memory.delete')}
            </button>
          </li>
        ))}
      </ul>
      {facts.length === 0 ? (
        <p>
          <LocalizedMessage
            resolution={message('conversation.memory.empty')}
          />
        </p>
      ) : null}
      <form className="conversation-journey__memory-form" onSubmit={create}>
        <label htmlFor="ai-memory-fact">
          <LocalizedMessage
            resolution={message('conversation.memory.fact_label')}
          />
        </label>
        <textarea
          id="ai-memory-fact"
          maxLength={200}
          rows={3}
          value={draft}
          onChange={(event) => setDraft(event.currentTarget.value)}
        />
        <button
          className="ge-button ge-button--primary"
          type="submit"
          disabled={busy || draft.trim() === '' || facts.length >= 5}
        >
          {text('conversation.memory.add')}
        </button>
      </form>
      {error ? (
        <p role="alert">
          <LocalizedMessage
            resolution={message('conversation.memory.error')}
          />
        </p>
      ) : null}
    </aside>
  )
}
