import { describe, expect, it } from 'vitest'
import { encodeMonoPcmWav, PCM_WAV_SAMPLE_RATE } from '../shared/pcmWav'
import { decodeBase64ToBytes } from './playBase64Audio'

const SAMPLES = 21

function percentile(sorted: readonly number[], p: number): number {
  const index = Math.min(
    sorted.length - 1,
    Math.max(0, Math.ceil((p / 100) * sorted.length) - 1),
  )
  return sorted[index] ?? 0
}

function wait(ms: number): Promise<void> {
  return new Promise((resolve) => {
    setTimeout(resolve, ms)
  })
}

function bytesToBase64(bytes: Uint8Array): string {
  let binary = ''
  const block = 0x8000
  for (let offset = 0; offset < bytes.length; offset += block) {
    binary += String.fromCharCode(
      ...bytes.subarray(offset, offset + block),
    )
  }
  return btoa(binary)
}

describe('speak-loop client latency on this machine', () => {
  it('reports p50/p95 for current vs overlapped release-to-first-audio', async () => {
    const pcm = new Float32Array(PCM_WAV_SAMPLE_RATE)
    for (let index = 0; index < pcm.length; index += 1) {
      pcm[index] = Math.sin((index / PCM_WAV_SAMPLE_RATE) * 440 * Math.PI * 2) * 0.2
    }

    const backupRecorderMs = 80
    const eventsRttMs = 40
    const current: number[] = []
    const achieved: number[] = []

    for (let sample = 0; sample < SAMPLES; sample += 1) {
      const currentStart = performance.now()
      await wait(backupRecorderMs)
      const wav = encodeMonoPcmWav(pcm)
      await wait(eventsRttMs)
      decodeBase64ToBytes(bytesToBase64(new Uint8Array(wav)))
      current.push(performance.now() - currentStart)

      const achievedStart = performance.now()
      const wavFast = encodeMonoPcmWav(pcm)
      const decodeDone = Promise.resolve(
        decodeBase64ToBytes(bytesToBase64(new Uint8Array(wavFast))),
      )
      await Promise.all([wait(eventsRttMs), decodeDone])
      achieved.push(performance.now() - achievedStart)
    }

    current.sort((left, right) => left - right)
    achieved.sort((left, right) => left - right)

    const report = {
      current_p50_ms: Math.round(percentile(current, 50)),
      current_p95_ms: Math.round(percentile(current, 95)),
      achieved_p50_ms: Math.round(percentile(achieved, 50)),
      achieved_p95_ms: Math.round(percentile(achieved, 95)),
      backup_recorder_wait_ms: backupRecorderMs,
      events_rtt_ms: eventsRttMs,
      samples: SAMPLES,
    }

    console.info('[speak-loop-latency]', report)

    expect(report.achieved_p50_ms).toBeLessThan(report.current_p50_ms)
    expect(report.achieved_p95_ms).toBeLessThan(report.current_p95_ms)
    expect(report.achieved_p50_ms).toBeGreaterThanOrEqual(eventsRttMs - 5)
  })
})
