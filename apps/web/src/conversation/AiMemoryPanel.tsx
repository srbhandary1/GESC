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
  const [editingId, setEditingId] = useState<string | null>(null)
  const [editDraft, setEditDraft] = useState('')
  const [busy, setBusy] = useState(() => api.listAiMemory !== undefined)
  const [error, setError] = useState(false)
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  useEffect(() => {
    if (api.listAiMemory === undefined) {
      return
    }

    let active = true
    void api
      .listAiMemory()
      .then((listing) => {
        if (active) {
          setFacts(listing.facts)
        }
      })
      .catch(() => {
        if (active) {
          setError(true)
        }
      })
      .finally(() => {
        if (active) {
          setBusy(false)
        }
      })

    return () => {
      active = false
    }
  }, [api])

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

  const beginEdit = (fact: LearnerMemoryFact) => {
    setEditingId(fact.id)
    setEditDraft(fact.fact_text)
    setError(false)
  }

  const cancelEdit = () => {
    setEditingId(null)
    setEditDraft('')
  }

  const saveEdit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const factId = editingId
    const factText = editDraft.trim()

    if (factId === null || factText === '' || busy) {
      return
    }

    setBusy(true)
    setError(false)
    void api
      .updateAiMemoryFact!(factId, { fact_text: factText }, createClientId())
      .then(() => {
        setEditingId(null)
        setEditDraft('')
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
      .then(() => {
        if (editingId === factId) {
          setEditingId(null)
          setEditDraft('')
        }
        return api.listAiMemory!()
      })
      .then((listing) => setFacts(listing.facts))
      .catch(() => setError(true))
      .finally(() => setBusy(false))
  }

  return (
    <details className="conversation-journey__memory conversation-journey__memory--collapsible">
      <summary className="conversation-journey__memory-summary">
        <span className="conversation-journey__memory-summary-title">
          <LocalizedMessage resolution={message('conversation.memory.title')} />
        </span>
        <span className="conversation-journey__memory-summary-hint">
          <LocalizedMessage
            resolution={message('conversation.memory.summary_hint')}
          />
        </span>
      </summary>
      <div
        aria-labelledby="ai-memory-title"
        className="conversation-journey__memory-body"
      >
      <h3 className="visually-hidden" id="ai-memory-title">
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
            {editingId === fact.id ? (
              <form
                className="conversation-journey__memory-edit"
                onSubmit={saveEdit}
              >
                <label htmlFor={`ai-memory-edit-${fact.id}`}>
                  <LocalizedMessage
                    resolution={message('conversation.memory.edit_label')}
                  />
                </label>
                <textarea
                  id={`ai-memory-edit-${fact.id}`}
                  maxLength={200}
                  rows={3}
                  value={editDraft}
                  onChange={(event) => setEditDraft(event.currentTarget.value)}
                />
                <div className="conversation-journey__memory-actions">
                  <button
                    className="ge-button ge-button--primary"
                    type="submit"
                    disabled={busy || editDraft.trim() === ''}
                  >
                    {text('conversation.memory.save')}
                  </button>
                  <button
                    className="ge-button"
                    type="button"
                    disabled={busy}
                    onClick={cancelEdit}
                  >
                    {text('conversation.memory.cancel')}
                  </button>
                </div>
              </form>
            ) : (
              <>
                <p lang="en-IN">{fact.fact_text}</p>
                <div className="conversation-journey__memory-actions">
                  <button
                    className="ge-button"
                    type="button"
                    disabled={busy}
                    onClick={() => beginEdit(fact)}
                  >
                    {text('conversation.memory.edit')}
                  </button>
                  <button
                    className="ge-button"
                    type="button"
                    disabled={busy}
                    onClick={() => remove(fact.id)}
                  >
                    {text('conversation.memory.delete')}
                  </button>
                </div>
              </>
            )}
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
      </div>
    </details>
  )
}
