/**
 * Browser Web Speech helpers for the disclosed synthetic demo.
 *
 * Prefer `speakDemoText` from `./demoTts` for playback: Azure neural TTS via
 * local `/demo/tts` first, then these browser adapters as fallback (D-0092).
 * Recognition remains on-device only. Production product speech remains behind
 * the project-owned STT/TTS ports (PR-009+) and is unchanged.
 */

export const DEMO_SPEECH_PROTOCOL = 'demo-speech:'

export interface DemoSpeechUtterance {
  readonly locale: string
  readonly text: string
}

export function encodeDemoSpeechUrl(
  locale: string,
  text: string,
): string {
  return `${DEMO_SPEECH_PROTOCOL}${encodeURIComponent(locale)}:${encodeURIComponent(text)}`
}

export function isDemoSpeechUrl(source: string): boolean {
  return source.startsWith(DEMO_SPEECH_PROTOCOL)
}

export function decodeDemoSpeechUrl(
  source: string,
): DemoSpeechUtterance | null {
  if (!isDemoSpeechUrl(source)) {
    return null
  }

  const payload = source.slice(DEMO_SPEECH_PROTOCOL.length)
  const separator = payload.indexOf(':')

  if (separator <= 0) {
    return null
  }

  try {
    return {
      locale: decodeURIComponent(payload.slice(0, separator)),
      text: decodeURIComponent(payload.slice(separator + 1)),
    }
  } catch {
    return null
  }
}

export function browserSpeechSynthesisAvailable(): boolean {
  return (
    typeof globalThis !== 'undefined' &&
    typeof globalThis.speechSynthesis !== 'undefined' &&
    typeof globalThis.SpeechSynthesisUtterance !== 'undefined'
  )
}

export function stopBrowserSpeech(): void {
  if (!browserSpeechSynthesisAvailable()) {
    return
  }

  globalThis.speechSynthesis.cancel()
}

async function loadBrowserVoices(): Promise<SpeechSynthesisVoice[]> {
  if (!browserSpeechSynthesisAvailable()) {
    return []
  }

  const immediate = globalThis.speechSynthesis.getVoices()
  if (immediate.length > 0) {
    return immediate
  }

  await new Promise<void>((resolve) => {
    const finish = () => {
      globalThis.speechSynthesis.removeEventListener('voiceschanged', finish)
      resolve()
    }
    globalThis.speechSynthesis.addEventListener('voiceschanged', finish)
    globalThis.setTimeout(finish, 750)
  })

  return globalThis.speechSynthesis.getVoices()
}

export async function speakWithBrowserSpeech(
  text: string,
  locale = 'en-IN',
): Promise<void> {
  const trimmed = text.trim()

  if (trimmed === '' || !browserSpeechSynthesisAvailable()) {
    throw new Error('Browser speech synthesis is unavailable.')
  }

  stopBrowserSpeech()
  const voices = await loadBrowserVoices()
  const timeoutMs = Math.min(45_000, Math.max(6_000, [...trimmed].length * 90))

  await new Promise<void>((resolve, reject) => {
    const utterance = new SpeechSynthesisUtterance(trimmed)
    utterance.lang = locale
    utterance.rate = 0.92
    utterance.pitch = 1

    let settled = false
    const finish = (action: () => void) => {
      if (settled) {
        return
      }
      settled = true
      globalThis.clearTimeout(timer)
      globalThis.clearTimeout(startTimer)
      action()
    }

    const timer = globalThis.setTimeout(() => {
      stopBrowserSpeech()
      finish(() =>
        reject(new Error('Browser speech synthesis timed out.')),
      )
    }, timeoutMs)

    utterance.onend = () => finish(() => resolve())
    utterance.onerror = (event) => {
      if (event.error === 'canceled' || event.error === 'interrupted') {
        finish(() => resolve())
        return
      }

      finish(() =>
        reject(new Error(`Browser speech synthesis failed: ${event.error}`)),
      )
    }
    utterance.onstart = () => {
      globalThis.clearTimeout(startTimer)
    }

    const preferred = pickVoice(locale, voices)
    if (preferred !== null) {
      utterance.voice = preferred
    }

    const startTimer = globalThis.setTimeout(() => {
      stopBrowserSpeech()
      finish(() =>
        reject(new Error('Browser speech synthesis did not start.')),
      )
    }, 900)

    globalThis.speechSynthesis.speak(utterance)
    // Chromium sometimes leaves the queue paused after cancel().
    if (globalThis.speechSynthesis.paused) {
      globalThis.speechSynthesis.resume()
    }
  })
}

function pickVoice(
  locale: string,
  voices = browserSpeechSynthesisAvailable()
    ? globalThis.speechSynthesis.getVoices()
    : [],
): SpeechSynthesisVoice | null {
  const exact = voices.find((voice) => voice.lang === locale)
  if (exact !== undefined) {
    return exact
  }

  const prefix = locale.split('-')[0] ?? locale
  return (
    voices.find((voice) =>
      voice.lang.toLowerCase().startsWith(prefix.toLowerCase()),
    ) ??
    voices.find((voice) => voice.lang.toLowerCase().startsWith('en')) ??
    voices[0] ??
    null
  )
}

type BrowserSpeechRecognitionConstructor = new () => BrowserSpeechRecognition

interface BrowserSpeechRecognition extends EventTarget {
  continuous: boolean
  interimResults: boolean
  lang: string
  maxAlternatives: number
  onaudioend: ((this: BrowserSpeechRecognition, event: Event) => void) | null
  onerror:
    | ((this: BrowserSpeechRecognition, event: SpeechRecognitionErrorEvent) => void)
    | null
  onresult:
    | ((this: BrowserSpeechRecognition, event: SpeechRecognitionEvent) => void)
    | null
  onend: ((this: BrowserSpeechRecognition, event: Event) => void) | null
  start: () => void
  stop: () => void
  abort: () => void
}

interface SpeechRecognitionEvent extends Event {
  readonly results: SpeechRecognitionResultList
}

interface SpeechRecognitionErrorEvent extends Event {
  readonly error: string
}

declare global {
  interface Window {
    SpeechRecognition?: BrowserSpeechRecognitionConstructor
    webkitSpeechRecognition?: BrowserSpeechRecognitionConstructor
  }
}

export function browserSpeechRecognitionAvailable(): boolean {
  return (
    typeof globalThis !== 'undefined' &&
    typeof globalThis.window !== 'undefined' &&
    (typeof globalThis.window.SpeechRecognition === 'function' ||
      typeof globalThis.window.webkitSpeechRecognition === 'function')
  )
}

export interface BrowserRecognitionResult {
  readonly transcript: string
}

/**
 * Captures one spoken turn with the browser speech recognizer.
 * Stops on the first final result or after the recognizer ends.
 */
export function recognizeWithBrowserSpeech(
  locale = 'en-IN',
): {
  readonly promise: Promise<BrowserRecognitionResult>
  readonly stop: () => void
} {
  if (!browserSpeechRecognitionAvailable()) {
    return {
      promise: Promise.reject(
        new Error('Browser speech recognition is unavailable.'),
      ),
      stop: () => undefined,
    }
  }

  const Recognition =
    globalThis.window.SpeechRecognition ??
    globalThis.window.webkitSpeechRecognition

  if (Recognition === undefined) {
    return {
      promise: Promise.reject(
        new Error('Browser speech recognition is unavailable.'),
      ),
      stop: () => undefined,
    }
  }

  const recognition = new Recognition()
  recognition.lang = locale
  recognition.continuous = false
  recognition.interimResults = true
  recognition.maxAlternatives = 1

  let settled = false
  let transcript = ''

  const promise = new Promise<BrowserRecognitionResult>((resolve, reject) => {
    recognition.onresult = (event) => {
      const parts: string[] = []

      for (let index = 0; index < event.results.length; index += 1) {
        const result = event.results[index]
        const alternative = result?.[0]

        if (alternative !== undefined) {
          parts.push(alternative.transcript)
        }

        if (result?.isFinal === true) {
          transcript = parts.join(' ').trim()
        }
      }

      const live = parts.join(' ').trim()
      if (live !== '') {
        transcript = live
      }
    }

    recognition.onerror = (event) => {
      if (settled) {
        return
      }

      settled = true

      if (event.error === 'aborted' || event.error === 'no-speech') {
        resolve({ transcript })
        return
      }

      reject(new Error(`Browser speech recognition failed: ${event.error}`))
    }

    recognition.onend = () => {
      if (settled) {
        return
      }

      settled = true
      resolve({ transcript: transcript.trim() })
    }

    try {
      recognition.start()
    } catch (error) {
      settled = true
      reject(
        error instanceof Error
          ? error
          : new Error('Browser speech recognition failed to start.'),
      )
    }
  })

  return {
    promise,
    stop: () => {
      try {
        recognition.stop()
      } catch {
        recognition.abort()
      }
    },
  }
}
