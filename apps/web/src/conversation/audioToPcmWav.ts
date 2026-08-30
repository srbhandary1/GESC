import type { CapturedAudio } from '../learner/BrowserAudioRecorder'
import {
  encodeMonoPcmWav,
  PCM_WAV_SAMPLE_RATE,
  resampleMono,
} from '../shared/pcmWav'

const MAXIMUM_DURATION_MS = 120_000

export async function audioToPcmWav(
  capture: CapturedAudio,
): Promise<Blob> {
  if (
    capture.durationMs < 1 ||
    capture.durationMs > MAXIMUM_DURATION_MS ||
    capture.blob.size < 1
  ) {
    throw new Error('The recording is outside the accepted bounds.')
  }

  if (
    (capture.blob.type === 'audio/wav' || capture.mimeType === 'audio/wav') &&
    capture.blob.size >= 44
  ) {
    return capture.blob.type === 'audio/wav'
      ? capture.blob
      : new Blob([capture.blob], { type: 'audio/wav' })
  }

  const AudioContextConstructor =
    globalThis.AudioContext ??
    (
      globalThis as typeof globalThis & {
        webkitAudioContext?: typeof AudioContext
      }
    ).webkitAudioContext

  if (AudioContextConstructor === undefined) {
    throw new Error('Audio conversion is unavailable.')
  }

  const context = new AudioContextConstructor()

  try {
    const decoded = await context.decodeAudioData(
      await capture.blob.arrayBuffer(),
    )
    const sourceLength = decoded.length
    const mixed = new Float32Array(sourceLength)

    for (let index = 0; index < sourceLength; index += 1) {
      let sample = 0
      for (let channel = 0; channel < decoded.numberOfChannels; channel += 1) {
        sample += decoded.getChannelData(channel)[index] ?? 0
      }
      mixed[index] = sample / decoded.numberOfChannels
    }

    const mono = resampleMono(mixed, decoded.sampleRate, PCM_WAV_SAMPLE_RATE)

    return new Blob([encodeMonoPcmWav(mono)], { type: 'audio/wav' })
  } finally {
    await context.close()
  }
}
