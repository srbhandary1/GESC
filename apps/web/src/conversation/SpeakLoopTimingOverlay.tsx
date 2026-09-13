import { useEffect, useState } from 'react'
import {
  speakLoopHistory,
  subscribeSpeakLoop,
  type SpeakLoopSnapshot,
} from './speakLoopTimings'

export function SpeakLoopTimingOverlay() {
  const [latest, setLatest] = useState<SpeakLoopSnapshot | null>(
    () => speakLoopHistory().at(-1) ?? null,
  )

  useEffect(() => subscribeSpeakLoop(setLatest), [])

  if (!import.meta.env.DEV || latest === null) {
    return null
  }

  const durations = latest.durationsMs

  return (
    <aside
      aria-hidden="true"
      className="speak-loop-timings"
      data-speak-loop-timings=""
    >
      <p>speak-loop ms</p>
      <ul>
        <li>stop→wav {formatMs(durations.record_stop_to_wav_ready_ms)}</li>
        <li>wav→upload {formatMs(durations.wav_ready_to_upload_done_ms)}</li>
        <li>upload→transcript {formatMs(durations.upload_done_to_transcript_ready_ms)}</li>
        <li>transcript→turn {formatMs(durations.transcript_ready_to_turn_json_ready_ms)}</li>
        <li>turn→audio {formatMs(durations.turn_json_ready_to_first_audio_ms)}</li>
        <li>release→audio {formatMs(durations.release_to_first_audio_ms)}</li>
      </ul>
    </aside>
  )
}

function formatMs(value: number | null): string {
  return value === null ? '—' : String(value)
}
