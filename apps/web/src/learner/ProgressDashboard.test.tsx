import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ProgressDashboard } from './ProgressDashboard'
import type { LearnerJourneyApi } from './LearnerJourneyApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('ProgressDashboard', () => {
  it('renders loading state initially', () => {
    const api = {
      progressDashboard: () => new Promise(() => {}),
    } as unknown as LearnerJourneyApi

    render(<ProgressDashboard api={api} locale="en-IN" onMissionRequested={vi.fn()} />)
    
    expect(screen.getByText('Loading audio', { exact: false })).toBeDefined()
  })

  it('renders empty state when no activity', async () => {
    const user = userEvent.setup()
    const onMissionRequested = vi.fn()
    const api = {
      progressDashboard: vi.fn().mockResolvedValue({
        enrollment: { id: 'enrollment-1', status: 'active' },
        objectives: [],
        speaking_activity: {
          meaningful_speaking_days_count: 0,
          completed_turn_count: 0,
          speaking_duration_ms: 0,
          speaking_minutes: 0,
          timezone: 'Asia/Kolkata',
        },
        attempt_improvement: {
          missions_attempted: 0,
          missions_passed_on_first_attempt: 0,
          missions_improved_on_retry: 0,
          missions_still_not_mastered: 0,
        },
        delayed_review: {
          scheduled_count: 0,
          due_count: 0,
          pending_count: 0,
          completed_count: 0,
          completed_on_time_count: 0,
          completed_late_count: 0,
        },
        recurring_errors: { patterns: [] },
      }),
    } as unknown as LearnerJourneyApi

    render(<ProgressDashboard api={api} locale="en-IN" onMissionRequested={onMissionRequested} />)

    await waitFor(() => {
      expect(screen.getByRole('button')).toBeDefined()
    })

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Your progress will appear here')

    await user.click(screen.getByRole('button'))
    expect(onMissionRequested).toHaveBeenCalled()
  })

  it('renders dashboard with stats and mastery', async () => {
    const api = {
      progressDashboard: vi.fn().mockResolvedValue({
        enrollment: { id: 'enrollment-1', status: 'active' },
        objectives: [
          {
            objective_id: 'obj-1',
            objective_key: 'can',
            knowledge_state: 'mastered',
            spoken_state: 'unassessed',
            best_knowledge_score: 100,
            mastered_at: '2026-08-30T10:00:00Z',
            evidence_attempt_id: 'attempt-1',
          },
          {
            objective_id: 'obj-2',
            objective_key: 'should',
            knowledge_state: 'not_mastered',
            spoken_state: 'unassessed',
            best_knowledge_score: 0,
            mastered_at: null,
            evidence_attempt_id: 'attempt-2',
          },
        ],
        speaking_activity: {
          meaningful_speaking_days_count: 5,
          completed_turn_count: 10,
          speaking_duration_ms: 120000,
          speaking_minutes: 2,
          timezone: 'Asia/Kolkata',
        },
        attempt_improvement: {
          missions_attempted: 2,
          missions_passed_on_first_attempt: 1,
          missions_improved_on_retry: 1,
          missions_still_not_mastered: 0,
        },
        delayed_review: {
          scheduled_count: 1,
          due_count: 1,
          pending_count: 0,
          completed_count: 0,
          completed_on_time_count: 0,
          completed_late_count: 0,
        },
        recurring_errors: {
          patterns: [
            {
              error_category: 'grammar',
              observation_count: 2,
              first_observed_at: '2026-08-20T10:00:00Z',
              last_observed_at: '2026-08-29T10:00:00Z',
              trend: 'reducing',
            },
          ],
        },
      }),
    } as unknown as LearnerJourneyApi

    render(<ProgressDashboard api={api} locale="en-IN" onMissionRequested={vi.fn()} />)

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1 })).toBeDefined()
    })

    const summarySection = screen.getByLabelText('Summary statistics')
    expect(summarySection).toHaveTextContent('5')
    expect(summarySection).toHaveTextContent('1') // due count missed

    const improvementList = screen.getByLabelText('Improvement highlights')
    expect(improvementList).toHaveTextContent('passed on first attempt')
    expect(improvementList).toHaveTextContent('improved on retry')
    expect(improvementList).toHaveTextContent('grammar')
    expect(improvementList).toHaveTextContent('reducing trend')

    const masteryList = screen.getByLabelText('Can — ability and permission')
    expect(masteryList).toHaveTextContent('Mastered')
  })
})
