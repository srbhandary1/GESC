import { describe, expect, it } from 'vitest'
import { encodeAudiblePcmCapture } from './encodeAudiblePcmCapture'
import { PCM_WAV_SAMPLE_RATE } from '../shared/pcmWav'
import { audioToPcmWav } from '../conversation/audioToPcmWav'

describe('encodeAudiblePcmCapture', () => {
  it('returns a WAV blob when PCM has audible speech', () => {
    const pcm = new Float32Array(PCM_WAV_SAMPLE_RATE)
    pcm[16] = 0.4

    const capture = encodeAudiblePcmCapture(
      pcm,
      PCM_WAV_SAMPLE_RATE,
      'audio/webm',
      1_000,
    )

    expect(capture).not.toBeNull()
    expect(capture?.blob.type).toBe('audio/wav')
    expect(capture?.blob.size).toBeGreaterThan(44)
    expect(capture?.durationMs).toBe(1_000)
  })

  it('returns null for silence so the backup recorder can still be used', () => {
    const pcm = new Float32Array(2_000)

    expect(
      encodeAudiblePcmCapture(pcm, PCM_WAV_SAMPLE_RATE, 'audio/webm', 250),
    ).toBeNull()
  })

  it('trims a browser PCM buffer overshoot to the live voice limit', async () => {
    const pcm = new Float32Array(PCM_WAV_SAMPLE_RATE * 26)
    pcm.fill(0.1)
    const capture = encodeAudiblePcmCapture(
      pcm,
      PCM_WAV_SAMPLE_RATE,
      'audio/webm',
      26_000,
    )

    expect(capture).not.toBeNull()
    const wav = await audioToPcmWav(capture!, 25_000)
    const view = new DataView(await wav.arrayBuffer())

    expect(view.getUint32(40, true)).toBe(PCM_WAV_SAMPLE_RATE * 25 * 2)
    expect(wav.size).toBe(44 + PCM_WAV_SAMPLE_RATE * 25 * 2)
  })
})
