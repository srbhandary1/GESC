import {
  registerSpeechAudio,
  unregisterSpeechAudio,
} from '../shared/stopSpeechPlayback'

export interface PlayBase64AudioOptions {
  readonly signal?: AbortSignal
  readonly onStarted?: () => void
  readonly onElement?: (audio: HTMLAudioElement) => void
}

export async function playBase64Audio(
  base64: string,
  mimeType = 'audio/wav',
  options: PlayBase64AudioOptions = {},
): Promise<void> {
  if (options.signal?.aborted) {
    throw abortError(options.signal)
  }

  const bytes = decodeBase64ToBytes(base64)
  const copy = new ArrayBuffer(bytes.byteLength)
  new Uint8Array(copy).set(bytes)
  const blob = new Blob([copy], { type: mimeType })
  const url = URL.createObjectURL(blob)
  const audio = new Audio()
  options.onElement?.(audio)
  registerSpeechAudio(audio)
  audio.preload = 'auto'
  audio.src = url

  try {
    await new Promise<void>((resolve, reject) => {
      let started = false
      const notifyStarted = () => {
        if (started) {
          return
        }

        started = true
        options.onStarted?.()
      }
      const onAbort = () => {
        audio.pause()
        reject(abortError(options.signal))
      }

      options.signal?.addEventListener('abort', onAbort, { once: true })
      audio.onended = () => resolve()
      audio.onerror = () => reject(new Error('audio_playback_failed'))
      audio.onplaying = notifyStarted

      void audio
        .play()
        .then(() => {
          if (!audio.paused) {
            notifyStarted()
          }
        })
        .catch(reject)
    })
  } finally {
    unregisterSpeechAudio(audio)
    URL.revokeObjectURL(url)
  }
}

export function decodeBase64ToBytes(base64: string): Uint8Array {
  const binary = atob(base64)
  const bytes = new Uint8Array(binary.length)
  const block = 0x8000

  for (let offset = 0; offset < binary.length; offset += block) {
    const slice = binary.slice(offset, offset + block)
    for (let index = 0; index < slice.length; index += 1) {
      bytes[offset + index] = slice.charCodeAt(index)
    }
  }

  return bytes
}

function abortError(signal?: AbortSignal): DOMException {
  if (signal?.reason instanceof DOMException) {
    return signal.reason
  }

  return new DOMException('The audio playback was aborted.', 'AbortError')
}
