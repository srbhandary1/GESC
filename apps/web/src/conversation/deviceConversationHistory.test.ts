import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { CurrentUserResponse } from '@global-english/contracts'
import type { FriendSession, FriendTurn } from './ConversationJourneyApi'
const native = vi.hoisted(() => ({ activate: vi.fn(), deactivate: vi.fn(), status: vi.fn(async () => ({ enabled: false })), setEnabled: vi.fn(), put: vi.fn(), list: vi.fn(async () => ({ items: [] })), read: vi.fn(), remove: vi.fn(), clear: vi.fn() }))
const fetchAudio = vi.hoisted(() => vi.fn())
vi.mock('@capacitor/core', () => ({ Capacitor: { isNativePlatform: () => true, getPlatform: () => 'android', isPluginAvailable: () => true }, registerPlugin: () => native }))
vi.mock('../auth/defaultAuthentication', () => ({ defaultAuthenticationRuntime: { apiBaseUrl: 'https://example.test/api/v1', apiFetch: fetchAudio } }))
import { archiveConversationTurn, bindDeviceHistory, clearDeviceHistory, historyEnabled, listDeviceHistory, readDeviceHistory, setDeviceHistoryEnabled } from './deviceConversationHistory'
const identity = (member: string) => ({ user: { id: 'user' }, current_membership: { id: member, organization: { id: 'organization' } } }) as CurrentUserResponse
const session = { id: 'session', mode: 'friend', interaction: { flow: 'question_help' } } as FriendSession
const turn = { id: 'turn', status: 'completed', safety_state: 'ok', transcript: 'Good morning.', reply: { text: 'Hello!', locale: 'en-IN' }, speech: { model_audio_base64: 'UklGRg==', model_audio_mime_type: 'audio/wav' } } as FriendTurn
beforeEach(async () => { await bindDeviceHistory(null); vi.clearAllMocks(); native.status.mockResolvedValue({ enabled: false }); native.list.mockResolvedValue({ items: [] }); await bindDeviceHistory(identity('member')) })
describe('optional account-bound device history', () => {
  it('never saves without opt-in or saves rejected output; persists successful text and both audio sources', async () => {
    await archiveConversationTurn(session, turn)
    expect(native.put).not.toHaveBeenCalled()
    await setDeviceHistoryEnabled(true)
    await archiveConversationTurn(session, { ...turn, safety_state: 'fallback' })
    expect(native.put).not.toHaveBeenCalled()
    await archiveConversationTurn(session, turn, { base64: 'bWluZQ==', mimeType: 'audio/wav' })
    const item = JSON.parse(native.put.mock.calls[0]![0].body)
    expect(item.learner).toBe('Good morning.')
    expect(item.replyAudio.base64).toBe('UklGRg==')
    expect(item.learnerAudio.base64).toBe('bWluZQ==')
    expect(fetchAudio).not.toHaveBeenCalled()
  })
  it('does not trigger deferred guidance synthesis just to fill saved history', async () => {
    await setDeviceHistoryEnabled(true)
    await archiveConversationTurn(session, { ...turn, speech: { ...turn.speech!, guidance_speech_status: 'deferred', guidance_audio_stream_path: '/api/v1/deferred-guidance' } })
    expect(native.put).toHaveBeenCalledOnce()
    expect(fetchAudio).not.toHaveBeenCalled()
    expect(JSON.parse(native.put.mock.calls[0]![0].body).guidanceAudio).toBeUndefined()
  })
  it('locks on logout and isolates another membership without enabling it', async () => {
    await setDeviceHistoryEnabled(true)
    await bindDeviceHistory(null)
    expect(historyEnabled()).toBe(false)
    await archiveConversationTurn(session, turn)
    expect(native.put).not.toHaveBeenCalled()
    await bindDeviceHistory(identity('other'))
    expect(native.activate).toHaveBeenLastCalledWith({ binding: 'user:organization:other' })
    expect(historyEnabled()).toBe(false)
  })
  it('deletion invalidates a save waiting for private audio and turns saving off', async () => {
    await setDeviceHistoryEnabled(true)
    let complete: ((response: Response) => void) | undefined
    fetchAudio.mockImplementationOnce(() => new Promise(resolve => { complete = resolve }))
    const saving = archiveConversationTurn(session, { ...turn, speech: { ...turn.speech!, model_audio_base64: null, model_audio_stream_path: '/api/v1/audio' } })
    await clearDeviceHistory()
    complete?.(new Response(new Blob(['audio'], { type: 'audio/wav' })))
    await saving
    expect(native.put).not.toHaveBeenCalled()
    expect(native.clear).toHaveBeenCalledOnce()
    expect(historyEnabled()).toBe(false)
  })
  it('never reveals a read finishing after account switch', async () => {
    let complete: ((value: { body: string }) => void) | undefined
    native.read.mockImplementationOnce(() => new Promise(resolve => { complete = resolve }))
    const reading = readDeviceHistory('turn')
    await vi.waitFor(() => expect(native.read).toHaveBeenCalled())
    await bindDeviceHistory(identity('other'))
    complete?.({ body: JSON.stringify({ reply: 'First account private answer' }) })
    await expect(reading).rejects.toThrow('history_locked')
    await bindDeviceHistory(null)
    expect(await listDeviceHistory()).toEqual([])
  })
})
