import {
  registerSpeechAudio,
  unregisterSpeechAudio,
} from '../shared/stopSpeechPlayback'

export async function playBase64Audio(
  base64: string,
  mimeType = 'audio/wav',
): Promise<void> {
  const binary = atob(base64)
  const bytes = new Uint8Array(binary.length)
  for (let index = 0; index < binary.length; index += 1) {
    bytes[index] = binary.charCodeAt(index)
  }
  const blob = new Blob([bytes], { type: mimeType })
  const url = URL.createObjectURL(blob)
  const audio = new Audio(url)
  registerSpeechAudio(audio)

  try {
    await new Promise<void>((resolve, reject) => {
      audio.onended = () => resolve()
      audio.onerror = () => reject(new Error('audio_playback_failed'))
      void audio.play().catch(reject)
    })
  } finally {
    unregisterSpeechAudio(audio)
    URL.revokeObjectURL(url)
  }
}
