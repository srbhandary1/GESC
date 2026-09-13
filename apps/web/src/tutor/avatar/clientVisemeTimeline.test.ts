import { describe, expect, it } from 'vitest'
import {
  estimateVisemeTimeline,
  wavDurationMsFromBytes,
} from './clientVisemeTimeline'

describe('clientVisemeTimeline', () => {
  it('builds a timeline aligned to audio duration', () => {
    const timeline = estimateVisemeTimeline('Hello', 500)
    expect(timeline).not.toBeNull()
    expect(timeline?.source).toBe('text-duration-estimator-v1')
    expect(timeline?.duration_ms).toBe(500)
    expect(timeline?.frames[0]).toEqual({ offset_ms: 0, viseme_id: 12 })
    expect(timeline?.frames.at(-1)?.viseme_id).toBe(0)
  })

  it('reads wav duration from a pcm header', () => {
    const bytes = new Uint8Array(44)
    const write = (offset: number, value: string) => {
      for (let index = 0; index < value.length; index += 1) {
        bytes[offset + index] = value.charCodeAt(index)
      }
    }
    write(0, 'RIFF')
    write(8, 'WAVE')
    write(12, 'fmt ')
    bytes[16] = 16
    bytes[22] = 1
    bytes[24] = 0x80
    bytes[25] = 0x3e
    bytes[34] = 16
    write(36, 'data')
    bytes[40] = 0x80
    bytes[41] = 0x3e
    bytes[42] = 0x00
    bytes[43] = 0x00

    expect(wavDurationMsFromBytes(bytes)).toBe(500)
  })
})
