import {
  type MessageKey,
  type ResolutionPolicy,
  type SupportedLocale,
} from '@global-english/localization'
import {
  resolveInterfaceMessage,
} from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { useEffect, useState } from 'react'
import type { LearnerJourneyApi, LearnerProgressDashboard } from './LearnerJourneyApi'
import './ProgressDashboard.css'

export interface ProgressDashboardProps {
  readonly api: LearnerJourneyApi
  readonly locale: SupportedLocale
  readonly localizationPolicy?: ResolutionPolicy
  readonly onMissionRequested: () => void
}

function conceptLabel(conceptKey: string | null): string | null {
  if (conceptKey === null || conceptKey === '') {
    return null
  }
  return conceptKey.replaceAll('_', ' ').replaceAll('-', ' ')
}

export function ProgressDashboard({
  api,
  locale,
  localizationPolicy,
  onMissionRequested,
}: ProgressDashboardProps) {
  const [data, setData] = useState<LearnerProgressDashboard | null>(null)
  const [error, setError] = useState(false)
  const [reloadToken, setReloadToken] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    api.progressDashboard(controller.signal)
      .then((payload) => {
        if (controller.signal.aborted) {
          return
        }
        setData(payload)
        setError(false)
      })
      .catch((e: unknown) => {
        if (e instanceof DOMException && e.name === 'AbortError') return
        setError(true)
      })
    return () => controller.abort()
  }, [api, reloadToken])

  const msg = (key: string, parameters?: Record<string, string>) => {
    return resolveInterfaceMessage(key as MessageKey, locale, localizationPolicy, parameters)
  }

  if (error) {
    return (
      <div className="progress-dashboard__error" role="alert">
        <LocalizedMessage
          resolution={msg('learner.error.message')}
        />
        <button
          className="button button--primary"
          onClick={() => {
            setData(null)
            setError(false)
            setReloadToken((token) => token + 1)
          }}
          type="button"
        >
          <LocalizedMessage resolution={msg('learner.progress.retry')} />
        </button>
      </div>
    )
  }

  if (data === null) {
    return (
      <div className="progress-dashboard__loading" aria-busy="true">
        <LocalizedMessage
          resolution={msg('learner.progress.loading')}
        />
      </div>
    )
  }

  const hasActivity = data.speaking_activity.completed_turn_count > 0 || data.objectives.length > 0

  if (!hasActivity) {
    return (
      <div className="progress-dashboard progress-dashboard--empty">
        <h1 className="progress-dashboard__title">
          <LocalizedMessage resolution={msg('learner.progress.empty.title')} />
        </h1>
        <p className="progress-dashboard__empty-message">
          <LocalizedMessage resolution={msg('learner.progress.empty.message')} />
        </p>
        <button
          className="button button--primary progress-dashboard__empty-action"
          onClick={onMissionRequested}
          type="button"
        >
          <LocalizedMessage resolution={msg('learner.progress.empty.action')} />
        </button>
      </div>
    )
  }

  const objectivesByKey = new Map<string, typeof data.objectives>()
  for (const obj of data.objectives) {
    const group = objectivesByKey.get(obj.objective_key) ?? []
    group.push(obj)
    objectivesByKey.set(obj.objective_key, group)
  }

  return (
    <div className="progress-dashboard">
      <header className="progress-dashboard__header">
        <h1 className="progress-dashboard__title">
          <LocalizedMessage
            resolution={msg('page.progress.title')}
          />
        </h1>
      </header>

      <section className="progress-dashboard__section progress-dashboard__summary" aria-label={msg('learner.progress.summary').text}>
        <div className="progress-dashboard__stat">
          <span className="progress-dashboard__stat-value">{data.speaking_activity.meaningful_speaking_days_count}</span>
          <span className="progress-dashboard__stat-label">
            <LocalizedMessage resolution={msg('learner.progress.speaking_days', { count: String(data.speaking_activity.meaningful_speaking_days_count) })} />
          </span>
        </div>
        <div className="progress-dashboard__stat">
          <span className="progress-dashboard__stat-value">{data.delayed_review.completed_on_time_count}</span>
          <span className="progress-dashboard__stat-label">
            <LocalizedMessage resolution={msg('learner.progress.delayed_review_ontime', { count: String(data.delayed_review.completed_on_time_count) })} />
          </span>
        </div>
        <div className="progress-dashboard__stat">
          <span className="progress-dashboard__stat-value">{data.delayed_review.completed_late_count}</span>
          <span className="progress-dashboard__stat-label">
            <LocalizedMessage resolution={msg('learner.progress.delayed_review_late', { count: String(data.delayed_review.completed_late_count) })} />
          </span>
        </div>
        {(data.delayed_review.due_count > 0 || data.delayed_review.pending_count > 0) && (
          <div className="progress-dashboard__stat progress-dashboard__stat--missed">
            <span className="progress-dashboard__stat-value">{data.delayed_review.due_count}</span>
            <span className="progress-dashboard__stat-label">
              <LocalizedMessage resolution={msg('learner.progress.delayed_review_missed', { count: String(data.delayed_review.due_count) })} />
            </span>
          </div>
        )}
      </section>

      {data.attempt_improvement.missions_attempted > 0 && (
        <section className="progress-dashboard__section progress-dashboard__improvement" aria-labelledby="improvement-title">
          <h2 id="improvement-title" className="progress-dashboard__section-title">
            <LocalizedMessage resolution={msg('learner.progress.improvement.title')} />
          </h2>
          <ul className="progress-dashboard__improvement-list" aria-label={msg('learner.progress.improvement_list').text}>
            {data.attempt_improvement.missions_passed_on_first_attempt > 0 && (
              <li>
                <LocalizedMessage resolution={msg('learner.progress.improvement.first_attempt_passed', { count: String(data.attempt_improvement.missions_passed_on_first_attempt) })} />
              </li>
            )}
            {data.attempt_improvement.missions_improved_on_retry > 0 && (
              <li>
                <LocalizedMessage resolution={msg('learner.progress.improvement.improved_on_retry', { count: String(data.attempt_improvement.missions_improved_on_retry) })} />
              </li>
            )}
            {data.recurring_errors.patterns.map(pattern => (
              <li key={pattern.error_category}>
                {pattern.trend === 'reducing'
                  ? <LocalizedMessage resolution={msg('learner.progress.improvement.recurring_error_reducing', { error: pattern.error_category })} />
                  : <LocalizedMessage resolution={msg('learner.progress.improvement.recurring_error_stable', { error: pattern.error_category })} />}
              </li>
            ))}
          </ul>
        </section>
      )}

      {objectivesByKey.size > 0 && (
        <section className="progress-dashboard__section progress-dashboard__mastery" aria-labelledby="mastery-title">
          <h2 id="mastery-title" className="progress-dashboard__section-title">
            <LocalizedMessage resolution={msg('learner.progress.mastery.title')} />
          </h2>
          <div className="progress-dashboard__mastery-grid">
            {Array.from(objectivesByKey.entries()).map(([key, objectives]) => (
              <div key={key} className="progress-dashboard__mastery-group">
                <h3 className="progress-dashboard__mastery-concept">{conceptLabel(key)}</h3>
                <ul className="progress-dashboard__mastery-list" aria-label={conceptLabel(key) ?? undefined}>
                  {objectives.map(obj => (
                    <li
                      key={obj.objective_id}
                      className={`progress-dashboard__mastery-item progress-dashboard__mastery-item--${obj.knowledge_state}`}
                    >
                      <span className="progress-dashboard__mastery-status" aria-hidden="true" />
                      <span className="progress-dashboard__mastery-label">
                        {obj.objective_key.replaceAll('_', ' ').replaceAll('-', ' ')} - {obj.knowledge_state === 'mastered'
                          ? <LocalizedMessage resolution={msg('learner.progress.mastery.mastered')} />
                          : <LocalizedMessage resolution={msg('learner.progress.mastery.not_mastered')} />}
                      </span>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </section>
      )}
    </div>
  )
}
