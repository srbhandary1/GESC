/**
 * Demo speech playback: cloud neural TTS via local `/demo/tts` first,
 * then on-device browser speech as fallback. Presentation-only.
 */

import {
  browserSpeechSynthesisAvailable,
  speakWithBrowserSpeech,
  stopBrowserSpeech,
} from './browserSpeech'
import { stopSpeechPlayback } from '../shared/stopSpeechPlayback'

export type DemoSpeechSource = 'cloud' | 'browser'

let currentAudio: HTMLAudioElement | null = null
let currentObjectUrl: string | null = null
let cloudConfiguredCache: boolean | null = null

export function stopDemoSpeech(): void {
  stopSpeechPlayback()
  if (currentAudio !== null) {
    currentAudio.pause()
    currentAudio.src = ''
    currentAudio = null
  }

  if (currentObjectUrl !== null) {
    URL.revokeObjectURL(currentObjectUrl)
    currentObjectUrl = null
  }

  stopBrowserSpeech()
}

export async function demoCloudTtsConfigured(): Promise<boolean> {
  // Re-check when previously false so a restarted demo with credentials
  // is picked up after HMR without a full browser reload.
  if (cloudConfiguredCache === true) {
    return true
  }

  try {
    const response = await fetch('/demo/tts/status', {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    })
    if (!response.ok) {
      cloudConfiguredCache = false
      return false
    }
    const body = (await response.json()) as { configured?: unknown }
    cloudConfiguredCache = body.configured === true
    return cloudConfiguredCache
  } catch {
    cloudConfiguredCache = false
    return false
  }
}

/** Test helper — clears the status cache between cases. */
export function resetDemoCloudTtsStatusCache(): void {
  cloudConfiguredCache = null
}

async function speakWithCloudTts(
  text: string,
  locale: string,
): Promise<void> {
  const response = await fetch('/demo/tts', {
    method: 'POST',
    headers: {
      Accept: 'audio/wav',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ locale, text }),
  })

  if (!response.ok) {
    throw new Error(`cloud_tts_http_${response.status}`)
  }

  const blob = await response.blob()
  if (blob.size === 0) {
    throw new Error('cloud_tts_empty')
  }

  const objectUrl = URL.createObjectURL(blob)
  currentObjectUrl = objectUrl
  const audio = new Audio(objectUrl)
  currentAudio = audio

  const timeoutMs = Math.min(60_000, Math.max(8_000, [...text].length * 80))

  await new Promise<void>((resolve, reject) => {
    let settled = false
    const finish = (action: () => void) => {
      if (settled) {
        return
      }
      settled = true
      globalThis.clearTimeout(timer)
      action()
    }

    const timer = globalThis.setTimeout(() => {
      stopDemoSpeech()
      finish(() => reject(new Error('Cloud voice playback timed out.')))
    }, timeoutMs)

    audio.onended = () => finish(() => resolve())
    audio.onerror = () =>
      finish(() => reject(new Error('Cloud voice playback failed.')))
    void audio.play().catch((error: unknown) => {
      finish(() =>
        reject(
          error instanceof Error
            ? error
            : new Error('Cloud voice playback failed to start.'),
        ),
      )
    })
  })
}

export async function speakDemoText(
  text: string,
  locale: string,
): Promise<DemoSpeechSource> {
  const trimmed = text.trim()
  if (trimmed === '') {
    throw new Error('Demo speech text is empty.')
  }

  stopDemoSpeech()

  const cloudReady = await demoCloudTtsConfigured()
  if (cloudReady) {
    try {
      await speakWithCloudTts(trimmed, locale)
      return 'cloud'
    } catch {
      // Fall through to browser speech.
    }
  }

  if (!browserSpeechSynthesisAvailable()) {
    throw new Error('Cloud and device speech are both unavailable.')
  }

  await speakWithBrowserSpeech(trimmed, locale)
  return 'browser'
}

export function demoSpeechPlaybackAvailable(
  cloudConfigured: boolean,
): boolean {
  return cloudConfigured || browserSpeechSynthesisAvailable()
}
