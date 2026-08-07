import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useState } from 'react'
import {
  resolveInterfaceMessage,
  type InterfaceMessageResolution,
} from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import type {
  OperatorAuditEvent,
  OperatorConsoleApi,
  OperatorContentDraft,
  OperatorPublicationStatus,
} from './OperatorConsoleApi'

export interface OperatorConsoleProps {
  api: OperatorConsoleApi
  locale: SupportedLocale
  localizationPolicy: ResolutionPolicy
}

type LoadState = 'loading' | 'ready' | 'error'

export function OperatorConsole({
  api,
  locale,
  localizationPolicy,
}: OperatorConsoleProps) {
  const message = (key: MessageKey): InterfaceMessageResolution =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const [state, setState] = useState<LoadState>('loading')
  const [drafts, setDrafts] = useState<readonly OperatorContentDraft[]>([])
  const [publications, setPublications] = useState<
    readonly OperatorPublicationStatus[]
  >([])
  const [auditEvents, setAuditEvents] = useState<
    readonly OperatorAuditEvent[]
  >([])

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    Promise.all([
      api.listDrafts(controller.signal),
      api.listPublications(controller.signal),
      api.listAuditEvents(controller.signal),
    ])
      .then(([nextDrafts, nextPublications, nextAudit]) => {
        if (!active) {
          return
        }

        setDrafts(nextDrafts)
        setPublications(nextPublications)
        setAuditEvents(nextAudit)
        setState('ready')
      })
      .catch(() => {
        if (active) {
          setState('error')
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [api])

  return (
    <section
      aria-labelledby="operator-console-title"
      className="operator-console"
      data-operator-console="content-workflow"
    >
      <h2 className="operator-console__title" id="operator-console-title">
        <LocalizedMessage resolution={message('operator.console.title')} />
      </h2>
      <p className="operator-console__lead">
        <LocalizedMessage resolution={message('operator.console.lead')} />
      </p>
      <p className="operator-console__notice">
        <LocalizedMessage resolution={message('operator.console.notice')} />
      </p>

      {state === 'loading' ? (
        <p>
          <LocalizedMessage
            resolution={message('operator.console.loading')}
          />
        </p>
      ) : null}

      {state === 'error' ? (
        <p role="alert">
          <LocalizedMessage resolution={message('operator.console.error')} />
        </p>
      ) : null}

      {state === 'ready' ? (
        <>
          <section
            aria-labelledby="operator-review-queue-title"
            className="operator-console__panel"
          >
            <h3 id="operator-review-queue-title">
              <LocalizedMessage
                resolution={message('operator.review_queue.title')}
              />
            </h3>
            {drafts.length === 0 ? (
              <p>
                <LocalizedMessage
                  resolution={message('operator.review_queue.empty')}
                />
              </p>
            ) : (
              <ul className="operator-console__list">
                {drafts.map((draft) => (
                  <li key={draft.curriculum_version_id}>
                    <strong>
                      {draft.program_key} / {draft.version_key}
                    </strong>
                    <span>
                      {' '}
                      · {draft.publication_status}
                      {' · EN '}
                      {draft.latest_english_decision ?? 'pending'}
                      {' · KN '}
                      {draft.latest_kannada_decision ?? 'pending'}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section
            aria-labelledby="operator-publish-status-title"
            className="operator-console__panel"
          >
            <h3 id="operator-publish-status-title">
              <LocalizedMessage
                resolution={message('operator.publish_status.title')}
              />
            </h3>
            {publications.length === 0 ? (
              <p>
                <LocalizedMessage
                  resolution={message('operator.publish_status.empty')}
                />
              </p>
            ) : (
              <ul className="operator-console__list">
                {publications.map((publication) => (
                  <li key={publication.publication_id}>
                    <strong>
                      {publication.program_key} / {publication.version_key}
                    </strong>
                    <span>
                      {' '}
                      · #{publication.publication_number}
                      {publication.is_head ? ' · head' : ''}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section
            aria-labelledby="operator-audit-title"
            className="operator-console__panel"
          >
            <h3 id="operator-audit-title">
              <LocalizedMessage
                resolution={message('operator.audit.title')}
              />
            </h3>
            {auditEvents.length === 0 ? (
              <p>
                <LocalizedMessage
                  resolution={message('operator.audit.empty')}
                />
              </p>
            ) : (
              <ul className="operator-console__list">
                {auditEvents.map((event) => (
                  <li key={event.id}>
                    <strong>{event.action}</strong>
                    <span>
                      {' '}
                      · {event.subject_type}:{event.subject_id}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      ) : null}
    </section>
  )
}
