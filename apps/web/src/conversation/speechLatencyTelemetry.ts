import { Capacitor } from '@capacitor/core'
import type { SpeechLatencySampleRequest } from '@global-english/contracts'
import type { SpeakLoopSnapshot } from './speakLoopTimings'

type NetworkInformation = {
  readonly effectiveType?: string
  readonly type?: string
}

export function speechLatencySample(
  snapshot: SpeakLoopSnapshot,
  playbackOutcome: SpeechLatencySampleRequest['playback_outcome'],
): SpeechLatencySampleRequest {
  return {
    client_runtime:
      Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android'
        ? 'android'
        : 'web',
    network_class: networkClass(),
    playback_outcome: playbackOutcome,
    release_to_ack_ms: snapshot.durationsMs.record_stop_to_ack_ms,
    release_to_first_signal_ms: sumWhenComplete([
      snapshot.durationsMs.record_stop_to_wav_ready_ms,
      snapshot.durationsMs.wav_ready_to_upload_done_ms,
      snapshot.durationsMs.upload_done_to_transcript_ready_ms,
      snapshot.durationsMs.transcript_ready_to_turn_json_ready_ms,
    ]),
    release_to_first_audio_ms:
      snapshot.durationsMs.release_to_first_audio_ms,
    turn_json_to_first_audio_ms:
      snapshot.durationsMs.turn_json_ready_to_first_audio_ms,
  }
}

function networkClass(): SpeechLatencySampleRequest['network_class'] {
  if (typeof navigator === 'undefined') {
    return 'unknown'
  }
  const connection = (
    navigator as Navigator & { readonly connection?: NetworkInformation }
  ).connection
  const type = connection?.type?.toLowerCase()
  if (type === 'wifi' || type === 'ethernet' || type === 'cellular') {
    return type
  }
  if (connection?.effectiveType !== undefined) {
    return 'cellular'
  }
  return 'unknown'
}

function sumWhenComplete(values: readonly (number | null)[]): number | null {
  if (values.some((value) => value === null)) {
    return null
  }

  return values.reduce<number>((sum, value) => sum + (value ?? 0), 0)
}
