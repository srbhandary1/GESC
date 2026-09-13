import { Capacitor } from '@capacitor/core'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { speechLatencySample } from './speechLatencyTelemetry'

afterEach(() => {
  vi.restoreAllMocks()
})

describe('speech latency telemetry', () => {
  it('contains durations and coarse runtime fields only', () => {
    vi.spyOn(Capacitor, 'isNativePlatform').mockReturnValue(false)
    const sample = speechLatencySample(
      {
        durationsMs: {
          record_stop_to_ack_ms: 40,
          record_stop_to_wav_ready_ms: 100,
          wav_ready_to_upload_done_ms: 200,
          upload_done_to_transcript_ready_ms: 500,
          transcript_ready_to_turn_json_ready_ms: 1000,
          turn_json_ready_to_first_audio_ms: 400,
          release_to_first_audio_ms: 2200,
        },
        marked: [
          'record_stop',
          'wav_ready',
          'upload_done',
          'transcript_ready',
          'turn_json_ready',
          'first_audio',
        ],
      },
      'started',
    )

    expect(sample).toEqual({
      client_runtime: 'web',
      network_class: 'unknown',
      playback_outcome: 'started',
      release_to_ack_ms: 40,
      release_to_first_signal_ms: 1800,
      release_to_first_audio_ms: 2200,
      turn_json_to_first_audio_ms: 400,
    })
    expect(JSON.stringify(sample)).not.toMatch(
      /audio_base64|transcript_text|assistant_reply|prompt_body|user_agent/iu,
    )
  })

  it('reports android without device fingerprinting', () => {
    vi.spyOn(Capacitor, 'isNativePlatform').mockReturnValue(true)
    vi.spyOn(Capacitor, 'getPlatform').mockReturnValue('android')

    expect(
      speechLatencySample(
        {
          durationsMs: {
            record_stop_to_ack_ms: null,
            record_stop_to_wav_ready_ms: null,
            wav_ready_to_upload_done_ms: null,
            upload_done_to_transcript_ready_ms: null,
            transcript_ready_to_turn_json_ready_ms: null,
            turn_json_ready_to_first_audio_ms: null,
            release_to_first_audio_ms: null,
          },
          marked: ['record_stop'],
        },
        'unavailable',
      ),
    ).toMatchObject({
      client_runtime: 'android',
      playback_outcome: 'unavailable',
      release_to_first_signal_ms: null,
    })
  })
})
