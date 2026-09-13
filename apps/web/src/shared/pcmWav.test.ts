import { describe, expect, it } from 'vitest'
import {
  concatenateFloat32,
  encodeMonoPcmWav,
  hasAudibleSpeech,
  peakAmplitude,
  resampleMono,
} from './pcmWav'

describe('pcmWav', () => {
  it('encodes a RIFF PCM WAV header at 16 kHz mono', () => {
    const samples = new Float32Array([0, 0.5, -0.5, 0])
    const encoded = encodeMonoPcmWav(samples)
    const view = new DataView(encoded)

    expect(ascii(view, 0, 4)).toBe('RIFF')
    expect(ascii(view, 8, 4)).toBe('WAVE')
    expect(view.getUint16(20, true)).toBe(1)
    expect(view.getUint16(22, true)).toBe(1)
    expect(view.getUint32(24, true)).toBe(16_000)
    expect(encoded.byteLength).toBe(44 + samples.length * 2)
  })

  it('rejects near-silent buffers and accepts a spoken peak', () => {
    expect(hasAudibleSpeech(new Float32Array(320))).toBe(false)
    expect(peakAmplitude(new Float32Array([0, 0.02, -0.01]))).toBeCloseTo(0.02)
    expect(hasAudibleSpeech(new Float32Array([0, 0.02, -0.01]))).toBe(true)
  })

  it('concatenates and resamples without changing duration', () => {
    const joined = concatenateFloat32([
      new Float32Array([1, 0]),
      new Float32Array([0, -1]),
    ])
    expect(Array.from(joined)).toEqual([1, 0, 0, -1])

    const resampled = resampleMono(joined, 8_000, 16_000)
    expect(resampled.length).toBe(8)
  })
})

function ascii(view: DataView, offset: number, length: number): string {
  return String.fromCharCode(
    ...Array.from({ length }, (_, index) => view.getUint8(offset + index)),
  )
}
