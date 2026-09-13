import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  abandonSpeakLoop,
  beginSpeakLoop,
  completeSpeakLoop,
  markSpeakLoop,
  resetSpeakLoopHistory,
  speakLoopHistory,
} from './speakLoopTimings'

afterEach(() => {
  resetSpeakLoopHistory()
  vi.restoreAllMocks()
})

describe('speak-loop timings', () => {
  it('logs only duration fields and never learner content', () => {
    const debug = vi.spyOn(console, 'debug').mockImplementation(() => undefined)

    beginSpeakLoop()
    markSpeakLoop('wav_ready')
    markSpeakLoop('upload_done')
    markSpeakLoop('transcript_ready')
    markSpeakLoop('turn_json_ready')
    markSpeakLoop('first_audio')

    expect(debug).toHaveBeenCalledOnce()
    const payload = debug.mock.calls[0]?.[1] as Record<string, unknown>
    expect(debug.mock.calls[0]?.[0]).toBe('[speak-loop]')
    expect(Object.keys(payload).sort()).toEqual([
      'record_stop_to_ack_ms',
      'record_stop_to_wav_ready_ms',
      'release_to_first_audio_ms',
      'transcript_ready_to_turn_json_ready_ms',
      'turn_json_ready_to_first_audio_ms',
      'upload_done_to_transcript_ready_ms',
      'wav_ready_to_upload_done_ms',
    ])
    for (const value of Object.values(payload)) {
      expect(value === null || typeof value === 'number').toBe(true)
    }
    expect(speakLoopHistory()).toHaveLength(1)
    expect(speakLoopHistory()[0]?.durationsMs.release_to_first_audio_ms).toBeTypeOf(
      'number',
    )
  })

  it('abandons an in-flight loop without recording a snapshot', () => {
    beginSpeakLoop()
    markSpeakLoop('wav_ready')
    abandonSpeakLoop()
    expect(completeSpeakLoop()).toBeNull()
    expect(speakLoopHistory()).toHaveLength(0)
  })
})
