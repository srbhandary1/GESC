import type { CapturedAudio } from '../learner/BrowserAudioRecorder'
import {
  encodeMonoPcmWav,
  PCM_WAV_SAMPLE_RATE,
  resampleMono,
} from '../shared/pcmWav'

const MAXIMUM_DURATION_MS = 120_000

export async function audioToPcmWav(
  capture: CapturedAudio,
  maximumDurationMs = MAXIMUM_DURATION_MS,
): Promise<Blob> {
  if (
    capture.durationMs < 1 ||
    maximumDurationMs < 1 ||
    maximumDurationMs > MAXIMUM_DURATION_MS ||
    capture.blob.size < 1
  ) {
    throw new Error('The recording is outside the accepted bounds.')
  }

  if (
    (capture.blob.type === 'audio/wav' || capture.mimeType === 'audio/wav') &&
    capture.blob.size >= 44
  ) {
    return trimCanonicalPcmWav(capture.blob, maximumDurationMs)
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
    const maximumSamples = Math.floor(
      (PCM_WAV_SAMPLE_RATE * maximumDurationMs) / 1000,
    )

    return new Blob(
      [encodeMonoPcmWav(mono.subarray(0, maximumSamples))],
      { type: 'audio/wav' },
    )
  } finally {
    await context.close()
  }
}

async function trimCanonicalPcmWav(
  blob: Blob,
  maximumDurationMs: number,
): Promise<Blob> {
  const bytes = await blob.arrayBuffer()
  const view = new DataView(bytes)
  if (
    bytes.byteLength < 44 ||
    ascii(view, 0, 4) !== 'RIFF' ||
    ascii(view, 8, 4) !== 'WAVE' ||
    ascii(view, 36, 4) !== 'data'
  ) {
    throw new Error('The recording is not a canonical PCM WAV file.')
  }

  const byteRate = view.getUint32(28, true)
  const blockAlign = view.getUint16(32, true)
  const dataBytes = view.getUint32(40, true)
  const maximumBytes =
    Math.floor((byteRate * maximumDurationMs) / 1000 / blockAlign) * blockAlign
  const acceptedDataBytes = Math.min(dataBytes, maximumBytes)
  if (acceptedDataBytes < 1 || 44 + dataBytes > bytes.byteLength) {
    throw new Error('The recording is outside the accepted bounds.')
  }

  if (acceptedDataBytes === dataBytes) {
    return blob.type === 'audio/wav'
      ? blob
      : new Blob([blob], { type: 'audio/wav' })
  }

  const trimmed = bytes.slice(0, 44 + acceptedDataBytes)
  const trimmedView = new DataView(trimmed)
  trimmedView.setUint32(4, 36 + acceptedDataBytes, true)
  trimmedView.setUint32(40, acceptedDataBytes, true)

  return new Blob([trimmed], { type: 'audio/wav' })
}

function ascii(view: DataView, offset: number, length: number): string {
  let value = ''
  for (let index = 0; index < length; index += 1) {
    value += String.fromCharCode(view.getUint8(offset + index))
  }

  return value
}
