import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { ConversationJourneyApi, VoiceConsents } from './ConversationJourneyApi'
import { VoicePracticePanel } from './VoicePracticePanel'

afterEach(cleanup)

describe('secure voice practice consent boundary', () => {
  it('requires both distinct purposes before exposing recording controls', async () => {
    const initial = listing('not_granted', 'not_granted')
    const uploadGranted = listing('granted', 'not_granted')
    const bothGranted = listing('granted', 'granted')
    let currentListing = initial
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(async (input) => {
        currentListing =
          input.purpose === 'voice_upload_and_transcription'
            ? uploadGranted
            : bothGranted

        return {
          effective_at: '2026-07-31T12:00:00Z',
          id: '01J00000000000000000000010',
          notice_revision: 'voice-processing-notice-2026-07-31',
          notice_sha256: 'a'.repeat(64),
          policy_version: 'recording-policy-v1',
          purpose: input.purpose,
          replayed: false,
          requested_locale: input.requested_locale,
          resolved_locale: 'en-IN' as const,
          state: 'granted' as const,
        }
      }),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(),
      voiceConsents: vi.fn(async () => currentListing),
      withdrawVoiceConsent: vi.fn(async (purpose, input) => {
        currentListing = uploadGranted

        return {
          effective_at: '2026-07-31T12:00:00Z',
          id: '01J00000000000000000000011',
          notice_revision: 'voice-processing-notice-2026-07-31',
          notice_sha256: 'a'.repeat(64),
          policy_version: 'recording-policy-v1',
          purpose,
          replayed: false,
          requested_locale: input.requested_locale,
          resolved_locale: 'en-IN' as const,
          state: 'withdrawn' as const,
        }
      }),
    }
    const user = userEvent.setup()

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => crypto.randomUUID()}
        locale="en-IN"
        onTranscriptSelected={vi.fn()}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    expect(
      (await screen.findByText(initial.notice.notice)).closest('aside'),
    ).toHaveAttribute('lang', 'en-IN')

    await user.click(
      screen.getByRole('button', {
        name: 'conversation.voice.consent_both',
      }),
    )
    expect(
      await screen.findByRole('heading', {
        name: 'learner.recording.region',
      }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('button', {
        name: 'conversation.voice.upload',
      }),
    ).not.toBeInTheDocument()
    expect(api.grantVoiceConsent).toHaveBeenNthCalledWith(
      1,
      {
        purpose: 'voice_upload_and_transcription',
        requested_locale: 'en-IN',
      },
      expect.any(String),
    )
    expect(api.grantVoiceConsent).toHaveBeenNthCalledWith(
      2,
      {
        purpose: 'conversation_provider_processing',
        requested_locale: 'en-IN',
      },
      expect.any(String),
    )

    await user.click(
      screen.getByText('conversation.voice.manage_consent'),
    )
    await user.click(
      screen.getByRole('button', {
        name: 'conversation.voice.withdraw_provider',
      }),
    )
    expect(api.withdrawVoiceConsent).toHaveBeenCalledWith(
      'conversation_provider_processing',
      { requested_locale: 'en-IN' },
      expect.any(String),
    )
    expect(
      await screen.findByRole('button', {
        name: 'conversation.voice.consent_provider',
      }),
    ).toBeVisible()
  })

  it('opens Type instead when the learner continues without recording', async () => {
    const bothGranted = listing('granted', 'granted')
    const onPreferTyping = vi.fn()
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(),
      voiceConsents: vi.fn(async () => bothGranted),
      withdrawVoiceConsent: vi.fn(),
    }
    const user = userEvent.setup()

    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => crypto.randomUUID()}
        locale="en-IN"
        onPreferTyping={onPreferTyping}
        onTranscriptSelected={vi.fn()}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    await user.click(
      await screen.findByRole('button', {
        name: 'learner.recording.continue_without',
      }),
    )
    expect(onPreferTyping).toHaveBeenCalledOnce()
  })

  it('treats a closed voice gate as an honest status, not a failure alert', async () => {
    const api: ConversationJourneyApi = {
      createTurn: vi.fn(),
      deleteVoiceRecording: vi.fn(),
      events: vi.fn(),
      grantVoiceConsent: vi.fn(),
      reviewVoiceTranscript: vi.fn(),
      startSession: vi.fn(),
      uploadVoiceRecording: vi.fn(),
      voiceConsents: vi.fn(async () => {
        throw new Error('voice_gate_closed')
      }),
      withdrawVoiceConsent: vi.fn(),
    }
    render(
      <VoicePracticePanel
        api={api}
        createClientId={() => crypto.randomUUID()}
        locale="en-IN"
        onTranscriptSelected={vi.fn()}
        sessionId="01J00000000000000000000001"
        text={(key) => key}
      />,
    )

    expect(
      await screen.findByRole('status'),
    ).toHaveTextContent('conversation.voice.error')
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'conversation.voice.setup' }),
    ).not.toBeInTheDocument()
  })
})

function listing(
  upload: 'granted' | 'not_granted',
  provider: 'granted' | 'not_granted',
): VoiceConsents {
  return {
    consents: [
      {
        effective_at: upload === 'granted'
          ? '2026-07-31T12:00:00Z'
          : null,
        purpose: 'voice_upload_and_transcription',
        state: upload,
      },
      {
        effective_at: provider === 'granted'
          ? '2026-07-31T12:00:00Z'
          : null,
        purpose: 'conversation_provider_processing',
        state: provider,
      },
      {
        effective_at: null,
        purpose: 'transcript_history',
        state: 'not_granted',
      },
    ],
    notice: {
      history_optional: true,
      notice:
        'Implementation draft: audio is uploaded privately and deleted after transcription.',
      notice_revision: 'voice-processing-notice-2026-07-31',
      notice_sha256: 'a'.repeat(64),
      policy_version: 'recording-policy-v1',
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
}
