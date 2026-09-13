export const PCM_WAV_SAMPLE_RATE = 16_000

const SILENCE_PEAK_THRESHOLD = 0.004

export function concatenateFloat32(
  chunks: readonly Float32Array[],
): Float32Array {
  let length = 0
  for (const chunk of chunks) {
    length += chunk.length
  }

  const samples = new Float32Array(length)
  let offset = 0
  for (const chunk of chunks) {
    samples.set(chunk, offset)
    offset += chunk.length
  }

  return samples
}

export function peakAmplitude(samples: Float32Array): number {
  let peak = 0
  for (let index = 0; index < samples.length; index += 1) {
    const magnitude = Math.abs(samples[index] ?? 0)
    if (magnitude > peak) {
      peak = magnitude
    }
  }

  return peak
}

export function hasAudibleSpeech(samples: Float32Array): boolean {
  return peakAmplitude(samples) >= SILENCE_PEAK_THRESHOLD
}

export function resampleMono(
  samples: Float32Array,
  sourceSampleRate: number,
  targetSampleRate = PCM_WAV_SAMPLE_RATE,
): Float32Array {
  if (sourceSampleRate === targetSampleRate) {
    return samples
  }

  const duration = samples.length / sourceSampleRate
  const targetLength = Math.max(1, Math.round(duration * targetSampleRate))
  const resampled = new Float32Array(targetLength)
  const sourceLength = samples.length

  for (let targetIndex = 0; targetIndex < targetLength; targetIndex += 1) {
    const sourcePosition = (targetIndex * sourceSampleRate) / targetSampleRate
    const leftIndex = Math.min(sourceLength - 1, Math.floor(sourcePosition))
    const rightIndex = Math.min(sourceLength - 1, leftIndex + 1)
    const fraction = sourcePosition - leftIndex
    const left = samples[leftIndex] ?? 0
    const right = samples[rightIndex] ?? 0
    resampled[targetIndex] = left * (1 - fraction) + right * fraction
  }

  return resampled
}

export function encodeMonoPcmWav(
  samples: Float32Array,
  sampleRate = PCM_WAV_SAMPLE_RATE,
): ArrayBuffer {
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
  view.setUint32(24, sampleRate, true)
  view.setUint32(28, sampleRate * 2, true)
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
