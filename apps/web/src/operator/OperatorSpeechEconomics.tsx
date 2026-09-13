import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useCallback, useEffect, useState, type FormEvent } from 'react'
import {
  resolveInterfaceMessage,
  type InterfaceMessageResolution,
} from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import type {
  OperatorConsoleApi,
  OperatorSpeechEconomicsCommercial,
  OperatorSpeechEconomicsFilters,
  OperatorSpeechEconomicsRow,
  OperatorSpeechEconomicsSummary,
} from './OperatorConsoleApi'

type MessageResolver = (key: MessageKey) => InterfaceMessageResolution

type ResourceState<T> =
  | { readonly status: 'loading' }
  | { readonly status: 'error' }
  | { readonly status: 'ready'; readonly value: T }

export interface OperatorSpeechEconomicsProps {
  readonly api: OperatorConsoleApi
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}

export function OperatorSpeechEconomics({
  api,
  locale,
  localizationPolicy,
}: OperatorSpeechEconomicsProps) {
  const message = useCallback(
    (key: MessageKey) =>
      resolveInterfaceMessage(key, locale, localizationPolicy),
    [locale, localizationPolicy],
  )
  const [draft, setDraft] = useState({
    from: '',
    to: '',
    provider_stage: '',
  })
  const [filters, setFilters] = useState<OperatorSpeechEconomicsFilters>({})
  const loadUsage = useCallback(
    (signal: AbortSignal) => api.getSpeechEconomics(filters, signal),
    [api, filters],
  )
  const usage = useOperatorResource(loadUsage)
  const loadCommercial = useCallback(
    (signal: AbortSignal) => api.getSpeechEconomicsCommercial(signal),
    [api],
  )
  const commercial = useOperatorResource(loadCommercial)
  const [exporting, setExporting] = useState(false)
  const [exportError, setExportError] = useState(false)

  const applyFilters = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFilters({
      ...(draft.from !== '' ? { from: draft.from } : {}),
      ...(draft.to !== '' ? { to: draft.to } : {}),
      ...(draft.provider_stage === 'stt' ||
      draft.provider_stage === 'model' ||
      draft.provider_stage === 'tts' ||
      draft.provider_stage === 'end_to_end'
        ? { provider_stage: draft.provider_stage }
        : {}),
    })
  }

  const exportCsv = async () => {
    setExporting(true)
    setExportError(false)

    try {
      await api.exportSpeechEconomics(filters)
    } catch {
      setExportError(true)
    } finally {
      setExporting(false)
    }
  }

  return (
    <article
      aria-labelledby="operator-speech-economics-title"
      className="operator-panel operator-panel--wide"
    >
      <h4 id="operator-speech-economics-title">
        <LocalizedMessage
          resolution={message('operator.speech_economics.title')}
        />
      </h4>
      <p>
        <LocalizedMessage
          resolution={message('operator.speech_economics.lead')}
        />
      </p>
      <form className="operator-economics-filters" onSubmit={applyFilters}>
        <label>
          <LocalizedMessage
            resolution={message('operator.speech_economics.from')}
          />
          <input
            onChange={(event) =>
              setDraft((current) => ({ ...current, from: event.target.value }))
            }
            type="date"
            value={draft.from}
          />
        </label>
        <label>
          <LocalizedMessage
            resolution={message('operator.speech_economics.to')}
          />
          <input
            onChange={(event) =>
              setDraft((current) => ({ ...current, to: event.target.value }))
            }
            type="date"
            value={draft.to}
          />
        </label>
        <label>
          <LocalizedMessage
            resolution={message('operator.speech_economics.stage')}
          />
          <select
            onChange={(event) =>
              setDraft((current) => ({
                ...current,
                provider_stage: event.target.value,
              }))
            }
            value={draft.provider_stage}
          >
            <option value="">
              {message('operator.speech_economics.stage_all').text}
            </option>
            <option value="stt">stt</option>
            <option value="model">model</option>
            <option value="tts">tts</option>
            <option value="end_to_end">end_to_end</option>
          </select>
        </label>
        <button type="submit">
          <LocalizedMessage
            resolution={message('operator.speech_economics.apply_filters')}
          />
        </button>
        <button
          disabled={exporting || usage.state.status !== 'ready'}
          onClick={() => {
            void exportCsv()
          }}
          type="button"
        >
          <LocalizedMessage
            resolution={message(
              exporting
                ? 'operator.speech_economics.exporting'
                : 'operator.speech_economics.export',
            )}
          />
        </button>
      </form>
      {exportError ? (
        <p className="operator-panel__inline-error" role="alert">
          <LocalizedMessage
            resolution={message('operator.speech_economics.export_error')}
          />
        </p>
      ) : null}
      {usage.state.status === 'loading' ? (
        <p className="operator-panel__loading" role="status">
          <LocalizedMessage resolution={message('operator.panel.loading')} />
        </p>
      ) : null}
      {usage.state.status === 'error' ? (
        <div className="operator-panel__failure" role="alert">
          <p>
            <LocalizedMessage resolution={message('operator.panel.error')} />
          </p>
          <button onClick={usage.retry} type="button">
            <LocalizedMessage resolution={message('operator.retry')} />
          </button>
        </div>
      ) : null}
      {usage.state.status === 'ready' ? (
        <UsageView message={message} summary={usage.state.value} />
      ) : null}
      {commercial.state.status === 'ready' &&
      commercial.state.value !== null ? (
        <CommercialView
          commercial={commercial.state.value}
          message={message}
        />
      ) : null}
    </article>
  )
}

function UsageView({
  message,
  summary,
}: Readonly<{
  message: MessageResolver
  summary: OperatorSpeechEconomicsSummary
}>) {
  if (summary.empty) {
    return (
      <p>
        <LocalizedMessage
          resolution={message('operator.speech_economics.empty')}
        />
      </p>
    )
  }

  return (
    <div className="operator-economics">
      {summary.partial ? (
        <p className="operator-economics-banner" role="status">
          <LocalizedMessage
            resolution={message('operator.speech_economics.partial')}
          />
        </p>
      ) : null}
      {summary.stale_rate ? (
        <p className="operator-economics-banner" role="status">
          <LocalizedMessage
            resolution={message('operator.speech_economics.stale_rate')}
          />
        </p>
      ) : null}
      {summary.unreconciled ? (
        <p className="operator-economics-banner" role="status">
          <LocalizedMessage
            resolution={message('operator.speech_economics.unreconciled')}
          />
        </p>
      ) : null}
      <div className="operator-metrics">
        <Metric
          label={message('operator.speech_economics.attempts')}
          value={String(summary.totals.attempt_count)}
        />
        <Metric
          label={message('operator.speech_economics.successes')}
          value={String(summary.totals.success_count)}
        />
        <Metric
          label={message('operator.speech_economics.allocated_cost')}
          value={formatMicrounits(summary.totals.allocated_inr_microunits)}
        />
        <Metric
          label={message('operator.speech_economics.cost_per_turn')}
          value={formatMicrounits(
            summary.totals.success_count > 0
              ? Math.trunc(
                  summary.totals.allocated_inr_microunits /
                    summary.totals.success_count,
                )
              : null,
          )}
        />
      </div>
      <div className="operator-economics-table-wrap">
        <table className="operator-economics-table">
          <caption>
            <LocalizedMessage
              resolution={message('operator.speech_economics.rows')}
            />
          </caption>
          <thead>
            <tr>
              <th scope="col">
                <LocalizedMessage
                  resolution={message('operator.speech_economics.column.date')}
                />
              </th>
              <th scope="col">
                <LocalizedMessage
                  resolution={message(
                    'operator.speech_economics.column.provider',
                  )}
                />
              </th>
              <th scope="col">
                <LocalizedMessage
                  resolution={message('operator.speech_economics.column.stage')}
                />
              </th>
              <th scope="col">
                <LocalizedMessage
                  resolution={message(
                    'operator.speech_economics.column.attempts',
                  )}
                />
              </th>
              <th scope="col">
                <LocalizedMessage
                  resolution={message(
                    'operator.speech_economics.column.successes',
                  )}
                />
              </th>
              <th scope="col">
                <LocalizedMessage
                  resolution={message(
                    'operator.speech_economics.column.allocated',
                  )}
                />
              </th>
              <th scope="col">
                <LocalizedMessage
                  resolution={message(
                    'operator.speech_economics.column.cost_per_turn',
                  )}
                />
              </th>
              <th scope="col">
                <LocalizedMessage
                  resolution={message('operator.speech_economics.column.p95')}
                />
              </th>
            </tr>
          </thead>
          <tbody>
            {summary.rows.map((row) => (
              <EconomicsRow key={rowKey(row)} row={row} />
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}

function EconomicsRow({ row }: Readonly<{ row: OperatorSpeechEconomicsRow }>) {
  return (
    <tr>
      <td>{row.usage_date}</td>
      <td>{row.provider_key}</td>
      <td>{row.provider_stage}</td>
      <td>{formatCount(row.attempt_count)}</td>
      <td>{formatCount(row.success_count)}</td>
      <td>{formatMicrounits(row.allocated_inr_microunits)}</td>
      <td>{formatMicrounits(row.cost_per_successful_turn_inr_microunits)}</td>
      <td>{formatCount(row.first_audible_p95_ms)}</td>
    </tr>
  )
}

function CommercialView({
  commercial,
  message,
}: Readonly<{
  commercial: OperatorSpeechEconomicsCommercial
  message: MessageResolver
}>) {
  return (
    <section
      aria-labelledby="operator-speech-economics-commercial-title"
      className="operator-economics-commercial"
    >
      <h5 id="operator-speech-economics-commercial-title">
        <LocalizedMessage
          resolution={message('operator.speech_economics.commercial.title')}
        />
      </h5>
      <p>
        <LocalizedMessage
          resolution={message('operator.speech_economics.commercial.lead')}
        />
      </p>
      {commercial.empty ? (
        <p>
          <LocalizedMessage
            resolution={message('operator.speech_economics.commercial.empty')}
          />
        </p>
      ) : (
        <ul className="operator-panel__list">
          {commercial.rate_cards.map((card) => (
            <li key={card.id}>
              <strong>{card.provider_key}</strong>
              <code>{card.rate_version}</code>
              <span>
                {card.billing_currency} {card.gross_price_microunits}
              </span>
            </li>
          ))}
          {commercial.statements.map((statement) => (
            <li key={statement.id}>
              <strong>{statement.provider_key}</strong>
              <span>
                {statement.period_start}–{statement.period_end}
              </span>
              <span>{formatMicrounits(statement.net_cost_inr_microunits)}</span>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

function Metric({
  label,
  value,
}: Readonly<{
  label: InterfaceMessageResolution
  value: string
}>) {
  return (
    <div className="operator-metric">
      <span>
        <LocalizedMessage resolution={label} />
      </span>
      <strong>{value}</strong>
    </div>
  )
}

function formatCount(value: number | null): string {
  return value === null ? '—' : String(value)
}

function formatMicrounits(value: number | null): string {
  if (value === null) {
    return '—'
  }

  return `₹${(value / 1_000_000).toFixed(2)}`
}

function rowKey(row: OperatorSpeechEconomicsRow): string {
  return [
    row.usage_date,
    row.environment,
    row.provider_stage,
    row.provider_key,
    row.model_version,
    row.route_role,
    row.locale,
    row.conversation_mode,
    row.result_status,
    row.dispatch_kind,
    row.billable_unit_kind,
  ].join(':')
}

function useOperatorResource<T>(
  load: (signal: AbortSignal) => Promise<T>,
): Readonly<{
  retry: () => void
  state: ResourceState<T>
}> {
  const [attempt, setAttempt] = useState(0)
  const [state, setState] = useState<ResourceState<T>>({
    status: 'loading',
  })

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    void load(controller.signal)
      .then((value) => {
        if (active) {
          setState({ status: 'ready', value })
        }
      })
      .catch(() => {
        if (active && !controller.signal.aborted) {
          setState({ status: 'error' })
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [attempt, load])

  return {
    retry: () => {
      setState({ status: 'loading' })
      setAttempt((current) => current + 1)
    },
    state,
  }
}
