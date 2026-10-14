import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { StrictMode } from 'react'
import type { ConversationInteraction } from '@global-english/contracts'
import type { ConversationJourneyApi, FriendSession, FriendTurn, VoiceConsents } from './ConversationJourneyApi'
import { ConversationJourneyUnavailableError } from './ConversationJourneyApi'
import { InteractiveDialogue } from './InteractiveDialogue'
import { QuestionHelpJourney } from './QuestionHelpJourney'
import { RoleplayJourney } from './RoleplayJourney'
import { playConversationSpeechResult } from './speakSessionSpeech'
import { stopSpeechPlayback } from '../shared/stopSpeechPlayback'

vi.mock('./speakSessionSpeech', () => ({
  playConversationSpeechResult: vi.fn(async () => false),
  speakSessionSpeech: vi.fn(async () => false),
}))
vi.mock('../shared/stopSpeechPlayback', () => ({ stopSpeechPlayback: vi.fn() }))
vi.mock('./VoicePracticePanel', () => ({
  VoicePracticePanel: ({ onTranscriptSelected, onProcessingConsentWithdrawn }: {
    onTranscriptSelected: (text: string, metadata: { recordingId: string; locale: 'en-IN' | 'unknown'; source: 'original_speech' | 'translated_speech' }) => void
    onProcessingConsentWithdrawn?: () => void
  }) => <>
    <button onClick={() => onTranscriptSelected('I have an appointment.', { recordingId: 'recorded-original', locale: 'en-IN', source: 'original_speech' })}>Submit original recording</button>
    <button onClick={() => onTranscriptSelected('Translated appointment.', { recordingId: 'recorded-translation', locale: 'en-IN', source: 'translated_speech' })}>Submit translated recording</button>
    <button onClick={() => onTranscriptSelected('Unverified words.', { recordingId: 'recorded-unknown', locale: 'unknown', source: 'original_speech' })}>Submit unknown-language recording</button>
    <button onClick={onProcessingConsentWithdrawn}>Complete processing withdrawal</button>
  </>,
}))

let command = 0
beforeEach(() => { command = 0; vi.clearAllMocks() })
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.useRealTimers() })
const createClientId = () => `command-${++command}`
const speech = { playback_available: false, rendering_mode: 'deterministic-fake-text-only' as const }

function interaction(flow: ConversationInteraction['flow'] = 'roleplay_dialogue', state: ConversationInteraction['state'] = flow === 'roleplay_dialogue' ? 'awaiting_reply' : 'awaiting_question', revision = 0): ConversationInteraction {
  return { flow, state, revision, opportunity: 1, ai_role: 'Receptionist', learner_role: 'Visitor', opening_reply: flow === 'roleplay_dialogue' ? { text: 'Good morning. How can I help you?', locale: 'en-IN' } : null, recovery_expires_at: new Date(Date.now() + 15 * 60_000).toISOString() }
}
function session(flow: ConversationInteraction['flow'] = 'roleplay_dialogue'): FriendSession {
  return {
    id: 'session', status: 'active', mode: flow === 'roleplay_dialogue' ? 'roleplay' : 'friend', coaching_mode: 'gentle', stage: flow === 'roleplay_dialogue' ? 'perform' : null, ui_locale: 'kn-IN', replayed: false,
    persona: { id: 'persona', key: 'patient-friend-prototype', version_id: 'persona-version', version: 1, display_name: 'Swathi', disclosure: 'AI partner', definition_hash: 'a'.repeat(64), review_status: 'synthetic_unreviewed' },
    scenario: { id: 'scenario', key: 'everyday-friend-prototype', version_id: 'scenario-version', version: 1, topic_code: 'everyday-conversation', definition_hash: 'b'.repeat(64), review_status: 'synthetic_unreviewed' },
    interaction: interaction(flow), latest_turn: null,
  }
}
function turn(state: ConversationInteraction['state'] = 'awaiting_reply', revision = 1, flow: ConversationInteraction['flow'] = 'roleplay_dialogue'): FriendTurn {
  return { id: `turn-${revision}`, conversation_session_id: 'session', turn_number: revision, status: 'completed', transcript: 'I have an appointment.', understood_intent: 'appointment', assistant_reply: 'What time is your appointment?', reply: { text: 'What time is your appointment?', locale: 'en-IN' }, priority_correction: null, safety_state: 'ok', speech, usage: { provider_attempts: 1, total_latency_ms: 1, estimated_cost_microunits: 1 }, events_url: '/events', replayed: false, interaction: interaction(flow, state, revision), assessment: 'accepted', original_turn_id: null }
}
function apiFor(initial = session()): ConversationJourneyApi {
  return { voiceConsents: vi.fn(async (): Promise<VoiceConsents> => ({ consents: [{ purpose: 'conversation_provider_processing', state: 'granted', effective_at: '2026-09-25T12:00:00Z' }, { purpose: 'voice_upload_and_transcription', state: 'not_granted', effective_at: null }, { purpose: 'transcript_history', state: 'not_granted', effective_at: null }], notice: { history_optional: true, notice: 'Synthetic provider notice', notice_revision: 'test-notice', notice_sha256: 'a'.repeat(64), policy_version: 'test-policy', purposes: ['voice_upload_and_transcription', 'conversation_provider_processing', 'transcript_history'], requested_locale: 'en-IN', resolved_locale: 'en-IN', review_status: 'implementation_draft' } })), startSession: vi.fn(async () => initial), createTurn: vi.fn(async () => turn()), events: vi.fn(async () => []), synthesizeSpeech: vi.fn(async () => speech), readSession: vi.fn(async () => initial), exitSession: vi.fn(async (): Promise<FriendSession> => ({ ...initial, stage: 'reflect', interaction: { ...initial.interaction!, state: 'closed' } })) }
}
function renderDialogue(api: ConversationJourneyApi, initial = session()) {
  const onExited = vi.fn()
  render(<InteractiveDialogue api={api} createClientId={createClientId} initialSession={initial} locale="en-IN" localizationPolicy="development-preview" onExited={onExited} />)
  return onExited
}
async function typeAndSend(value: string, question = false) {
  const user = userEvent.setup()
  if (screen.queryByRole('textbox') === null) await user.click(screen.getByRole('button', { name: 'Type instead' }))
  const field = screen.getByRole('textbox', { name: question ? 'Your question or response' : 'Type one English practice turn' })
  await user.clear(field)
  await user.type(field, value)
  await user.click(screen.getByRole('button', { name: 'Send turn' }))
}

describe('interactive conversation', () => {
  it('reads the exact free-talk reply below its bubble without audio', async () => {
    const initial = session('free_speech')
    initial.ui_locale = 'kn-IN'
    initial.interaction!.state = 'awaiting_reply'
    const first = { ...turn('awaiting_reply', 1, 'free_speech'), reply: { text: 'What do you enjoy about gardening?', locale: 'en-IN' as const } }
    initial.latest_turn = first
    initial.interaction!.revision = 1
    const api = apiFor(initial)
    vi.mocked(api.createTurn).mockResolvedValue({ ...turn('awaiting_reply', 2, 'free_speech'), explanation_target: first.id, explanation_source: first.reply,
      reply: { text: 'ತೋಟಗಾರಿಕೆಯಲ್ಲಿ ನಿಮಗೆ ಏನು ಇಷ್ಟ?', locale: 'kn-IN' }, transcript: 'Read in my language', assistant_reply: null })
    render(<InteractiveDialogue api={api} createClientId={createClientId} initialSession={initial} locale="kn-IN" localizationPolicy="development-preview" onExited={vi.fn()} />)
    const user = userEvent.setup()
    const button = screen.getByRole('button', { name: 'ನಾನು ಆಯ್ಕೆ ಮಾಡಿದ ಭಾಷೆಯಲ್ಲಿ ಓದಿ' })
    expect(screen.queryByText('ತೋಟಗಾರಿಕೆಯಲ್ಲಿ ನಿಮಗೆ ಏನು ಇಷ್ಟ?')).not.toBeInTheDocument()
    await user.click(button)
    const translated = await screen.findByText('ತೋಟಗಾರಿಕೆಯಲ್ಲಿ ನಿಮಗೆ ಏನು ಇಷ್ಟ?')
    expect(translated).toHaveAttribute('lang', 'kn-IN')
    expect(translated.closest('.conversation-journey__bubble')).toContainElement(screen.getByText(first.reply.text))
    expect(screen.queryByText('Read in my language')).not.toBeInTheDocument()
    expect(api.createTurn).toHaveBeenCalledWith('session', expect.objectContaining({ action: 'accept_explanation', explanation_target: first.id, expected_revision: 1 }), expect.any(String), expect.any(AbortSignal))
    expect(api.synthesizeSpeech).not.toHaveBeenCalled()
    expect(playConversationSpeechResult).not.toHaveBeenCalled()
    expect(screen.queryByRole('button', { name: 'ನಾನು ಆಯ್ಕೆ ಮಾಡಿದ ಭಾಷೆಯಲ್ಲಿ ಓದಿ' })).not.toBeInTheDocument()
  })

  it('recovers a reading aid with its English source and clears it on expiry', () => {
    vi.useFakeTimers()
    const initial = session('free_speech')
    initial.ui_locale = 'kn-IN'
    initial.interaction!.recovery_expires_at = new Date(Date.now() + 1000).toISOString()
    initial.latest_turn = { ...turn('awaiting_reply', 2, 'free_speech'), explanation_target: 'source-turn', explanation_source: { text: 'English source.', locale: 'en-IN' }, reply: { text: 'ಕನ್ನಡ ವಿವರಣೆ.', locale: 'kn-IN' } }
    render(<InteractiveDialogue api={apiFor(initial)} createClientId={createClientId} initialSession={initial} locale="kn-IN" localizationPolicy="development-preview" onExited={vi.fn()} />)
    expect(screen.getByText('English source.')).toBeVisible()
    expect(screen.getByText('ಕನ್ನಡ ವಿವರಣೆ.')).toBeVisible()
    act(() => { vi.advanceTimersByTime(1001) })
    expect(screen.queryByText('English source.')).not.toBeInTheDocument()
    expect(screen.queryByText('ಕನ್ನಡ ವಿವರಣೆ.')).not.toBeInTheDocument()
  })

  it('keeps free speech conversational after advisory correction and clears the submitted draft', async () => {
    const initial = session('free_speech')
    initial.interaction!.state = 'awaiting_reply'
    const api = apiFor(initial)
    vi.mocked(api.createTurn).mockResolvedValue({
      ...turn('awaiting_reply', 1, 'free_speech'), assessment: 'unassessed',
      reply: { text: 'What do you enjoy about gardening?', locale: 'en-IN' },
      assistant_reply: 'What do you enjoy about gardening?',
      priority_correction: { needed: true, error_category: 'grammar.agreement', correction_en: 'I grow tomatoes.', explanation_l1: 'Use grow with I.', explanation_locale: 'en-IN', retry_recommended: false },
    })
    renderDialogue(api, initial)
    await typeAndSend('I grows tomatoes.')
    expect(await screen.findByText('What do you enjoy about gardening?')).toBeVisible()
    expect(screen.getByText('I grow tomatoes.')).toBeVisible()
    await waitFor(() => expect(screen.getByRole('textbox')).toHaveValue(''))
    expect(api.createTurn).toHaveBeenCalledWith('session', expect.objectContaining({ action: 'reply' }), expect.any(String), expect.any(AbortSignal))
  })

  it('expires at the deadline, clears content and pending commands, and ignores a late response', async () => {
    vi.useFakeTimers()
    const initial = session()
    initial.interaction!.recovery_expires_at = new Date(Date.now() + 1_000).toISOString()
    initial.latest_turn = { ...turn(), transcript: 'Earlier private turn.' }
    const api = apiFor(initial)
    let finish: ((value: FriendTurn) => void) | undefined
    let signal: AbortSignal | undefined
    vi.mocked(api.createTurn).mockImplementation((_id, _input, _key, requestSignal) => {
      signal = requestSignal
      return new Promise((resolve) => { finish = resolve })
    })
    renderDialogue(api, initial)
    expect(screen.getByText('Earlier private turn.')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Type instead' }))
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Unsent private draft.' } })
    fireEvent.click(screen.getByRole('button', { name: 'Send turn' }))
    await act(async () => { await vi.advanceTimersByTimeAsync(1_000) })
    expect(signal?.aborted).toBe(true)
    expect(stopSpeechPlayback).toHaveBeenCalled()
    expect(screen.getByRole('heading', { name: /no longer available/ })).toBeInTheDocument()
    expect(screen.queryByText('Earlier private turn.')).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Listen again' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Retry sending' })).not.toBeInTheDocument()
    await act(async () => { finish?.({ ...turn(), transcript: 'Late private response.' }); await Promise.resolve() })
    expect(screen.queryByText('Late private response.')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Send turn' })).not.toBeInTheDocument()
    expect(api.createTurn).toHaveBeenCalledOnce()
    expect(screen.getByRole('button', { name: 'Exit and delete temporary conversation' })).toBeEnabled()
  })

  it('rechecks the deadline on foreground resume without relying on suspended timers', () => {
    vi.useFakeTimers()
    const initial = { ...session(), latest_turn: { ...turn(), transcript: 'Private before background.' } }
    renderDialogue(apiFor(initial), initial)
    vi.setSystemTime(Date.now() + 16 * 60_000)
    fireEvent(document, new Event('visibilitychange'))
    expect(screen.getByRole('heading', { name: /no longer available/ })).toBeInTheDocument()
    expect(screen.queryByText('Private before background.')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Listen again' })).not.toBeInTheDocument()
  })

  it('ignores a recovered body arriving after local expiry', async () => {
    vi.useFakeTimers()
    const initial = session()
    initial.interaction!.recovery_expires_at = new Date(Date.now() + 1_000).toISOString()
    initial.latest_turn = turn()
    const api = apiFor(initial)
    let finish: ((value: FriendSession) => void) | undefined
    let signal: AbortSignal | undefined
    vi.mocked(api.createTurn).mockRejectedValue(new Error('offline'))
    vi.mocked(api.readSession!).mockImplementation((_id, requestSignal) => {
      signal = requestSignal
      return new Promise((resolve) => { finish = resolve })
    })
    renderDialogue(api, initial)
    fireEvent.click(screen.getByRole('button', { name: 'Type instead' }))
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Private draft.' } })
    await act(async () => { fireEvent.click(screen.getByRole('button', { name: 'Send turn' })); await Promise.resolve() })
    fireEvent.click(screen.getByRole('button', { name: 'Recover this conversation' }))
    await act(async () => { await vi.advanceTimersByTimeAsync(1_000) })
    expect(signal?.aborted).toBe(true)
    await act(async () => { finish?.({ ...session(), latest_turn: { ...turn(), transcript: 'Late recovered body.' } }); await Promise.resolve() })
    expect(screen.getByRole('heading', { name: /no longer available/ })).toBeInTheDocument()
    expect(screen.queryByText('Late recovered body.')).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })

  it('clears local session bodies and replay controls after confirmed processing withdrawal', async () => {
    const initial = { ...session(), latest_turn: { ...turn(), transcript: 'Private before withdrawal.' } }
    const api = apiFor(initial)
    renderDialogue(api, initial)
    await userEvent.setup().click(screen.getByRole('button', { name: 'Complete processing withdrawal' }))
    expect(screen.getByRole('heading', { name: /Conversation ended/ })).toBeInTheDocument()
    expect(screen.queryByText('Private before withdrawal.')).not.toBeInTheDocument()
    expect(screen.queryByText('Good morning. How can I help you?')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Listen again' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Send turn' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Done' })).toBeEnabled()
  })

  it('never speaks an opener whose initial recovery deadline has already passed', async () => {
    vi.useFakeTimers()
    const initial = session()
    initial.interaction!.recovery_expires_at = new Date(Date.now() - 1).toISOString()
    const api = apiFor(initial)
    renderDialogue(api, initial)
    await act(async () => { await vi.advanceTimersByTimeAsync(0) })
    expect(api.synthesizeSpeech).not.toHaveBeenCalled()
    expect(playConversationSpeechResult).not.toHaveBeenCalled()
    expect(screen.getByRole('heading', { name: /no longer available/ })).toBeInTheDocument()
  })

  it('never resends an admitted turn with unknown outcome and only unlocks on committed recovery', async () => {
    const api = apiFor()
    vi.mocked(api.createTurn).mockRejectedValueOnce(new ConversationJourneyUnavailableError(409, 'conversation.turn_outcome_unknown'))
    renderDialogue(api)
    await typeAndSend('I have an appointment.')
    expect(await screen.findByRole('alert')).toHaveTextContent('The result of this turn is unknown')
    expect(screen.queryByRole('button', { name: 'Retry sending' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Send turn' })).not.toBeInTheDocument()
    const user = userEvent.setup()
    await user.click(screen.getByRole('button', { name: 'Recover this conversation' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('The result of this turn is unknown')
    expect(api.createTurn).toHaveBeenCalledOnce()
    expect(screen.queryByRole('button', { name: 'Send turn' })).not.toBeInTheDocument()
    const committed = turn()
    vi.mocked(api.readSession!).mockResolvedValue({ ...session(), latest_turn: committed, interaction: committed.interaction })
    await user.click(screen.getByRole('button', { name: 'Recover this conversation' }))
    await screen.findByRole('button', { name: 'Send turn' })
    expect(api.createTurn).toHaveBeenCalledOnce()
    expect(screen.getByRole('button', { name: 'Exit and delete temporary conversation' })).toBeEnabled()
  })
  it('requires new explicit notice acceptance and starts only the AI role', async () => {
    const api = apiFor()
    const user = userEvent.setup()
    render(<RoleplayJourney api={api} createClientId={createClientId} locale="en-IN" localizationPolicy="development-preview" />)
    const start = screen.getByRole('button', { name: 'Start this situation' })
    expect(start).toBeDisabled()
    expect(screen.getByRole('checkbox')).not.toBeChecked()
    expect(api.startSession).not.toHaveBeenCalled()
    await user.click(screen.getByRole('checkbox'))
    await user.click(start)
    expect(await screen.findByRole('heading', { name: 'Your turn' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Observe' })).not.toBeInTheDocument()
    expect(screen.queryByText(/You: Good morning/)).not.toBeInTheDocument()
    expect(api.startSession).toHaveBeenCalledWith(expect.objectContaining({ interaction_flow: 'roleplay_dialogue', recovery_notice_version: 'conversation-recovery-2026-09-25', recovery_notice_accepted: true }), expect.any(String), expect.any(AbortSignal))
    await waitFor(() => expect(api.synthesizeSpeech).toHaveBeenCalledWith('session', { purpose: 'model_en', text: 'Good morning. How can I help you?' }, expect.any(String), expect.any(AbortSignal)))
  })

  it('holds the same opportunity for correction and accepts a valid alternative retry', async () => {
    const api = apiFor()
    const wrong = { ...turn('retry_required'), transcript: 'I has appointment.', assessment: 'retry_required' as const, original_turn_id: 'turn-1', priority_correction: { needed: true, correction_en: 'I have an appointment.', error_category: 'grammar.agreement', explanation_locale: 'kn-IN' as const, explanation_l1: 'I ಜೊತೆಗೆ have ಬಳಸಿ.', encouragement_l1: 'ಚೆನ್ನಾಗಿ ಪ್ರಯತ್ನಿಸಿದ್ದೀರಿ.', retry_recommended: true }, reply: { text: 'Do not advance to this line.', locale: 'en-IN' as const } }
    vi.mocked(api.createTurn).mockResolvedValueOnce(wrong).mockResolvedValueOnce({ ...turn('awaiting_reply', 2), transcript: 'I am here for my appointment.' })
    renderDialogue(api)
    await typeAndSend('I has appointment.')
    expect(await screen.findByText('I ಜೊತೆಗೆ have ಬಳಸಿ.')).toHaveAttribute('lang', 'kn-IN')
    expect(screen.getByText('ಚೆನ್ನಾಗಿ ಪ್ರಯತ್ನಿಸಿದ್ದೀರಿ.')).toBeInTheDocument()
    expect(screen.queryByText('Do not advance to this line.')).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Try the same response again' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Continue' })).not.toBeInTheDocument()
    await waitFor(() => expect(playConversationSpeechResult).toHaveBeenCalledWith(expect.objectContaining({ purpose: 'model_en', text: 'I have an appointment.' })))
    await typeAndSend('I am here for my appointment.')
    expect(await screen.findByText('What time is your appointment?')).toBeInTheDocument()
    expect(api.createTurn).toHaveBeenLastCalledWith('session', expect.objectContaining({ action: 'retry', expected_revision: 1, prototype_utterance: 'I am here for my appointment.' }), expect.any(String), expect.any(AbortSignal))
  })

  it('reuses a lost-response command and gives edited submissions a new identity', async () => {
    const api = apiFor()
    vi.mocked(api.createTurn).mockRejectedValue(new Error('offline'))
    renderDialogue(api)
    await typeAndSend('Hello.')
    await screen.findByRole('alert')
    const first = vi.mocked(api.createTurn).mock.calls[0]!
    const user = userEvent.setup()
    await user.click(screen.getByRole('button', { name: 'Retry sending' }))
    await waitFor(() => expect(api.createTurn).toHaveBeenCalledTimes(2))
    expect(vi.mocked(api.createTurn).mock.calls[1]![2]).toBe(first[2])
    await typeAndSend('Good morning.')
    await waitFor(() => expect(api.createTurn).toHaveBeenCalledTimes(3))
    expect(vi.mocked(api.createTurn).mock.calls[2]![2]).not.toBe(first[2])
  })

  it('plays a committed answer recovered after interruption without dispatching the model again', async () => {
    const initial = session('question_help')
    const api = apiFor(initial)
    vi.mocked(api.createTurn).mockRejectedValue(new Error('connection_lost'))
    const committed = turn('awaiting_question', 1, 'question_help')
    vi.mocked(api.readSession!).mockResolvedValue({ ...initial, latest_turn: committed, interaction: committed.interaction })
    renderDialogue(api, initial)
    await typeAndSend('How can I ask for an appointment?', true)
    await userEvent.setup().click(await screen.findByRole('button', { name: 'Recover this conversation' }))
    await waitFor(() => expect(playConversationSpeechResult).toHaveBeenCalledWith(expect.objectContaining({ text: committed.reply!.text })))
    expect(api.createTurn).toHaveBeenCalledOnce()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('retains typed input after a failed model turn without speaking a next line', async () => {
    const api = apiFor()
    vi.mocked(api.createTurn).mockResolvedValue({ ...turn(), status: 'failed', safety_state: 'fallback', assessment: 'unassessed', reply: { text: 'Do not speak this failed response.', locale: 'en-IN' } })
    renderDialogue(api)
    await typeAndSend('Please help me.')
    await screen.findByRole('alert')
    expect(screen.getByRole('textbox')).toHaveValue('Please help me.')
    expect(screen.queryByText('Do not speak this failed response.')).not.toBeInTheDocument()
    expect(playConversationSpeechResult).not.toHaveBeenCalledWith(expect.objectContaining({ text: 'Do not speak this failed response.' }))
    expect(screen.getByRole('button', { name: 'Retry sending' })).toBeEnabled()
    expect(screen.queryByRole('button', { name: 'Recover this conversation' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Listen again' })).not.toBeInTheDocument()
    const first = vi.mocked(api.createTurn).mock.calls[0]!
    await userEvent.setup().click(screen.getByRole('button', { name: 'Send turn' }))
    await waitFor(() => expect(api.createTurn).toHaveBeenCalledTimes(2))
    expect(vi.mocked(api.createTurn).mock.calls[1]![1].expected_revision).toBe(1)
    expect(vi.mocked(api.createTurn).mock.calls[1]![2]).not.toBe(first[2])
  })

  it('aborts a pending submission and leaves text plus recovery available', async () => {
    const api = apiFor()
    let signal: AbortSignal | undefined
    vi.mocked(api.createTurn).mockImplementation((_id, _input, _command, requestSignal) => {
      signal = requestSignal
      return new Promise((_resolve, reject) => requestSignal?.addEventListener('abort', () => reject(new DOMException('Cancelled', 'AbortError'))))
    })
    renderDialogue(api)
    await typeAndSend('Keep this response.')
    await userEvent.setup().click(screen.getByRole('button', { name: 'Stop' }))
    expect(signal?.aborted).toBe(true)
    expect(await screen.findByRole('alert')).toHaveTextContent('Stopped.')
    await waitFor(() => expect(screen.getByRole('textbox')).toBeEnabled())
    expect(screen.getByRole('textbox')).toHaveValue('Keep this response.')
    expect(screen.getByRole('button', { name: 'Recover this conversation' })).toBeEnabled()
  })

  it('does not treat StrictMode rehearsal as exit but deletes on navigation', async () => {
    const api = apiFor()
    const { unmount } = render(<StrictMode><InteractiveDialogue api={api} createClientId={createClientId} initialSession={session()} locale="en-IN" localizationPolicy="development-preview" onExited={vi.fn()} /></StrictMode>)
    await waitFor(() => expect(api.synthesizeSpeech).toHaveBeenCalledTimes(1))
    expect(api.exitSession).not.toHaveBeenCalled()
    unmount()
    await waitFor(() => expect(api.exitSession).toHaveBeenCalledOnce())
  })

  it('keeps the dialogue available when exit deletion cannot be confirmed', async () => {
    const api = apiFor()
    vi.mocked(api.exitSession!).mockRejectedValue(new Error('offline'))
    const onExited = renderDialogue(api)
    await userEvent.setup().click(screen.getByRole('button', { name: 'Exit and delete temporary conversation' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('deletion could not be confirmed')
    expect(screen.getByText('Good morning. How can I help you?')).toBeInTheDocument()
    expect(onExited).not.toHaveBeenCalled()
  })

  it('does not use translated speech as learner English and preserves verified recording linkage', async () => {
    const api = apiFor()
    renderDialogue(api)
    const user = userEvent.setup()
    await user.click(screen.getByRole('button', { name: 'Submit translated recording' }))
    expect(api.createTurn).not.toHaveBeenCalled()
    expect(await screen.findByText(/We could not verify the original English words/)).toBeInTheDocument()
    expect(screen.getByRole('textbox')).toHaveValue('Translated appointment.')
    await user.click(screen.getByRole('button', { name: 'Submit original recording' }))
    await waitFor(() => expect(api.createTurn).toHaveBeenCalledWith('session', expect.objectContaining({ transcript_source: 'original_speech', transcript_locale: 'en-IN', voice_recording_id: 'recorded-original' }), expect.any(String), expect.any(AbortSignal)))
  })

  it('asks for original English repair when the recording language is unknown', async () => {
    const api = apiFor()
    renderDialogue(api)
    await userEvent.setup().click(screen.getByRole('button', { name: 'Submit unknown-language recording' }))
    expect(await screen.findByText(/We could not verify the original English words/)).toBeInTheDocument()
    expect(screen.getByRole('textbox')).toHaveValue('Unverified words.')
    expect(api.createTurn).not.toHaveBeenCalled()
  })

  it.each(['expired', 'closed'] as const)('clears private bodies on recovered %s and offers no further learner submission', async (state) => {
    const initial = { ...session(), latest_turn: { ...turn(), transcript: 'Private before recovery.' } }
    const api = apiFor(initial)
    vi.mocked(api.createTurn).mockRejectedValue(new Error('offline'))
    vi.mocked(api.readSession!).mockResolvedValue({ ...initial, latest_turn: null, interaction: { ...initial.interaction!, state, opening_reply: null, recovery_expires_at: null } })
    renderDialogue(api, initial)
    await typeAndSend('Hello.')
    await screen.findByRole('alert')
    await userEvent.setup().click(screen.getByRole('button', { name: 'Recover this conversation' }))
    expect(await screen.findByRole('heading', { name: state === 'expired' ? /no longer available/ : /Conversation ended/ })).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByText('Private before recovery.')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Retry sending' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Listen again' })).not.toBeInTheDocument()
  })

  it('deletes private dialogue on exit, clears messages and renders unassessed reflection', async () => {
    const initial = session()
    const api = apiFor(initial)
    vi.mocked(api.exitSession!).mockResolvedValue({ ...initial, stage: 'reflect', interaction: { ...initial.interaction!, state: 'closed' }, objectives: [{ id: 'objective', key: 'time', title: 'State appointment time', completion_criteria_code: 'time' }], feedback: [{ id: 'evidence', scenario_objective_id: 'objective', evidence_digest: 'c'.repeat(64), completion_state: 'unassessed', source_turn_id: null, advisory: true }] })
    const onExited = renderDialogue(api, initial)
    await typeAndSend('Private appointment detail.')
    await screen.findByText('What time is your appointment?')
    const user = userEvent.setup()
    await user.click(screen.getByRole('button', { name: 'Exit and delete temporary conversation' }))
    expect(await screen.findByText('Not assessed')).toBeInTheDocument()
    expect(screen.queryByText('What time is your appointment?')).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: /no longer available/ })).not.toBeInTheDocument()
    expect(onExited).not.toHaveBeenCalled()
    await user.click(screen.getByRole('button', { name: 'Done' }))
    expect(onExited).toHaveBeenCalledOnce()
  })

  it('preserves coaching choice and saved-note controls in consented free speech', async () => {
    const initial = session('free_speech')
    initial.interaction!.state = 'awaiting_reply'
    const api = apiFor(initial)
    vi.mocked(api.startSession).mockRejectedValueOnce(new Error('offline'))
    api.listAiMemory = vi.fn(async () => ({ facts: [] }))
    api.createAiMemoryFact = vi.fn(async () => { throw new Error('not invoked') })
    api.updateAiMemoryFact = vi.fn(async () => { throw new Error('not invoked') })
    api.deleteAiMemoryFact = vi.fn(async () => { throw new Error('not invoked') })
    render(<QuestionHelpJourney flow="free_speech" api={api} createClientId={createClientId} locale="en-IN" localizationPolicy="development-preview" />)
    const user = userEvent.setup()
    await user.selectOptions(screen.getByRole('combobox'), 'fluency')
    expect(api.startSession).not.toHaveBeenCalled()
    await user.click(screen.getByRole('checkbox'))
    await user.click(screen.getByRole('button', { name: 'Talk with Swathi' }))
    await waitFor(() => expect(api.startSession).toHaveBeenCalledWith(expect.objectContaining({ interaction_flow: 'free_speech', coaching_mode: 'fluency' }), expect.any(String), expect.any(AbortSignal)))
    await screen.findByRole('alert')
    await user.selectOptions(screen.getByRole('combobox'), 'tutor')
    await user.click(screen.getByRole('button', { name: 'Talk with Swathi' }))
    await waitFor(() => expect(api.startSession).toHaveBeenCalledTimes(2))
    expect(vi.mocked(api.startSession).mock.calls[1]![0].coaching_mode).toBe('tutor')
    expect(vi.mocked(api.startSession).mock.calls[1]![1]).not.toBe(vi.mocked(api.startSession).mock.calls[0]![1])
    expect(screen.getAllByRole('button', { name: 'Type instead' })).toHaveLength(1)
    await user.click(screen.getByText('Optional notes Swathi may use in chat'))
    expect(await screen.findByText('No saved AI memory facts yet.')).toBeVisible()
  })

  it('starts Ask Swathi only after acknowledgement and keeps English-first permission-led help', async () => {
    const initial = session('question_help')
    const api = apiFor(initial)
    const answer = { ...turn('awaiting_question', 1, 'question_help'), transcript: 'Past tense ಎಂದರೇನು?', reply: { text: 'Past tense describes an earlier action.', locale: 'en-IN' as const }, assessment: null }
    const offer = { ...turn('awaiting_explanation_permission', 2, 'question_help'), reply: { text: 'Would you like me to explain in Kannada?', locale: 'en-IN' as const }, assessment: null }
    const explanation = { ...turn('awaiting_question', 3, 'question_help'), assistant_reply: null, reply: { text: 'ಈಗಾಗಲೇ ನಡೆದ ಕೆಲಸಕ್ಕೆ past tense ಬಳಸಿ.', locale: 'kn-IN' as const }, assessment: null }
    vi.mocked(api.createTurn).mockResolvedValueOnce(answer).mockResolvedValueOnce(offer).mockResolvedValueOnce(explanation).mockResolvedValueOnce({ ...answer, id: 'turn-4', interaction: interaction('question_help', 'awaiting_question', 4), reply: { text: 'Here is another English answer.', locale: 'en-IN' } })
    render(<QuestionHelpJourney api={api} createClientId={createClientId} locale="en-IN" localizationPolicy="development-preview" />)
    const user = userEvent.setup()
    expect(screen.getByRole('button', { name: 'Start question help' })).toBeDisabled()
    await user.click(screen.getByRole('checkbox'))
    await user.click(screen.getByRole('button', { name: 'Start question help' }))
    await screen.findByRole('heading', { name: 'Your question or response' })
    await typeAndSend('Past tense ಎಂದರೇನು?', true)
    expect(await screen.findByText('Past tense describes an earlier action.')).toHaveAttribute('lang', 'en-IN')
    await user.click(screen.getByRole('button', { name: 'I did not understand' }))
    await screen.findByRole('button', { name: 'Yes, explain in my language' })
    expect(screen.queryByText(explanation.reply.text)).not.toBeInTheDocument()
    expect(playConversationSpeechResult).not.toHaveBeenCalledWith(expect.objectContaining({ text: explanation.reply.text }))
    await user.click(screen.getByRole('button', { name: 'Yes, explain in my language' }))
    expect(await screen.findByText(explanation.reply.text)).toHaveAttribute('lang', 'kn-IN')
    expect(vi.mocked(api.createTurn).mock.calls[2]![1]).toMatchObject({ action: 'accept_explanation', expected_revision: 2 })
    await typeAndSend('One more question.', true)
    expect(await screen.findByText('Here is another English answer.')).toHaveAttribute('lang', 'en-IN')
    expect(vi.mocked(api.createTurn).mock.calls[3]![1].action).toBe('question')
  })

  it('declines language help without synthesizing an explanation', async () => {
    const initial = { ...session('question_help'), interaction: interaction('question_help', 'awaiting_explanation_permission', 2) }
    const api = apiFor(initial)
    vi.mocked(api.createTurn).mockResolvedValue({ ...turn('awaiting_question', 3, 'question_help'), reply: { text: 'Let us keep using simple English.', locale: 'en-IN' } })
    renderDialogue(api, initial)
    await userEvent.setup().click(screen.getByRole('button', { name: 'No, keep English' }))
    expect(await screen.findByText('Let us keep using simple English.')).toHaveAttribute('lang', 'en-IN')
    expect(vi.mocked(api.createTurn).mock.calls[0]![1]).toMatchObject({ action: 'decline_explanation', expected_revision: 2 })
    expect(api.synthesizeSpeech).not.toHaveBeenCalled()
  })
})
