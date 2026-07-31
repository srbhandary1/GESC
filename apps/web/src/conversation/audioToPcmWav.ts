import type { CapturedAudio } from '../learner/BrowserAudioRecorder'

const TARGET_SAMPLE_RATE = 16_000
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
    const targetLength = Math.max(
      1,
      Math.round(decoded.duration * TARGET_SAMPLE_RATE),
    )
    const mono = new Float32Array(targetLength)

    for (let targetIndex = 0; targetIndex < targetLength; targetIndex += 1) {
      const sourcePosition =
        targetIndex * decoded.sampleRate / TARGET_SAMPLE_RATE
      const leftIndex = Math.min(
        sourceLength - 1,
        Math.floor(sourcePosition),
      )
      const rightIndex = Math.min(sourceLength - 1, leftIndex + 1)
      const fraction = sourcePosition - leftIndex
      let sample = 0

      for (let channel = 0; channel < decoded.numberOfChannels; channel += 1) {
        const values = decoded.getChannelData(channel)
        sample +=
          (values[leftIndex] ?? 0) * (1 - fraction) +
          (values[rightIndex] ?? 0) * fraction
      }

      mono[targetIndex] = sample / decoded.numberOfChannels
    }

    return new Blob([encodeMonoPcmWav(mono)], { type: 'audio/wav' })
  } finally {
    await context.close()
  }
}

function encodeMonoPcmWav(samples: Float32Array): ArrayBuffer {
  const dataBytes = samples.length * 2
  const buffer = new ArrayBuffer(44 + dataBytes)
  const view = new DataView(buffer)

  writeAscii(view, 0, 'RIFF')
  view.setUint32(4, 36 + dataBytes, true)
  writeAscii(view, 8, 'WAVE')
  writeAscii(view, 12, 'fmt ')
  view.setUint32(16, 16, true)
  view.setUint16(20, 1, true)
  view.setUint16(22, 1, true)
  view.setUint32(24, TARGET_SAMPLE_RATE, true)
  view.setUint32(28, TARGET_SAMPLE_RATE * 2, true)
  view.setUint16(32, 2, true)
  view.setUint16(34, 16, true)
  writeAscii(view, 36, 'data')
  view.setUint32(40, dataBytes, true)

  for (let index = 0; index < samples.length; index += 1) {
    const bounded = Math.max(-1, Math.min(1, samples[index] ?? 0))
    view.setInt16(
      44 + index * 2,
      bounded < 0 ? bounded * 0x8000 : bounded * 0x7fff,
      true,
    )
  }

  return buffer
}

function writeAscii(view: DataView, offset: number, value: string): void {
  for (let index = 0; index < value.length; index += 1) {
    view.setUint8(offset + index, value.charCodeAt(index))
  }
}
