import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { ConversationJourneyApi, VoiceConsents } from './ConversationJourneyApi'
import { ConversationPrivacyPanel } from './ConversationPrivacyPanel'
import { QuestionHelpJourney } from './QuestionHelpJourney'

afterEach(cleanup)

function listing(granted = false): VoiceConsents {
  return {
    consents: [
      { purpose: 'conversation_provider_processing', state: granted ? 'granted' : 'not_granted', effective_at: granted ? '2026-09-25T12:00:00Z' : null },
      { purpose: 'voice_upload_and_transcription', state: 'not_granted', effective_at: null },
      { purpose: 'transcript_history', state: 'not_granted', effective_at: null },
    ],
    notice: { history_optional: true, notice: 'Synthetic provider notice', notice_revision: 'test-notice', notice_sha256: 'a'.repeat(64), policy_version: 'test-policy', purposes: ['voice_upload_and_transcription', 'conversation_provider_processing', 'transcript_history'], requested_locale: 'en-IN', resolved_locale: 'en-IN', review_status: 'implementation_draft' },
  }
}
function fixture(initiallyGranted = false) {
  const api: ConversationJourneyApi = { startSession: vi.fn(), createTurn: vi.fn(), events: vi.fn(), voiceConsents: vi.fn(async () => listing(initiallyGranted)), grantVoiceConsent: vi.fn(), withdrawVoiceConsent: vi.fn(), uploadVoiceRecording: vi.fn() }
  let sequence = 0
  render(<QuestionHelpJourney api={api} createClientId={() => `command-${++sequence}`} locale="en-IN" localizationPolicy="development-preview" />)
  return api
}

describe('separate conversation provider permission', () => {
  it('starts lesson help through its bound callback and never falls back to general chat', async () => {
    const api: ConversationJourneyApi = { startSession: vi.fn(), createTurn: vi.fn(), events: vi.fn(), voiceConsents: vi.fn(async () => listing(true)) }
    const lessonStart = vi.fn().mockRejectedValue(new Error('synthetic unavailable'))
    render(<QuestionHelpJourney api={api} startLessonSession={lessonStart} createClientId={() => 'lesson-command'} locale="en-IN" localizationPolicy="development-preview" />)
    const user = userEvent.setup()
    await user.click(screen.getByRole('checkbox'))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Start question help' })).toBeEnabled())
    await user.click(screen.getByRole('button', { name: 'Start question help' }))
    expect(lessonStart).toHaveBeenCalledExactlyOnceWith('lesson-command', expect.any(AbortSignal))
    expect(api.startSession).not.toHaveBeenCalled()
    expect(await screen.findByRole('alert')).toBeInTheDocument()
  })

  it('never grants from recovery agreement and grants only the explicitly selected purpose', async () => {
    const api = fixture()
    const user = userEvent.setup()
    await screen.findByText('Synthetic provider notice')
    const start = screen.getByRole('button', { name: 'Start question help' })
    await user.click(screen.getByRole('checkbox'))
    expect(start).toBeDisabled()
    expect(api.startSession).not.toHaveBeenCalled()
    expect(api.grantVoiceConsent).not.toHaveBeenCalled()
    vi.mocked(api.voiceConsents!).mockResolvedValue(listing(true))
    await user.click(screen.getByRole('button', { name: 'Allow automated conversation processing' }))
    await waitFor(() => expect(start).toBeEnabled())
    expect(api.grantVoiceConsent).toHaveBeenCalledExactlyOnceWith({ purpose: 'conversation_provider_processing', requested_locale: 'en-IN' }, expect.any(String), expect.any(AbortSignal))
    expect(api.uploadVoiceRecording).not.toHaveBeenCalled()
    expect(screen.getByText('Start a new dialogue after 15 minutes.')).toBeInTheDocument()
  })

  it('reuses an existing provider grant without repeating notices or permission controls', async () => {
    const api = fixture(true)
    const user = userEvent.setup()
    await waitFor(() => expect(api.voiceConsents).toHaveBeenCalled())
    await user.click(screen.getByRole('checkbox'))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Start question help' })).toBeEnabled())
    expect(screen.queryByText('Synthetic provider notice')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Withdraw automated processing consent' })).not.toBeInTheDocument()
    expect(api.grantVoiceConsent).not.toHaveBeenCalled()
  })

  it('withdraws permission from Account Privacy and refreshes a mounted tutor before another start', async () => {
    const api = fixture(true)
    render(<ConversationPrivacyPanel api={api} locale="en-IN" localizationPolicy="development-preview" />)
    const user = userEvent.setup()
    await user.click(screen.getByRole('checkbox'))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Start question help' })).toBeEnabled())
    await user.click(screen.getByText('Privacy', { selector: 'summary' }))
    vi.mocked(api.voiceConsents!).mockResolvedValue(listing(false))
    await user.click(screen.getByRole('button', { name: 'Withdraw automated processing consent' }))
    await waitFor(() => expect(screen.getByRole('button', { name: 'Start question help' })).toBeDisabled())
    expect(api.withdrawVoiceConsent).toHaveBeenCalledExactlyOnceWith('conversation_provider_processing', { requested_locale: 'en-IN' }, expect.any(String))
    expect(api.startSession).not.toHaveBeenCalled()
  })

  it('retains the exact grant command after a lost response and fails closed meanwhile', async () => {
    const api = fixture()
    const user = userEvent.setup()
    await screen.findByText('Synthetic provider notice')
    vi.mocked(api.grantVoiceConsent!).mockRejectedValue(new Error('offline'))
    await user.click(screen.getByRole('checkbox'))
    await user.click(screen.getByRole('button', { name: 'Allow automated conversation processing' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Processing permission could not be confirmed')
    expect(screen.getByRole('button', { name: 'Start question help' })).toBeDisabled()
    await user.click(screen.getByRole('button', { name: 'Allow automated conversation processing' }))
    expect(api.grantVoiceConsent).toHaveBeenCalledTimes(2)
    expect(vi.mocked(api.grantVoiceConsent!).mock.calls[0]?.slice(0, 2)).toEqual(vi.mocked(api.grantVoiceConsent!).mock.calls[1]?.slice(0, 2))
  })

  it('does not assume permission when the consent API is unavailable', async () => {
    const user = userEvent.setup()
    const api: ConversationJourneyApi = { startSession: vi.fn(), createTurn: vi.fn(), events: vi.fn() }
    render(<QuestionHelpJourney api={api} createClientId={() => 'command'} locale="en-IN" localizationPolicy="development-preview" />)
    expect(await screen.findByRole('alert')).toHaveTextContent('Processing permission could not be confirmed')
    await user.click(screen.getByRole('checkbox'))
    expect(screen.getByRole('button', { name: 'Start question help' })).toBeDisabled()
    expect(api.startSession).not.toHaveBeenCalled()
  })
})
