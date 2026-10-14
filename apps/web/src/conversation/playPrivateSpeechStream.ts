import { defaultAuthenticationRuntime } from '../auth/defaultAuthentication'
import {
  registerSpeechAudio,
  unregisterSpeechAudio,
} from '../shared/stopSpeechPlayback'
import type { PlayBase64AudioOptions } from './playBase64Audio'

export async function playPrivateSpeechStream(
  path: string,
  mimeType: string,
  options?: PlayBase64AudioOptions,
): Promise<void> {
  if (
    path.startsWith('/') &&
    !defaultAuthenticationRuntime.privateMediaRequiresFetch
  ) {
    await playHref(path, mimeType, options)
    return
  }

  const response = await defaultAuthenticationRuntime.apiFetch(
    resolveSpeechUrl(path),
    {
      credentials: 'include',
      headers: { Accept: mimeType },
      signal: options?.signal,
    },
  )

  if (!response.ok) {
    throw new Error('Conversation speech stream unavailable.')
  }

  const blob = await response.blob()
  const objectUrl = URL.createObjectURL(blob)

  try {
    await playHref(objectUrl, blob.type === '' ? mimeType : blob.type, options)
  } finally {
    URL.revokeObjectURL(objectUrl)
  }
}

function resolveSpeechUrl(path: string): string {
  if (/^https?:\/\//u.test(path)) {
    return path
  }

  const base = defaultAuthenticationRuntime.apiBaseUrl
  if (!/^https?:\/\//u.test(base)) {
    return path
  }

  return `${new URL(base).origin}${path.startsWith('/') ? path : `/${path}`}`
}

async function playHref(
  href: string,
  mimeType: string,
  options?: PlayBase64AudioOptions,
): Promise<void> {
  const audio = new Audio()
  registerSpeechAudio(audio)
  audio.preload = 'auto'
  audio.src = href

  const started = options?.onStarted
  const onTimeUpdate = options?.onTimeUpdate
  const signal = options?.signal
  let notified = false
  const notifyStarted = () => {
    if (notified) {
      return
    }

    notified = true
    started?.()
  }

  if (onTimeUpdate !== undefined) {
    audio.ontimeupdate = () => {
      onTimeUpdate(Math.round(audio.currentTime * 1000))
    }
  }

  try {
    await new Promise<void>((resolve, reject) => {
      const abort = () => {
        audio.pause()
        reject(new DOMException('The operation was aborted.', 'AbortError'))
      }

      if (signal?.aborted) {
        abort()
        return
      }

      signal?.addEventListener('abort', abort, { once: true })
      audio.onended = () => resolve()
      audio.onerror = () =>
        reject(new Error('Conversation speech playback failed.'))
      audio.onplaying = notifyStarted

      void audio.play().then(() => {
        if (!audio.paused) {
          notifyStarted()
        }
      }, reject)
    })
  } finally {
    options?.onTimeUpdate?.(Math.round(audio.currentTime * 1000))
    unregisterSpeechAudio(audio)
  }

  void mimeType
}
