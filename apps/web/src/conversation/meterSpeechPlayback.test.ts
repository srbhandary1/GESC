import { describe, expect, it, vi } from 'vitest'
import { meterSpeechPlayback } from './meterSpeechPlayback'

vi.mock('./WebConversationJourneyApi', () => ({ defaultConversationJourneyApi: { recordPlayback: vi.fn() } }))

describe('actual playback metering', () => {
  it('starts at zero, reports partial playback on stop and never bills generation', async () => {
    const report = vi.fn(async (input: { played_ms: number }) => ({ played_ms: input.played_ms, charged_seconds: Math.ceil(input.played_ms / 1000), allowed_ms: 10000, remaining_seconds: 60 }))
    const meter = await meterSpeechPlayback('scoped-grant', undefined, report)
    expect(report.mock.calls.map(([input]) => input.played_ms)).toEqual([0])
    meter.update(2300)
    await meter.finish()
    expect(report.mock.calls.map(([input]) => input.played_ms)).toEqual([0, 2300])
  })

  it('caps playback at the remaining balance and aborts on accounting failure', async () => {
    const report = vi.fn(async () => ({ played_ms: 0, charged_seconds: 0, allowed_ms: 2000, remaining_seconds: 2 }))
    const meter = await meterSpeechPlayback('grant', undefined, report)
    meter.update(2600)
    expect(meter.signal.aborted).toBe(true)
    await meter.finish()
    expect(report).toHaveBeenLastCalledWith(expect.objectContaining({ played_ms: 2000 }))
    const failing = vi.fn().mockResolvedValueOnce({ played_ms: 0, charged_seconds: 0, allowed_ms: 10000, remaining_seconds: 60 }).mockRejectedValue(new Error('offline'))
    const unavailable = await meterSpeechPlayback('grant', undefined, failing)
    unavailable.update(5100)
    await unavailable.finish()
    expect(unavailable.signal.aborted).toBe(true)
  })

  it('does not send a charge for audio with no provider meter grant', async () => {
    const report = vi.fn()
    const meter = await meterSpeechPlayback(undefined, undefined, report)
    meter.update(3000)
    await meter.finish()
    expect(report).not.toHaveBeenCalled()
  })
})
