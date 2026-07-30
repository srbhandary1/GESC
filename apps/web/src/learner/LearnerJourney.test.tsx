import {
  DEFAULT_LOCALE,
  resolveMessage,
  type MessageKey,
} from '@global-english/localization'
import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type {
  AudioRecorder,
  AudioRecordingSession,
} from './BrowserAudioRecorder'
import { LearnerJourney } from './LearnerJourney'
import type {
  LearnerHome,
  LearnerJourneyApi,
  LearnerMission,
  LearnerProgress,
  MissionResult,
} from './LearnerJourneyApi'

const ids = {
  attempt: '01J00000000000000000000021',
  best: '01J00000000000000000000022',
  capture: '018f4f82-6400-7a12-8f4b-20b12bf6b6f3',
  enrollment: '01J00000000000000000000024',
  first: '01J00000000000000000000025',
  latest: '01J00000000000000000000026',
  mission: '01J00000000000000000000027',
  objective: '01J00000000000000000000028',
  publication: '01J00000000000000000000029',
} as const

beforeEach(() => {
  vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(
    () => undefined,
  )
  vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue()
  vi.spyOn(HTMLMediaElement.prototype, 'load').mockImplementation(
    () => undefined,
  )
  Object.defineProperty(URL, 'createObjectURL', {
    configurable: true,
    value: vi.fn(() => 'blob:local-recording'),
  })
  Object.defineProperty(URL, 'revokeObjectURL', {
    configurable: true,
    value: vi.fn(),
  })
})

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('deterministic learner journey', () => {
  it('onboards, records locally, retries, submits and renders server authority', async () => {
    const user = userEvent.setup()
    const api = fakeApi(incompleteHome())
    const recorder = fakeRecorder()

    render(
      <LearnerJourney
        api={api}
        audioRecorder={recorder}
        createClientId={() => ids.capture}
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    expect(
      await screen.findByRole('heading', {
        name: t('learner.onboarding.title'),
      }),
    ).toHaveFocus()

    await user.click(
      screen.getByRole('checkbox', {
        name: t('learner.onboarding.adult_confirmation'),
      }),
    )
    await user.click(
      screen.getByRole('radio', {
        name: t('learner.onboarding.goal_workplace'),
      }),
    )
    await user.click(
      screen.getByRole('button', {
        name: t('learner.onboarding.continue'),
      }),
    )

    expect(api.updateOnboarding).toHaveBeenCalledWith(
      {
        adult_confirmed: true,
        goal_key: 'workplace-confidence',
      },
      ids.capture,
    )
    await user.click(
      await screen.findByRole('button', {
        name: t('learner.home.start_mission'),
      }),
    )
    expect(
      await screen.findByRole('heading', {
        name: 'Introduce yourself',
      }),
    ).toHaveFocus()
    expect(screen.getByText('ನಿಮ್ಮ ಹೆಸರನ್ನು ಹೇಳಿ.')).toHaveAttribute(
      'lang',
      'kn-IN',
    )
    expect(
      screen
        .getAllByText('Hello, my name is Ravi.')
        .every((element) => element.getAttribute('lang') === 'en-IN'),
    ).toBe(true)

    await user.click(
      screen.getByRole('button', {
        name: t('learner.mission.continue_to_speak'),
      }),
    )
    await user.click(
      screen.getByRole('button', {
        name: t('learner.recording.start'),
      }),
    )
    await user.click(
      await screen.findByRole('button', {
        name: t('learner.recording.stop'),
      }),
    )

    expect(
      await screen.findByLabelText(
        t('learner.recording.playback_label'),
      ),
    ).toBeVisible()
    expect(URL.createObjectURL).toHaveBeenCalledOnce()

    await user.click(
      screen.getByRole('button', {
        name: t('learner.recording.retry'),
      }),
    )
    expect(URL.revokeObjectURL).toHaveBeenCalledWith(
      'blob:local-recording',
    )

    await user.click(
      screen.getByRole('button', {
        name: t('learner.recording.start'),
      }),
    )
    await user.click(
      await screen.findByRole('button', {
        name: t('learner.recording.stop'),
      }),
    )
    await user.click(
      screen.getByRole('button', {
        name: t('learner.mission.continue_to_check'),
      }),
    )
    await user.click(
      screen.getByRole('radio', { name: 'Hello, my name is Ravi.' }),
    )
    await user.click(
      screen.getByRole('button', {
        name: t('learner.mission.submit'),
      }),
    )

    expect(
      await screen.findByRole('heading', {
        name: t('learner.result.title'),
      }),
    ).toHaveFocus()
    expect(screen.getByText(t('learner.result.knowledge_mastered'))).toBeVisible()
    expect(screen.getAllByText(t('learner.result.spoken_unassessed'))).not
      .toHaveLength(0)
    expect(screen.getByText('ಪ್ರಯತ್ನ 1')).toBeVisible()
    expect(screen.getAllByText('ಪ್ರಯತ್ನ 2')).toHaveLength(2)
    expect(screen.queryByText(ids.first)).not.toBeInTheDocument()
    expect(
      screen.getAllByText(
        t('learner.result.local_capture_completed'),
      ),
    ).not.toHaveLength(0)
    expect(api.recordResponse).toHaveBeenCalledWith(
      ids.attempt,
      expect.objectContaining({
        local_recording: {
          captured: true,
          byte_size: 128,
          client_capture_id: ids.capture,
          duration_ms: 1200,
          mime_type: 'audio/webm',
        },
      }),
      ids.capture,
    )
  })

  it('allows a microphone-free check without claiming spoken mastery', async () => {
    const user = userEvent.setup()
    const api = fakeApi(completeHome())
    const recorder: AudioRecorder = {
      isSupported: () => false,
      start: vi.fn(),
    }

    render(
      <LearnerJourney
        api={api}
        audioRecorder={recorder}
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    await user.click(
      await screen.findByRole('button', {
        name: t('learner.home.start_mission'),
      }),
    )
    await user.click(
      await screen.findByRole('button', {
        name: t('learner.mission.continue_to_speak'),
      }),
    )
    await user.click(
      screen.getByRole('button', {
        name: t('learner.recording.continue_without'),
      }),
    )
    await user.click(
      screen.getByRole('radio', { name: 'Hello, my name is Ravi.' }),
    )
    await user.click(
      screen.getByRole('button', {
        name: t('learner.mission.submit'),
      }),
    )

    expect(
      await screen.findAllByText(
        t('learner.result.local_capture_not_recorded'),
      ),
    ).not.toHaveLength(0)
    expect(
      screen.getAllByText(t('learner.result.spoken_unassessed')),
    ).not.toHaveLength(0)
    expect(api.recordResponse).toHaveBeenCalledWith(
      ids.attempt,
      expect.objectContaining({ local_recording: null }),
      expect.any(String),
    )
  })

  it('retries onboarding with the same frozen payload and command id', async () => {
    const user = userEvent.setup()
    const updateOnboarding = vi
      .fn()
      .mockRejectedValueOnce(new TypeError('ambiguous network loss'))
      .mockResolvedValueOnce(completeHome())
    const api = {
      ...fakeApi(incompleteHome()),
      updateOnboarding,
    } satisfies LearnerJourneyApi

    render(
      <LearnerJourney
        api={api}
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    await user.click(
      await screen.findByRole('checkbox', {
        name: t('learner.onboarding.adult_confirmation'),
      }),
    )
    await user.click(
      screen.getByRole('radio', {
        name: t('learner.onboarding.goal_workplace'),
      }),
    )
    const submit = screen.getByRole('button', {
      name: t('learner.onboarding.continue'),
    })
    await user.click(submit)

    expect(
      await screen.findByText(t('learner.onboarding.error')),
    ).toBeVisible()
    const travelGoal = screen.getByRole('radio', {
      name: t('learner.onboarding.goal_travel'),
    })
    expect(travelGoal).toBeDisabled()
    await user.click(travelGoal)
    expect(
      screen.getByRole('radio', {
        name: t('learner.onboarding.goal_workplace'),
      }),
    ).toBeChecked()

    await user.click(submit)
    await screen.findByRole('heading', {
      name: t('learner.home.title'),
    })

    expect(updateOnboarding).toHaveBeenCalledTimes(2)
    expect(updateOnboarding.mock.calls[1]?.[0]).toEqual(
      updateOnboarding.mock.calls[0]?.[0],
    )
    expect(updateOnboarding.mock.calls[1]?.[1]).toBe(
      updateOnboarding.mock.calls[0]?.[1],
    )
  })

  it('retries an attempt response with the same frozen payload and command id', async () => {
    const user = userEvent.setup()
    const recordResponse = vi
      .fn()
      .mockRejectedValueOnce(new TypeError('ambiguous network loss'))
      .mockResolvedValueOnce(undefined)
    const api = {
      ...fakeApi(completeHome()),
      recordResponse,
    } satisfies LearnerJourneyApi
    const recorder: AudioRecorder = {
      isSupported: () => false,
      start: vi.fn(),
    }

    render(
      <LearnerJourney
        api={api}
        audioRecorder={recorder}
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    await user.click(
      await screen.findByRole('button', {
        name: t('learner.home.start_mission'),
      }),
    )
    await user.click(
      await screen.findByRole('button', {
        name: t('learner.mission.continue_to_speak'),
      }),
    )
    await user.click(
      screen.getByRole('button', {
        name: t('learner.recording.continue_without'),
      }),
    )
    const correct = screen.getByRole('radio', {
      name: 'Hello, my name is Ravi.',
    })
    const incorrect = screen.getByRole('radio', {
      name: 'Goodbye yesterday.',
    })
    await user.click(correct)
    const submit = screen.getByRole('button', {
      name: t('learner.mission.submit'),
    })
    await user.click(submit)

    expect(
      await screen.findByText(t('learner.mission.submit_error')),
    ).toBeVisible()
    expect(correct).toBeChecked()
    expect(correct).toBeDisabled()
    expect(incorrect).toBeDisabled()
    await user.click(incorrect)
    expect(correct).toBeChecked()

    await user.click(submit)
    await screen.findByRole('heading', {
      name: t('learner.result.title'),
    })

    expect(recordResponse).toHaveBeenCalledTimes(2)
    expect(recordResponse.mock.calls[1]?.[1]).toEqual(
      recordResponse.mock.calls[0]?.[1],
    )
    expect(recordResponse.mock.calls[1]?.[2]).toBe(
      recordResponse.mock.calls[0]?.[2],
    )
  })

  it('shows the authoritative submit result without waiting for progress', async () => {
    const user = userEvent.setup()
    const api = {
      ...fakeApi(completeHome()),
      progress: vi.fn(
        () => new Promise<LearnerProgress>(() => undefined),
      ),
    } satisfies LearnerJourneyApi
    const recorder: AudioRecorder = {
      isSupported: () => false,
      start: vi.fn(),
    }

    render(
      <LearnerJourney
        api={api}
        audioRecorder={recorder}
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    await user.click(
      await screen.findByRole('button', {
        name: t('learner.home.start_mission'),
      }),
    )
    await user.click(
      screen.getByRole('button', {
        name: t('learner.mission.continue_to_speak'),
      }),
    )
    await user.click(
      screen.getByRole('button', {
        name: t('learner.recording.continue_without'),
      }),
    )
    await user.click(
      screen.getByRole('radio', {
        name: 'Hello, my name is Ravi.',
      }),
    )
    await user.click(
      screen.getByRole('button', {
        name: t('learner.mission.submit'),
      }),
    )

    expect(
      await screen.findByRole('heading', {
        name: t('learner.result.title'),
      }),
    ).toBeVisible()
  })
})

function fakeApi(initialHome: LearnerHome): LearnerJourneyApi {
  const api: LearnerJourneyApi = {
    home: vi.fn(async () => initialHome),
    updateOnboarding: vi.fn(async () => completeHome()),
    mission: vi.fn(async () => mission),
    startAttempt: vi.fn(async () => ({
      id: ids.attempt,
      status: 'in_progress' as const,
    })),
    recordResponse: vi.fn(async () => undefined),
    submitAttempt: vi.fn(async () =>
      initialHome.onboarding.completed ? noSpeechResult : result,
    ),
    progress: vi.fn(async () =>
      initialHome.onboarding.completed ? noSpeechProgress : progress,
    ),
  }

  return api
}

function fakeRecorder(): AudioRecorder {
  return {
    isSupported: () => true,
    start: vi.fn(async (): Promise<AudioRecordingSession> => ({
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob(['x'.repeat(128)], { type: 'audio/webm' }),
        durationMs: 1200,
        mimeType: 'audio/webm',
      })),
    })),
  }
}

function incompleteHome(): LearnerHome {
  return {
    enrollment: null,
    next_mission: null,
    onboarding: {
      adult_confirmed: false,
      completed: false,
      goal_key: null,
    },
  }
}

function completeHome(): LearnerHome {
  return {
    enrollment: { id: ids.enrollment, status: 'active' },
    next_mission: {
      estimated_minutes: 5,
      id: ids.mission,
      title: content('en-IN', 'Introduce yourself'),
    },
    onboarding: {
      adult_confirmed: true,
      completed: true,
      goal_key: 'workplace-confidence',
    },
  }
}

const mission: LearnerMission = {
  check: {
    id: '01J00000000000000000000030',
    options: [
      {
      id: '01J00000000000000000000031',
        label: content('en-IN', 'Hello, my name is Ravi.'),
      },
      {
        id: '01J00000000000000000000032',
        label: content('en-IN', 'Goodbye yesterday.'),
      },
    ],
    prompt: content('en-IN', 'Choose the correct introduction.'),
  },
  enrollment_id: ids.enrollment,
  id: ids.mission,
  kannada_guidance: content('kn-IN', 'ನಿಮ್ಮ ಹೆಸರನ್ನು ಹೇಳಿ.'),
  model_audio: {
    asset_id: '01J00000000000000000000033',
    duration_ms: 1800,
    mime_type: 'audio/wav',
    stream_url: '/api/v1/media-assets/01J00000000000000000000033/content',
  },
  model_text: content('en-IN', 'Hello, my name is Ravi.'),
  objective_id: ids.objective,
  publication_id: ids.publication,
  title: content('en-IN', 'Introduce yourself'),
  transcript: content('en-IN', 'Hello, my name is Ravi.'),
}

const result: MissionResult = {
  attempt_id: ids.attempt,
  can_do: content('en-IN', 'I can introduce myself.'),
  check_state: 'correct',
  evidence: {
    best_attempt_id: ids.best,
    best_attempt_number: 2,
    first_attempt_id: ids.first,
    first_attempt_number: 1,
    latest_attempt_id: ids.latest,
    latest_attempt_number: 2,
  },
  knowledge_state: 'mastered',
  local_capture_receipt_present: true,
  review: {
    due_at: '2026-08-01T09:00:00Z',
    timezone: 'Asia/Kolkata',
  },
  spoken_state: 'unassessed',
}

const noSpeechResult: MissionResult = {
  ...result,
  knowledge_state: 'mastered',
  local_capture_receipt_present: false,
  spoken_state: 'unassessed',
}

const progress: LearnerProgress = {
  enrollment_id: ids.enrollment,
  objectives: [
    {
      evidence: result.evidence,
      knowledge_state: 'mastered',
      label: content('en-IN', 'Introduce myself'),
      local_capture_receipt_present: true,
      objective_id: ids.objective,
      review_due_at: result.review.due_at,
      review_timezone: result.review.timezone,
      spoken_state: 'unassessed',
    },
  ],
}

const noSpeechProgress: LearnerProgress = {
  ...progress,
  objectives: progress.objectives.map((objective) => ({
    ...objective,
    local_capture_receipt_present: false,
  })),
}

function content(
  locale: 'en-IN' | 'kn-IN',
  text: string,
) {
  return {
    content_hash: 'a'.repeat(64),
    locale,
    revision_id: '01J00000000000000000000034',
    text,
  } as const
}

function t(key: MessageKey): string {
  return resolveMessage(key, {
    policy: 'development-preview',
    requestedLocale: DEFAULT_LOCALE,
  }).text
}
