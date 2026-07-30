import type { CapturedAudio } from './BrowserAudioRecorder'
import type { LocalRecordingMetadata } from './LearnerJourneyApi'

const allowedCaptureMimeTypes = new Set<LocalRecordingMetadata['mime_type']>([
  'audio/webm',
  'audio/webm;codecs=opus',
  'audio/ogg',
  'audio/ogg;codecs=opus',
  'audio/mp4',
])

export function isValidLocalCapture(
  audio: CapturedAudio,
): audio is CapturedAudio & {
  readonly mimeType: LocalRecordingMetadata['mime_type']
} {
  return (
    audio.blob.size >= 128 &&
    audio.blob.size <= 10_000_000 &&
    Number.isInteger(audio.durationMs) &&
    audio.durationMs >= 250 &&
    audio.durationMs <= 120_000 &&
    allowedCaptureMimeTypes.has(
      audio.mimeType as LocalRecordingMetadata['mime_type'],
    )
  )
}
