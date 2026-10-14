import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useState, type FormEvent } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import {
  defaultTuitionRequestsApi,
  type InstituteContact,
  type TuitionRequest,
  type TuitionRequestsApi,
} from './TuitionRequestsApi'
import './LearnerJourney.css'

export interface TuitionRequestPanelProps {
  readonly api?: TuitionRequestsApi
  readonly createClientId?: () => string
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}

export function TuitionRequestPanel({
  api = defaultTuitionRequestsApi,
  createClientId = createRandomClientId,
  locale,
  localizationPolicy,
}: TuitionRequestPanelProps) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const [requests, setRequests] = useState<readonly TuitionRequest[]>([])
  const [windowValue, setWindowValue] = useState('')
  const [topic, setTopic] = useState('')
  const [notes, setNotes] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)
  const [listError, setListError] = useState(false)
  const [submitted, setSubmitted] = useState(false)
  const [instituteContact, setInstituteContact] = useState<InstituteContact>({
    email: null,
    phone: null,
    whatsapp: null,
  })

  useEffect(() => {
    const controller = new AbortController()
    void api
      .list(controller.signal)
      .then((listing) => {
        setRequests(listing.requests)
        setInstituteContact(
          listing.institute_contact ?? {
            email: null,
            phone: null,
            whatsapp: null,
          },
        )
        setListError(false)
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setListError(true)
        }
      })
    return () => controller.abort()
  }, [api])

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault()
    setBusy(true)
    setError(false)
    try {
      const created = await api.create(
        {
          preferred_window: windowValue.trim(),
          topic: topic.trim(),
          ...(notes.trim() === '' ? {} : { notes: notes.trim() }),
        },
        createClientId(),
      )
      setRequests((current) => [created, ...current])
      setWindowValue('')
      setTopic('')
      setNotes('')
      setSubmitted(true)
    } catch {
      setError(true)
    } finally {
      setBusy(false)
    }
  }

  return (
    <section
      aria-labelledby="tuition-request-title"
      className="learner-card"
      id="tuition-request"
    >
      <h2 id="tuition-request-title">
        <LocalizedMessage resolution={message('tuition.title')} />
      </h2>
      <p>
        <LocalizedMessage resolution={message('tuition.lead')} />
      </p>
      {instituteContact.phone !== null ||
      instituteContact.whatsapp !== null ||
      instituteContact.email !== null ? (
        <div className="learner-stack">
          <p>
            <LocalizedMessage resolution={message('tuition.contact_lead')} />
          </p>
          <ul>
            {instituteContact.phone !== null ? (
              <li>
                <a href={`tel:${instituteContact.phone}`}>
                  <LocalizedMessage
                    resolution={message('tuition.contact_phone')}
                  />
                </a>
              </li>
            ) : null}
            {instituteContact.whatsapp !== null ? (
              <li>
                <a
                  href={`https://wa.me/${instituteContact.whatsapp}`}
                  rel="noreferrer"
                  target="_blank"
                >
                  <LocalizedMessage
                    resolution={message('tuition.contact_whatsapp')}
                  />
                </a>
              </li>
            ) : null}
            {instituteContact.email !== null ? (
              <li>
                <a href={`mailto:${instituteContact.email}`}>
                  <LocalizedMessage
                    resolution={message('tuition.contact_email')}
                  />
                </a>
              </li>
            ) : null}
          </ul>
        </div>
      ) : null}
      <form className="learner-stack" onSubmit={(event) => void onSubmit(event)}>
        <label htmlFor="tuition-window">
          <LocalizedMessage resolution={message('tuition.preferred_window')} />
        </label>
        <input
          id="tuition-window"
          maxLength={120}
          minLength={3}
          onChange={(event) => setWindowValue(event.currentTarget.value)}
          required
          value={windowValue}
        />
        <label htmlFor="tuition-topic">
          <LocalizedMessage resolution={message('tuition.topic')} />
        </label>
        <input
          id="tuition-topic"
          maxLength={200}
          minLength={3}
          onChange={(event) => setTopic(event.currentTarget.value)}
          required
          value={topic}
        />
        <label htmlFor="tuition-notes">
          <LocalizedMessage resolution={message('tuition.notes')} />
        </label>
        <textarea
          id="tuition-notes"
          maxLength={500}
          onChange={(event) => setNotes(event.currentTarget.value)}
          rows={3}
          value={notes}
        />
        <button className="ge-button ge-button--primary" disabled={busy} type="submit">
          <LocalizedMessage
            resolution={message(busy ? 'tuition.submitting' : 'tuition.submit')}
          />
        </button>
      </form>
      {submitted ? (
        <p>
          <LocalizedMessage resolution={message('tuition.success')} />
        </p>
      ) : null}
      {error ? (
        <p role="alert">
          <LocalizedMessage resolution={message('tuition.error')} />
        </p>
      ) : null}
      {listError ? (
        <p>
          <LocalizedMessage resolution={message('tuition.error')} />
        </p>
      ) : null}
      <h3>
        <LocalizedMessage resolution={message('tuition.list_title')} />
      </h3>
      {requests.length === 0 ? (
        <p>
          <LocalizedMessage resolution={message('tuition.empty')} />
        </p>
      ) : (
        <ul>
          {requests.map((request) => (
            <li key={request.id}>
              {request.topic}
              {' · '}
              {request.preferred_window}
              {' · '}
              <LocalizedMessage
                resolution={message(
                  request.status === 'acknowledged'
                    ? 'tuition.status.acknowledged'
                    : 'tuition.status.submitted',
                )}
              />
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
