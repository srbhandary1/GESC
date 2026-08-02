import { MixedLanguageText } from '@global-english/design-system'
import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import type {
  LearnerJourneyApi,
  LearnerProgress,
} from '../learner/LearnerJourneyApi'

export interface DemoLearnerProgressProps {
  readonly api: LearnerJourneyApi
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}

type ProgressState =
  | { readonly kind: 'loading' }
  | { readonly kind: 'error' }
  | { readonly kind: 'ready'; readonly progress: LearnerProgress }

/**
 * Demo-only progress destination. It reads the same in-memory synthetic
 * fixtures as the rest of the local demo and reuses the accepted learner
 * message keys; it creates no authoritative record and is never rendered
 * outside the explicit demo runtime.
 */
export function DemoLearnerProgress({
  api,
  locale,
  localizationPolicy,
}: DemoLearnerProgressProps) {
  const [state, setState] = useState<ProgressState>({ kind: 'loading' })
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  useEffect(() => {
    const controller = new AbortController()

    api
      .home(controller.signal)
      .then((home) => {
        if (home.enrollment === null) {
          throw new Error('The demo enrollment is unavailable.')
        }

        return api.progress(home.enrollment.id, controller.signal)
      })
      .then((progress) => setState({ kind: 'ready', progress }))
      .catch(() => {
        if (!controller.signal.aborted) {
          setState({ kind: 'error' })
        }
      })

    return () => {
      controller.abort()
    }
  }, [api])

  if (state.kind === 'loading') {
    return (
      <p
        className="learner-status"
        role="status"
      >
        <LocalizedMessage resolution={message('learner.loading')} />
      </p>
    )
  }

  if (state.kind === 'error') {
    return (
      <p role="alert">
        <LocalizedMessage resolution={message('learner.error.message')} />
      </p>
    )
  }

  return (
    <section
      aria-labelledby="demo-progress-title"
      className="learner-stack"
    >
      <h2
        className="learner-section-title"
        id="demo-progress-title"
      >
        <LocalizedMessage resolution={message('learner.progress.title')} />
      </h2>
      {state.progress.objectives.map((objective) => (
        <article
          className="learner-card learner-progress-objective"
          key={objective.objective_id}
        >
          <h3 className="learner-card__title">
            <MixedLanguageText
              segments={[
                {
                  locale: objective.label.locale,
                  text: objective.label.text,
                },
              ]}
            />
          </h3>
          <dl className="learner-definition-list">
            <div>
              <dt>
                <LocalizedMessage
                  resolution={message('learner.result.knowledge_label')}
                />
              </dt>
              <dd>
                <LocalizedMessage
                  resolution={message(
                    objective.knowledge_state === 'mastered'
                      ? 'learner.result.knowledge_mastered'
                      : 'learner.result.knowledge_developing',
                  )}
                />
              </dd>
            </div>
            <div>
              <dt>
                <LocalizedMessage
                  resolution={message('learner.result.spoken_label')}
                />
              </dt>
              <dd>
                <LocalizedMessage
                  resolution={message('learner.result.spoken_unassessed')}
                />
              </dd>
            </div>
            <div>
              <dt>
                <LocalizedMessage
                  resolution={message(
                    'learner.result.local_capture_label',
                  )}
                />
              </dt>
              <dd>
                <LocalizedMessage
                  resolution={message(
                    objective.local_capture_receipt_present
                      ? 'learner.result.local_capture_completed'
                      : 'learner.result.local_capture_not_recorded',
                  )}
                />
              </dd>
            </div>
            <div>
              <dt>
                <LocalizedMessage
                  resolution={message('learner.result.review_title')}
                />
              </dt>
              <dd>
                <time dateTime={objective.review_due_at}>
                  {formatReviewDate(
                    objective.review_due_at,
                    locale,
                    objective.review_timezone,
                  )}
                </time>
              </dd>
            </div>
          </dl>
        </article>
      ))}
    </section>
  )
}

function formatReviewDate(
  dueAt: string,
  locale: SupportedLocale,
  timezone: string,
): string {
  try {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: 'medium',
      timeStyle: 'short',
      timeZone: timezone,
    }).format(new Date(dueAt))
  } catch {
    return dueAt
  }
}
