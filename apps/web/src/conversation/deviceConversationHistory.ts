import { Capacitor, registerPlugin } from '@capacitor/core'
import type { ConversationSpeechResult, CurrentUserResponse } from '@global-english/contracts'
import type { FriendSession, FriendTurn } from './ConversationJourneyApi'
import { defaultAuthenticationRuntime } from '../auth/defaultAuthentication'

export interface SavedAudio { readonly base64: string; readonly mimeType: string }
export interface SavedConversationTurn {
  readonly id: string
  readonly sessionId: string
  readonly savedAt: string
  readonly mode: string
  readonly learner: string | null
  readonly reply: string
  readonly replyLocale: string
  readonly correction: string | null
  readonly explanation: string | null
  readonly learnerAudio?: SavedAudio
  readonly replyAudio?: SavedAudio
  readonly guidanceAudio?: SavedAudio
}
export interface HistoryItem { id: string; metadata: string }
export interface HistoryBridge {
  activate(input: { binding: string }): Promise<void>
  deactivate(): Promise<void>
  status(): Promise<{ enabled: boolean }>
  setEnabled(input: { enabled: boolean }): Promise<void>
  put(input: { id: string; metadata: string; body: string }): Promise<void>
  list(): Promise<{ items: HistoryItem[] }>
  read(input: { id: string }): Promise<{ body: string }>
  remove(input: { id: string }): Promise<void>
  clear(): Promise<void>
}
const bridge = registerPlugin<HistoryBridge>('GlobalEnglishConversationHistory')
export const HISTORY_EVENT = 'global-english:device-history-changed'
let generation = 0
let enabled = false
let active = false
let failure = false
let binding: string | null = null
let ready: Promise<void> = Promise.resolve()
const notify = () => window.dispatchEvent(new Event(HISTORY_EVENT))
export const historyAvailable = () => Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android' && Capacitor.isPluginAvailable('GlobalEnglishConversationHistory')
export const historyEnabled = () => active && enabled
export const historyFailed = () => failure

export async function bindDeviceHistory(identity: CurrentUserResponse | null) {
  const member = identity?.current_membership
  const next = identity && member ? `${identity.user.id}:${member.organization.id}:${member.id}` : null
  if (binding === next) return ready
  binding = next
  const epoch = ++generation
  enabled = false; active = false; failure = false; notify()
  if (!historyAvailable()) return
  ready = (async () => {
    if (!next) { await bridge.deactivate(); return }
    await bridge.activate({ binding: next })
    const status = await bridge.status()
    if (epoch !== generation) return
    active = true; enabled = status.enabled; notify()
  })().catch(() => { if (epoch === generation) { failure = true; notify() } })
  return ready
}
export async function setDeviceHistoryEnabled(value: boolean) {
  await ready
  if (!active) throw new Error('history_locked')
  const epoch = ++generation; enabled = false // Invalidate saves already waiting on media.
  await bridge.setEnabled({ enabled: value })
  if (epoch === generation) { enabled = value; failure = false; notify() }
}
export async function clearDeviceHistory() {
  ++generation; enabled = false; failure = false; notify()
  await ready
  if (!active) throw new Error('history_locked')
  await bridge.clear(); notify()
}
export async function listDeviceHistory(): Promise<HistoryItem[]> { await ready; if (!active) return []; const epoch = generation; const result = await bridge.list(); return epoch === generation ? result.items : [] }
export async function readDeviceHistory(id: string): Promise<SavedConversationTurn> { await ready; if (!active) throw new Error('history_locked'); const epoch = generation; const result = await bridge.read({ id }); if (epoch !== generation) throw new Error('history_locked'); return JSON.parse(result.body) as SavedConversationTurn }
export async function removeDeviceHistory(id: string) { ++generation; await bridge.remove({ id }); notify() }

export async function audioForHistory(blob: Blob): Promise<SavedAudio> {
  const bytes = new Uint8Array(await blob.arrayBuffer())
  let binary = ''
  for (let offset = 0; offset < bytes.length; offset += 8192) binary += String.fromCharCode(...bytes.subarray(offset, offset + 8192))
  return { base64: btoa(binary), mimeType: blob.type || 'audio/wav' }
}
async function savedAudio(base64?: string | null, mimeType?: string | null, path?: string | null): Promise<SavedAudio | undefined> {
  if (base64) return { base64, mimeType: mimeType || 'audio/wav' }
  if (!path) return undefined
  // Download only the authenticated first-party audio already created for this turn.
  if (!path.startsWith('/api/v1/')) throw new Error('invalid_history_audio')
  const base = defaultAuthenticationRuntime.apiBaseUrl
  const origin = /^https?:/u.test(base) ? new URL(base).origin : ''
  const response = await defaultAuthenticationRuntime.apiFetch(origin + path, { credentials: 'include' })
  if (!response.ok) throw new Error('history_audio_unavailable')
  return audioForHistory(await response.blob())
}
export async function archiveConversationTurn(session: FriendSession, turn: FriendTurn, learnerAudio?: SavedAudio) {
  if (!historyEnabled() || turn.status !== 'completed' || turn.safety_state !== 'ok') return
  const epoch = generation
  try {
    const speech = turn.speech
    const replyAudio = await savedAudio(speech?.model_audio_base64, speech?.model_audio_mime_type, speech?.model_audio_stream_path)
    const guidanceAudio = speech?.guidance_speech_status === 'deferred' ? undefined
      : await savedAudio(speech?.guidance_audio_base64, speech?.guidance_audio_mime_type, speech?.guidance_audio_stream_path)
    if (epoch !== generation || !historyEnabled()) return
    const item: SavedConversationTurn = { id: turn.id, sessionId: session.id, savedAt: new Date().toISOString(), mode: session.interaction?.flow ?? session.mode,
      learner: turn.transcript, reply: turn.reply?.text ?? turn.assistant_reply ?? '', replyLocale: turn.reply?.locale ?? 'en-IN',
      correction: turn.priority_correction?.correction_en ?? null, explanation: turn.priority_correction?.explanation_l1 ?? null,
      learnerAudio, replyAudio, guidanceAudio }
    await bridge.put({ id: item.id, metadata: JSON.stringify({ savedAt: item.savedAt, preview: item.learner?.slice(0, 70) ?? item.reply.slice(0, 70), sessionId: item.sessionId }), body: JSON.stringify(item) })
    if (epoch === generation) { failure = false; notify() }
  } catch { if (epoch === generation) { failure = true; notify() } }
}

/** Save the roleplay opening too, without manufacturing a submitted learner turn. */
export async function archiveConversationOpening(session: FriendSession, speech: ConversationSpeechResult) {
  const reply = session.interaction?.opening_reply
  if (!reply) return
  await archiveConversationTurn(session, {
    id: `${session.id}_opening`, conversation_session_id: session.id, turn_number: 0,
    status: 'completed', transcript: null, understood_intent: 'opening', assistant_reply: reply.text,
    reply, speech, priority_correction: null, safety_state: 'ok',
    usage: { provider_attempts: 0, total_latency_ms: 0, estimated_cost_microunits: 0 },
    events_url: '', replayed: false, assessment: 'unassessed', original_turn_id: null,
  })
}
