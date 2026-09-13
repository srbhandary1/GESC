export type SpeakLoopPhase =
  | 'waiting'
  | 'listening'
  | 'processing'
  | 'preparing'
  | 'speaking'

export const SPEAK_LOOP_STAGES = [
  'record_stop',
  'wav_ready',
  'upload_done',
  'transcript_ready',
  'ack',
  'turn_json_ready',
  'first_audio',
] as const

export type SpeakLoopStage = (typeof SPEAK_LOOP_STAGES)[number]

export interface SpeakLoopDurationsMs {
  readonly record_stop_to_ack_ms: number | null
  readonly record_stop_to_wav_ready_ms: number | null
  readonly wav_ready_to_upload_done_ms: number | null
  readonly upload_done_to_transcript_ready_ms: number | null
  readonly transcript_ready_to_turn_json_ready_ms: number | null
  readonly turn_json_ready_to_first_audio_ms: number | null
  readonly release_to_first_audio_ms: number | null
}

export interface SpeakLoopSnapshot {
  readonly durationsMs: SpeakLoopDurationsMs
  readonly marked: readonly SpeakLoopStage[]
}

const HISTORY_CAP = 50
const history: SpeakLoopSnapshot[] = []
const listeners = new Set<(snapshot: SpeakLoopSnapshot) => void>()

let activeMarks: Partial<Record<SpeakLoopStage, number>> | null = null

export function beginSpeakLoop(): void {
  activeMarks = { record_stop: performance.now() }
}

export function markSpeakLoop(stage: SpeakLoopStage): SpeakLoopSnapshot | null {
  if (activeMarks === null) {
    return null
  }

  if (activeMarks[stage] !== undefined) {
    return null
  }

  activeMarks[stage] = performance.now()

  if (stage === 'first_audio') {
    return completeSpeakLoop()
  }

  return null
}

export function abandonSpeakLoop(): void {
  activeMarks = null
}

export function completeSpeakLoop(): SpeakLoopSnapshot | null {
  if (activeMarks === null) {
    return null
  }

  const snapshot = snapshotFromMarks(activeMarks)
  activeMarks = null
  history.push(snapshot)
  if (history.length > HISTORY_CAP) {
    history.shift()
  }

  console.debug('[speak-loop]', snapshot.durationsMs)
  for (const listener of listeners) {
    listener(snapshot)
  }

  return snapshot
}

export function peekSpeakLoop(): SpeakLoopSnapshot | null {
  if (activeMarks === null) {
    return null
  }

  return snapshotFromMarks(activeMarks)
}

export function speakLoopHistory(): readonly SpeakLoopSnapshot[] {
  return history
}

export function subscribeSpeakLoop(
  listener: (snapshot: SpeakLoopSnapshot) => void,
): () => void {
  listeners.add(listener)

  return () => {
    listeners.delete(listener)
  }
}

export function resetSpeakLoopHistory(): void {
  history.length = 0
  activeMarks = null
}

function snapshotFromMarks(
  marks: Partial<Record<SpeakLoopStage, number>>,
): SpeakLoopSnapshot {
  const delta = (
    from: SpeakLoopStage,
    to: SpeakLoopStage,
  ): number | null => {
    const start = marks[from]
    const end = marks[to]
    if (start === undefined || end === undefined) {
      return null
    }

    return Math.max(0, Math.round(end - start))
  }

  return {
    durationsMs: {
      record_stop_to_ack_ms: delta('record_stop', 'ack'),
      record_stop_to_wav_ready_ms: delta('record_stop', 'wav_ready'),
      wav_ready_to_upload_done_ms: delta('wav_ready', 'upload_done'),
      upload_done_to_transcript_ready_ms: delta(
        'upload_done',
        'transcript_ready',
      ),
      transcript_ready_to_turn_json_ready_ms: delta(
        'transcript_ready',
        'turn_json_ready',
      ),
      turn_json_ready_to_first_audio_ms: delta(
        'turn_json_ready',
        'first_audio',
      ),
      release_to_first_audio_ms: delta('record_stop', 'first_audio'),
    },
    marked: SPEAK_LOOP_STAGES.filter((stage) => marks[stage] !== undefined),
  }
}
