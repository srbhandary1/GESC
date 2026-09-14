import {
  DEFAULT_LOCALE,
  resolveMessage,
  type MessageKey,
} from '@global-english/localization'
import { cleanup, render, screen, waitFor, within } from '@testing-library/react'
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
  OfflineBundle,
} from './LearnerJourneyApi'
import type {
  OfflineLearningStore,
  OfflineProfileBinding,
} from '../offline/OfflineLearningStore'

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
  Object.defineProperty(globalThis.navigator, 'onLine', {
    configurable: true,
    value: true,
  })
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

    const onboardingTitle = await screen.findByRole('heading', {
      name: t('learner.onboarding.title'),
    })
    await waitFor(() => expect(onboardingTitle).toHaveFocus())

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
        name: t('learner.home.continue'),
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
        name: t('learner.home.continue'),
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
        name: t('learner.home.continue'),
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
        name: t('learner.home.continue'),
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

  it('downloads with personal-device consent, completes offline, and syncs one immutable command', async () => {
    const user = userEvent.setup()
    const binding: OfflineProfileBinding = {
      membershipId: '01J00000000000000000000041',
      organizationId: '01J00000000000000000000042',
      userId: '01J00000000000000000000043',
    }
    const storedBundle = {
      audio: new Uint8Array([1, 2, 3]),
      bundle: offlineBundle(binding),
    }
    let enabled = false
    const store: OfflineLearningStore = {
      acceptReceipt: vi.fn(async () => undefined),
      available: () => true,
      enablePersonalDevice: vi.fn(async () => {
        enabled = true
      }),
      hasProfile: vi.fn(async () => enabled),
      loadBundle: vi.fn(async () => storedBundle),
      markNeedsAttention: vi.fn(async () => undefined),
      pendingCommands: vi.fn(async () => []),
      prepareLogout: vi.fn(async () => undefined),
      purge: vi.fn(async () => undefined),
      purgeAll: vi.fn(async () => undefined),
      queueCompletion: vi.fn(async () => undefined),
      resumePersonalDevice: vi.fn(async () => null),
      saveBundle: vi.fn(async () => undefined),
    }
    const api = {
      ...fakeApi(completeHome()),
      completeOffline: vi.fn(async () => noSpeechResult),
      downloadMedia: vi.fn(async () => storedBundle.audio),
      offlineBundle: vi.fn(async () => storedBundle.bundle),
    }

    render(
      <LearnerJourney
        api={api}
        audioRecorder={{
          isSupported: () => false,
          start: vi.fn(),
        }}
        createClientId={() => ids.capture}
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
        offlineBinding={binding}
        offlineStore={store}
      />,
    )

    await user.click(
      await screen.findByRole('checkbox', {
        name: t('learner.offline.personal_confirmation'),
      }),
    )
    await user.click(
      screen.getByRole('button', {
        name: t('learner.offline.download'),
      }),
    )

    expect(store.enablePersonalDevice).toHaveBeenCalledWith(binding)
    expect(store.saveBundle).toHaveBeenCalledWith(
      binding,
      storedBundle.bundle,
      storedBundle.audio,
    )

    Object.defineProperty(globalThis.navigator, 'onLine', {
      configurable: true,
      value: false,
    })
    globalThis.dispatchEvent(new Event('offline'))
    await user.click(
      screen.getByRole('button', {
        name: t('learner.home.continue'),
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
        name: t('learner.offline.saved'),
      }),
    ).toBeVisible()
    expect(store.queueCompletion).toHaveBeenCalledWith(
      binding,
      expect.objectContaining({
        body: expect.objectContaining({
          bundle_id: storedBundle.bundle.manifest.bundle_id,
          local_recording: null,
          selected_option_id: mission.check.options[0]?.id,
        }),
        commandId: ids.capture,
        state: 'pending',
      }),
    )
    expect(api.startAttempt).not.toHaveBeenCalled()
    expect(api.recordResponse).not.toHaveBeenCalled()

    vi.mocked(store.pendingCommands).mockResolvedValue([
      vi.mocked(store.queueCompletion).mock.calls[0]![1],
    ])
    Object.defineProperty(globalThis.navigator, 'onLine', {
      configurable: true,
      value: true,
    })
    globalThis.dispatchEvent(new Event('online'))

    expect(
      await screen.findByRole('heading', {
        name: t('learner.result.title'),
      }),
    ).toBeVisible()
    expect(api.completeOffline).toHaveBeenCalledOnce()
    expect(store.acceptReceipt).toHaveBeenCalledOnce()
  })

  it('shows Level videos on home and switches the authenticated stream source', async () => {
    const user = userEvent.setup()
    const listLevelVideos = vi.fn(async (enrollmentId: string) => ({
      enrollment_id: enrollmentId,
      videos: [
        {
          enrollment_id: enrollmentId,
          lesson_id: '01J00000000000000000000041',
          level_label: 'Level 1',
          publication_id: ids.publication,
          title: { locale: 'en-IN' as const, text: 'Level 1 introduction' },
          video: {
            asset_id: '01J00000000000000000000051',
            byte_size: 11515204,
            mime_type: 'video/mp4' as const,
            stream_url: '/api/v1/media-assets/01J00000000000000000000051/lesson-video',
          },
        },
        {
          enrollment_id: enrollmentId,
          lesson_id: '01J00000000000000000000042',
          level_label: 'Level 2',
          publication_id: ids.publication,
          title: { locale: 'en-IN' as const, text: 'Level 2 introduction' },
          video: {
            asset_id: '01J00000000000000000000052',
            byte_size: 2048,
            mime_type: 'video/mp4' as const,
            stream_url: '/api/v1/media-assets/01J00000000000000000000052/lesson-video',
          },
        },
      ],
    }))
    const api = {
      ...fakeApi(completeHome()),
      listLevelVideos,
    } satisfies LearnerJourneyApi

    render(
      <LearnerJourney
        api={api}
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    expect(await screen.findByTestId('lesson-video-element')).toBeVisible()
    expect(screen.getByTestId('level-videos-panel')).toBeVisible()
    expect(
      screen.getByRole('heading', { name: t('learner.level_videos.title') }),
    ).toBeVisible()
    expect(listLevelVideos).toHaveBeenCalledWith(ids.enrollment, expect.any(AbortSignal))
    expect(screen.getByTestId('lesson-video-element').querySelector('source'))
      .toHaveAttribute(
        'src',
        '/api/v1/media-assets/01J00000000000000000000051/lesson-video',
      )

    await user.click(screen.getByRole('button', { name: 'Level 2' }))
    expect(screen.getByTestId('lesson-video-element').querySelector('source'))
      .toHaveAttribute(
        'src',
        '/api/v1/media-assets/01J00000000000000000000052/lesson-video',
      )
  })

  it('hides Level videos when the enrollment catalog is empty', async () => {
    const api = fakeApi(completeHome())

    render(
      <LearnerJourney
        api={api}
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    expect(
      await screen.findByRole('button', {
        name: t('learner.home.continue'),
      }),
    ).toBeVisible()
    await vi.waitFor(() => {
      expect(api.listLevelVideos).toHaveBeenCalled()
    })
    await vi.waitFor(() => {
      expect(screen.queryByTestId('level-videos-panel')).not.toBeInTheDocument()
    })
  })

  it('presents a due review before the next mission and keeps streak secondary', async () => {
    const user = userEvent.setup()
    const longKnTitle =
      'ಪರಿಚಯಗಳ ಮರುಅಭ್ಯಾಸ: ದಿನಚರಿ ಸಂಭಾಷಣೆಯಲ್ಲಿ ಸ್ವಯಂ ಪರಿಚಯ, ಕೆಲಸದ ಸ್ಥಳದಲ್ಲಿ ಸಹೋದ್ಯೋಗಿಗಳೊಂದಿಗೆ ಮಾತನಾಡುವುದು ಮತ್ತು ಅತಿಥಿಗಳನ್ನು ಸ್ವಾಗತಿಸುವುದು'
    const home = {
      ...completeHome(),
      due_review: {
        due_at: '2026-08-30T03:30:00Z',
        enrollment_id: ids.enrollment,
        id: '01J00000000000000000000061',
        interval_days: 3,
        mission_id: ids.mission,
        objective_id: ids.objective,
        timezone: 'Asia/Kolkata',
        title: content('kn-IN', longKnTitle),
      },
    } satisfies LearnerHome
    const api = fakeApi(home)

    render(
      <LearnerJourney
        api={api}
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    const dominantTitle = await screen.findByRole('heading', {
      level: 3,
      name: longKnTitle,
    })
    const dominantAction = dominantTitle.closest('.learner-home__action')
    expect(dominantAction).not.toBeNull()
    expect(screen.getByText(longKnTitle)).toHaveAttribute('lang', 'kn-IN')
    expect(screen.getByText('2 ಅರ್ಥಪೂರ್ಣ ಮಾತಿನ ದಿನಗಳು')).toBeVisible()
    expect(
      screen.getByRole('button', { name: t('learner.home.continue') }),
    ).toHaveClass('learner-home__continue')
    expect(
      screen.getByRole('link', { name: t('demo.phase1.home.speak') }),
    ).toHaveAttribute('href', '#speak')
    expect(screen.getByRole('img', { name: t('tutor.name') })).toBeVisible()
    expect(
      screen.queryByText(
        resolveMessage('learner.home.estimated_minutes', {
          policy: 'development-preview',
          requestedLocale: DEFAULT_LOCALE,
          parameters: { minutes: 5 },
        }).text,
      ),
    ).not.toBeInTheDocument()
    expect(
      screen.getByText(
        resolveMessage('learner.home.review_interval', {
          policy: 'development-preview',
          requestedLocale: DEFAULT_LOCALE,
          parameters: { days: '3' },
        }).text,
      ),
    ).toBeVisible()
    const coach = dominantAction?.querySelector('.learner-home__coach')
    expect(coach).not.toBeNull()
    expect(
      within(coach as HTMLElement).getByText(t('tutor.name')),
    ).toBeVisible()
    expect(
      within(coach as HTMLElement).getByText(t('tutor.ready')),
    ).toBeVisible()
    expect(
      coach?.querySelector('.tutor-presence__face'),
    ).toHaveAttribute('src', '/tutor/tutor-maya.png')
    expect(
      dominantAction?.querySelectorAll('.ge-button--primary'),
    ).toHaveLength(1)
    await user.click(
      screen.getByRole('button', { name: t('learner.home.continue') }),
    )
    expect(api.mission).toHaveBeenCalledWith(ids.mission, ids.enrollment)
  })

  it('lists upcoming reviews beneath the dominant action', async () => {
    const home = {
      ...completeHome(),
      upcoming_reviews: [
        {
          due_at: '2026-09-01T03:30:00Z',
          enrollment_id: ids.enrollment,
          id: '01J00000000000000000000062',
          interval_days: 7,
          mission_id: '01J00000000000000000000063',
          objective_id: ids.objective,
          timezone: 'Asia/Kolkata',
          title: content('en-IN', 'Workplace introductions review'),
        },
      ],
    } satisfies LearnerHome
    const api = fakeApi(home)

    render(
      <LearnerJourney
        api={api}
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    expect(
      await screen.findByRole('heading', {
        level: 3,
        name: t('learner.home.upcoming_reviews.title'),
      }),
    ).toBeVisible()
    expect(screen.getByText('Workplace introductions review')).toBeVisible()
    expect(screen.getByText(/Due /)).toBeVisible()
  })

  it('shows neutral streak copy when the learner has not spoken yet', async () => {
    const api = fakeApi({
      ...completeHome(),
      streak: {
        as_of_date: '2026-08-30',
        count: 0,
        timezone: 'Asia/Kolkata',
      },
    })

    render(
      <LearnerJourney
        api={api}
        locale={DEFAULT_LOCALE}
        localizationPolicy="development-preview"
      />,
    )

    expect(
      await screen.findByText(t('learner.home.streak_zero')),
    ).toBeVisible()
    expect(
      screen.getByRole('button', { name: t('learner.home.continue') }),
    ).toBeVisible()
    expect(screen.getByText(t('learner.home.guidance_kn'))).toHaveAttribute(
      'lang',
      'kn-IN',
    )
    expect(screen.getByText(t('learner.home.guidance_en'))).toHaveAttribute(
      'lang',
      'en-IN',
    )
  })
})

function fakeApi(initialHome: LearnerHome): LearnerJourneyApi {
  const api: LearnerJourneyApi = {
    home: vi.fn(async () => initialHome),
    updateOnboarding: vi.fn(async () => completeHome()),
    listLevelVideos: vi.fn(async (enrollmentId: string) => ({
      enrollment_id: enrollmentId,
      videos: [],
    })),
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
    progressDashboard: vi.fn(async () =>
      Promise.reject(new Error('no progressDashboard stubbed')),
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
    due_review: null,
    upcoming_reviews: [],
    enrollment: null,
    next_mission: null,
    onboarding: {
      adult_confirmed: false,
      completed: false,
      goal_key: null,
    },
    streak: {
      as_of_date: '2026-08-30',
      count: 0,
      timezone: 'Asia/Kolkata',
    },
  }
}

function completeHome(): LearnerHome {
  return {
    due_review: null,
    upcoming_reviews: [],
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
    streak: {
      as_of_date: '2026-08-30',
      count: 2,
      timezone: 'Asia/Kolkata',
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

function offlineBundle(
  binding: OfflineProfileBinding,
): OfflineBundle {
  return {
    lease: 'signed-offline-lease',
    manifest: {
      binding: {
        enrollment_id: ids.enrollment,
        membership_id: binding.membershipId,
        organization_id: binding.organizationId,
        user_id: binding.userId,
      },
      bundle_hash: 'b'.repeat(64),
      bundle_id: '01J00000000000000000000044',
      expires_at: '2099-08-01T10:00:00Z',
      issued_at: '2026-07-30T10:00:00Z',
      mission: {
        ...mission,
        model_audio: {
          ...mission.model_audio,
          byte_size: 3,
          content_hash: 'c'.repeat(64),
        },
        offline_check: {
          correct_option_id: mission.check.options[0]!.id,
        },
      },
      policy_version: 'daily-speaking-v1',
      publication: {
        curriculum_version_id: '01J00000000000000000000045',
        manifest_hash: 'd'.repeat(64),
        mission_definition_hash: 'e'.repeat(64),
        owner_organization_id: binding.organizationId,
        publication_id: ids.publication,
      },
      schema: 'global-english.offline-mission-bundle.v1',
    },
  }
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
