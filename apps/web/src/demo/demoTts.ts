/**
 * Demo speech playback: neural TTS via `/demo/tts` first (Sarvam,
 * ElevenLabs, Azure with failover), then browser speech as fallback.
 *
 * Same-origin `/demo/tts` is the product path. Optional
 * `VITE_DEMO_TTS_ORIGIN` lets the Android WebView call that host.
 * Provider secrets never enter this bundle.
 */

import {
  browserSpeechSynthesisAvailable,
  speakWithBrowserSpeech,
  stopBrowserSpeech,
} from './browserSpeech'

export type DemoSpeechSource = 'neural' | 'browser'

let currentAudio: HTMLAudioElement | null = null
let currentObjectUrl: string | null = null
let azureConfiguredCache: boolean | null = null

export function demoTtsOrigin(): string {
  const configured = import.meta.env.VITE_DEMO_TTS_ORIGIN
  if (typeof configured !== 'string') {
    return ''
  }

  return configured.trim().replace(/\/$/u, '')
}

export function demoTtsUrl(
  path: '/demo/tts' | '/demo/tts/status' | '/demo/tts/settings',
): string {
  const origin = demoTtsOrigin()
  return origin === '' ? path : `${origin}${path}`
}

export function stopDemoSpeech(): void {
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

export async function demoAzureTtsConfigured(): Promise<boolean> {
  // Re-check when previously false so a restarted demo with credentials
  // is picked up after HMR without a full browser reload.
  if (azureConfiguredCache === true) {
    return true
  }

  try {
    const response = await fetch(demoTtsUrl('/demo/tts/status'), {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    })
    if (!response.ok) {
      azureConfiguredCache = false
      return false
    }
    const body = (await response.json()) as { configured?: unknown }
    azureConfiguredCache = body.configured === true
    return azureConfiguredCache
  } catch {
    azureConfiguredCache = false
    return false
  }
}

/** Test helper — clears the status cache between cases. */
export function resetDemoAzureTtsStatusCache(): void {
  azureConfiguredCache = null
}

async function speakWithAzureTts(
  text: string,
  locale: string,
): Promise<void> {
  const response = await fetch(demoTtsUrl('/demo/tts'), {
    method: 'POST',
    headers: {
      Accept: 'audio/wav',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ locale, text }),
  })

  if (!response.ok) {
    throw new Error(`azure_tts_http_${response.status}`)
  }

  const blob = await response.blob()
  if (blob.size === 0) {
    throw new Error('azure_tts_empty')
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
      finish(() => reject(new Error('Azure TTS playback timed out.')))
    }, timeoutMs)

    audio.onended = () => finish(() => resolve())
    audio.onerror = () =>
      finish(() => reject(new Error('Azure TTS playback failed.')))
    void audio.play().catch((error: unknown) => {
      finish(() =>
        reject(
          error instanceof Error
            ? error
            : new Error('Azure TTS playback failed to start.'),
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

  const azureReady = await demoAzureTtsConfigured()
  if (azureReady) {
    try {
      await speakWithAzureTts(trimmed, locale)
      return 'neural'
    } catch {
      // Fall through to browser speech.
    }
  }

  if (!browserSpeechSynthesisAvailable()) {
    throw new Error('Neural TTS and browser speech are both unavailable.')
  }

  await speakWithBrowserSpeech(trimmed, locale)
  return 'browser'
}

export function demoSpeechPlaybackAvailable(
  azureConfigured: boolean,
): boolean {
  return azureConfigured || browserSpeechSynthesisAvailable()
}
