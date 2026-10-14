import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { createRef } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { VoiceConsents } from '../conversation/ConversationJourneyApi'
import type { AudioRecorder, AudioRecordingSession } from './BrowserAudioRecorder'
import {
  FirstUseOnboarding,
} from './FirstUseOnboarding'
import {
  FIRST_USE_RESUME_TTL_MILLISECONDS,
  firstUseResumeStorageKey,
  writeFirstUseResume,
} from './FirstUseOnboardingResume'
import type { LearnerHome, LearnerJourneyApi } from './LearnerJourneyApi'
import type { OfflineProfileBinding } from '../offline/OfflineLearningStore'

const learnerA: OfflineProfileBinding = {
  membershipId: 'membership-a',
  organizationId: 'organization-a',
  userId: 'user-a',
}
const learnerB: OfflineProfileBinding = {
  membershipId: 'membership-b',
  organizationId: 'organization-a',
  userId: 'user-b',
}

beforeEach(() => {
  window.localStorage.clear()
  window.history.replaceState({}, '', '#home')
  Object.defineProperty(globalThis.navigator, 'onLine', {
    configurable: true,
    value: true,
  })
})

afterEach(() => {
  cleanup()
  window.localStorage.clear()
  vi.restoreAllMocks()
})

describe('production first-use onboarding', () => {
  it('runs the ordered flow, keeps both consents unselected, and persists no typed content', async () => {
    const user = userEvent.setup()
    const recorder = recorderStub(false)
    const playAudioOutput = vi.fn(async () => undefined)
    const updateOnboarding = vi.fn(async () => completeHome())
    const grantVoiceConsent = vi.fn(async (input) => ({ purpose: input.purpose }))
    const onComplete = vi.fn()

    render(
      <FirstUseOnboarding
        api={{ ...apiStub(), updateOnboarding }}
        audioRecorder={recorder}
        conversationApi={{
          grantVoiceConsent: grantVoiceConsent as never,
          voiceConsents: vi.fn(async () => consentListing),
        }}
        createClientId={() => 'command-id'}
        initialHome={incompleteHome()}
        locale="en-IN"
        localizationPolicy="development-preview"
        onComplete={onComplete}
        playAudioOutput={playAudioOutput}
        resumeBinding={learnerA}
        titleRef={createRef<HTMLHeadingElement>()}
      />,
    )

    await expectHeading('Set up English learning for your goals')
    expect(screen.getByRole('button', { name: 'Get started' })).toBeEnabled()
    await continueFlow(user)
    await expectHeading('Choose your interface preferences')
    await user.click(screen.getByRole('checkbox', {
      name: 'Use larger text for this session',
    }))
    await continueFlow(user)
    await expectHeading('What do you want to speak English for?')
    await user.click(screen.getByRole('radio', {
      name: 'Speak with confidence at work',
    }))
    await continueFlow(user)

    expect(updateOnboarding).toHaveBeenCalledWith(
      { adult_confirmed: true, goal_key: 'workplace-confidence' },
      'command-id',
      expect.any(AbortSignal),
    )
    await expectHeading('Where would you like to begin?')
    await user.click(screen.getByRole('radio', { name: /Start from the beginning/ }))
    await continueFlow(user)
    await expectHeading('Check your audio output')
    await user.click(screen.getByRole('button', { name: 'Play output sound' }))
    expect(playAudioOutput).toHaveBeenCalledOnce()
    expect(recorder.start).not.toHaveBeenCalled()
    await continueFlow(user)

    await expectHeading('Choose how voice features may use your data')
    const upload = screen.getByRole('checkbox', {
      name: /Private audio upload and transcription/,
    })
    const provider = screen.getByRole('checkbox', {
      name: /Automated conversation processing/,
    })
    expect(upload).not.toBeChecked()
    expect(provider).not.toBeChecked()
    expect(
      screen.getByRole('button', { name: 'Continue without voice' }),
    ).toBeEnabled()
    await user.click(upload)
    await user.click(provider)
    expect(
      screen.getByRole('button', { name: 'Save voice choices and continue' }),
    ).toBeEnabled()
    await continueFlow(user)
    expect(grantVoiceConsent).toHaveBeenCalledTimes(2)
    expect(grantVoiceConsent).toHaveBeenCalledWith(
      { purpose: 'voice_upload_and_transcription', requested_locale: 'en-IN' },
      'command-id',
    )
    expect(grantVoiceConsent).toHaveBeenCalledWith(
      { purpose: 'conversation_provider_processing', requested_locale: 'en-IN' },
      'command-id',
    )

    await expectHeading('Try the microphone, if you want')
    await user.click((await screen.findAllByRole('button', { name: 'Type instead' }))[0]!)
    expect(recorder.start).not.toHaveBeenCalled()
    await continueFlow(user)
    await expectHeading('Make one short English turn')
    await user.click(screen.getByRole('radio', { name: 'Type a reply' }))
    const reply = screen.getByRole('textbox', { name: 'Your short English reply' })
    await user.type(reply, 'Good morning from a private draft.')
    const storageKey = await firstUseResumeStorageKey(learnerA)
    expect(window.localStorage.getItem(storageKey)).not.toContain(
      'Good morning from a private draft.',
    )
    await continueFlow(user)
    await expectHeading('Your learning setup is ready')
    await user.click(screen.getByRole('button', { name: 'Open Home' }))
    expect(onComplete).toHaveBeenCalledWith(completeHome())
    await waitFor(() => expect(window.localStorage.getItem(storageKey)).toBeNull())
  })

  it('restores the last safe step without restoring learner text, locale, or consent choices', async () => {
    await writeFirstUseResume(learnerA, {
      goal: 'workplace-confidence',
      placement: 'deferred',
      step: 'practice',
    })

    render(
      <FirstUseOnboarding
        api={apiStub()}
        audioRecorder={recorderStub(false)}
        createClientId={() => 'command-id'}
        initialHome={completeHome()}
        locale="kn-IN"
        localizationPolicy="development-preview"
        onComplete={vi.fn()}
        resumeBinding={learnerA}
        titleRef={createRef<HTMLHeadingElement>()}
      />,
    )

    await expectHeading('ಒಂದು ಚಿಕ್ಕ ಇಂಗ್ಲಿಷ್ ಉತ್ತರ ನೀಡಿ')
    expect(
      screen.queryByText('Make one short English turn'),
    ).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    const stored = window.localStorage.getItem(
      await firstUseResumeStorageKey(learnerA),
    ) ?? ''
    expect(stored).not.toContain('locale')
    expect(stored).not.toContain('consent')
    expect(stored).not.toContain('text')
    expect(stored).not.toContain('adultConfirmed')
    expect(stored).not.toContain('onboardingSaved')
  })

  it('uses the contextual recorder only after the deliberate microphone action', async () => {
    const user = userEvent.setup()
    await writeFirstUseResume(learnerA, {
      goal: 'workplace-confidence',
      placement: 'beginning',
      step: 'microphone',
    })
    const recorder = recorderStub(true)

    render(
      <FirstUseOnboarding
        api={apiStub()}
        audioRecorder={recorder}
        createClientId={() => 'command-id'}
        initialHome={completeHome()}
        locale="en-IN"
        localizationPolicy="development-preview"
        onComplete={vi.fn()}
        resumeBinding={learnerA}
        titleRef={createRef<HTMLHeadingElement>()}
      />,
    )

    expect(recorder.start).not.toHaveBeenCalled()
    await user.click(await screen.findByRole('button', { name: 'Start recording' }))
    expect(recorder.start).not.toHaveBeenCalled()
    await user.click(screen.getByRole('button', { name: 'Continue to microphone' }))
    expect(recorder.start).toHaveBeenCalledOnce()
  })

  it('does not read learner A resume state under learner B binding', async () => {
    await writeFirstUseResume(learnerA, {
      goal: 'workplace-confidence',
      placement: 'deferred',
      step: 'practice',
    })

    render(
      <FirstUseOnboarding
        api={apiStub()}
        audioRecorder={recorderStub(false)}
        createClientId={() => 'command-id'}
        initialHome={incompleteHome()}
        locale="en-IN"
        localizationPolicy="development-preview"
        onComplete={vi.fn()}
        resumeBinding={learnerB}
        titleRef={createRef<HTMLHeadingElement>()}
      />,
    )

    await expectHeading('Set up English learning for your goals')
    expect(screen.queryByText('Make one short English turn')).not.toBeInTheDocument()
  })

  it('discards an expired resume record', async () => {
    await writeFirstUseResume(
      learnerA,
      {
        goal: 'workplace-confidence',
        placement: 'deferred',
        step: 'practice',
      },
      Date.now() - FIRST_USE_RESUME_TTL_MILLISECONDS - 1,
    )

    render(
      <FirstUseOnboarding
        api={apiStub()}
        audioRecorder={recorderStub(false)}
        createClientId={() => 'command-id'}
        initialHome={incompleteHome()}
        locale="en-IN"
        localizationPolicy="development-preview"
        onComplete={vi.fn()}
        resumeBinding={learnerA}
        titleRef={createRef<HTMLHeadingElement>()}
      />,
    )

    await expectHeading('Set up English learning for your goals')
    expect(screen.queryByText('Make one short English turn')).not.toBeInTheDocument()
  })

  it('restores to preferences when onboarding is not yet saved on the server', async () => {
    await writeFirstUseResume(learnerA, {
      goal: 'workplace-confidence',
      placement: 'deferred',
      step: 'practice',
    })
    const storageKey = await firstUseResumeStorageKey(learnerA)
    const stored = JSON.parse(
      window.localStorage.getItem(storageKey) ?? '{}',
    ) as Record<string, unknown>
    window.localStorage.setItem(storageKey, JSON.stringify({
      ...stored,
      onboardingSaved: true,
    }))

    render(
      <FirstUseOnboarding
        api={apiStub()}
        audioRecorder={recorderStub(false)}
        createClientId={() => 'command-id'}
        initialHome={incompleteHome()}
        locale="en-IN"
        localizationPolicy="development-preview"
        onComplete={vi.fn()}
        resumeBinding={learnerA}
        titleRef={createRef<HTMLHeadingElement>()}
      />,
    )

    await expectHeading('Choose your interface preferences')
  })

  it('keeps browser Back aligned with in-flow Back across the first-use steps', async () => {
    const user = userEvent.setup()

    render(
      <FirstUseOnboarding
        api={apiStub()}
        audioRecorder={recorderStub(false)}
        createClientId={() => 'command-id'}
        initialHome={incompleteHome()}
        locale="en-IN"
        localizationPolicy="development-preview"
        onComplete={vi.fn()}
        resumeBinding={learnerA}
        titleRef={createRef<HTMLHeadingElement>()}
      />,
    )

    await expectHeading('Set up English learning for your goals')
    await waitFor(() =>
      expect(window.location.hash).toBe('#home/first-use/welcome'),
    )
    await continueFlow(user)
    await expectHeading('Choose your interface preferences')
    await waitFor(() =>
      expect(window.location.hash).toBe('#home/first-use/preferences'),
    )

    window.history.back()
    await expectHeading('Set up English learning for your goals')
    expect(
      screen.getByRole('heading', {
        name: 'Set up English learning for your goals',
      }),
    ).toHaveFocus()
    await waitFor(() =>
      expect(window.location.hash).toBe('#home/first-use/welcome'),
    )

    await continueFlow(user)
    await expectHeading('Choose your interface preferences')
    await user.click(screen.getByRole('button', { name: 'Back' }))
    await expectHeading('Set up English learning for your goals')
    expect(
      screen.getByRole('heading', {
        name: 'Set up English learning for your goals',
      }),
    ).toHaveFocus()
    await waitFor(() =>
      expect(window.location.hash).toBe('#home/first-use/welcome'),
    )
  })
})

async function continueFlow(user: ReturnType<typeof userEvent.setup>) {
  await user.click(screen.getByRole('button', {
    name: /Get started|Confirm age and continue|Save preferences and continue|Save goal and continue|Save starting point and continue|Continue to privacy choices|Continue without voice|Save voice choices and continue|Continue to private practice|Review your setup|Open Home/,
  }))
}

async function expectHeading(name: string) {
  const heading = await screen.findByRole('heading', { name })
  await waitFor(() => expect(heading).toHaveFocus())
}

function recorderStub(contextual: boolean): AudioRecorder & { start: ReturnType<typeof vi.fn> } {
  return {
    isSupported: () => contextual,
    requiresContextualPermissionRationale: () => contextual,
    start: vi.fn(async (): Promise<AudioRecordingSession> => ({
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob(['audio'], { type: 'audio/webm' }),
        durationMs: 1000,
        mimeType: 'audio/webm',
      })),
    })),
  }
}

function apiStub(): LearnerJourneyApi {
  return {
    home: vi.fn(async () => incompleteHome()),
    listLevelVideos: vi.fn(async () => ({ enrollment_id: 'enrollment', videos: [] })),
    mission: vi.fn(),
    progress: vi.fn(),
    progressDashboard: vi.fn(),
    recordResponse: vi.fn(),
    startAttempt: vi.fn(),
    submitAttempt: vi.fn(),
    updateOnboarding: vi.fn(async () => completeHome()),
  } as unknown as LearnerJourneyApi
}

const consentListing: VoiceConsents = {
  consents: [
    { effective_at: null, purpose: 'voice_upload_and_transcription', state: 'not_granted' },
    { effective_at: null, purpose: 'conversation_provider_processing', state: 'not_granted' },
    { effective_at: null, purpose: 'transcript_history', state: 'not_granted' },
  ],
  notice: {
    history_optional: true,
    notice: 'Audio is used only for the purposes you select. You may refuse or withdraw without losing typed learning.',
    notice_revision: 'voice-notice-v1',
    notice_sha256: 'a'.repeat(64),
    policy_version: 'voice-policy-v1',
    purposes: [
      'voice_upload_and_transcription',
      'conversation_provider_processing',
      'transcript_history',
    ],
    requested_locale: 'en-IN',
    resolved_locale: 'en-IN',
    review_status: 'implementation_draft',
  },
}

function incompleteHome(): LearnerHome {
  return {
    due_review: null,
    enrollment: null,
    next_mission: null,
    onboarding: { adult_confirmed: false, completed: false, goal_key: null },
    streak: { as_of_date: '2026-09-15', count: 0, timezone: 'Asia/Kolkata' },
    upcoming_reviews: [],
  }
}

function completeHome(): LearnerHome {
  return {
    ...incompleteHome(),
    enrollment: { id: '01J00000000000000000000024', status: 'active' },
    onboarding: {
      adult_confirmed: true,
      completed: true,
      goal_key: 'workplace-confidence',
    },
  }
}
