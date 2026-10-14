import type { ConversationPlaybackProgress, ConversationPlaybackProgressRequest } from '@global-english/contracts'
import { createClientId } from '../shared/createClientId'
import { defaultConversationJourneyApi } from './WebConversationJourneyApi'

/** Only playback position advances usage; provider generation and waiting do not. */
export async function meterSpeechPlayback(
  grant: string | undefined,
  signal: AbortSignal | undefined,
  report: (input: ConversationPlaybackProgressRequest) => Promise<ConversationPlaybackProgress> = input => defaultConversationJourneyApi.recordPlayback(input),
) {
  const controller = new AbortController()
  const abort = () => controller.abort(signal?.reason)
  if (signal?.aborted) abort()
  signal?.addEventListener('abort', abort, { once: true })
  const key = createClientId()
  let played = 0
  let submitted = 0
  let allowed = Infinity
  let pending = Promise.resolve()
  let failed = false
  if (grant) {
    try {
      const started = await report({ grant, playback_key: key, played_ms: 0 })
      allowed = started.allowed_ms
      if (allowed <= 0) controller.abort()
    } catch (error) {
      signal?.removeEventListener('abort', abort)
      throw error
    }
  }
  const submit = () => {
    if (!grant || played <= submitted || failed) return
    const value = Math.floor(played)
    submitted = value
    pending = pending.then(async () => {
      const current = await report({ grant, playback_key: key, played_ms: value })
      allowed = current.allowed_ms
      if (played >= allowed) controller.abort()
    }).catch(() => {
      // Stop instead of continuing unmetered after connectivity/account failure.
      failed = true
      controller.abort()
    })
  }
  return {
    signal: controller.signal,
    update(timeMs: number) {
      if (!Number.isFinite(timeMs) || timeMs < 0) return
      played = Math.max(played, Math.min(timeMs, allowed))
      if (played - submitted >= 5000) submit()
      if (played >= allowed) controller.abort()
    },
    async finish() {
      submit()
      await pending
      signal?.removeEventListener('abort', abort)
    },
  }
}
