import type { CapturedAudio } from './BrowserAudioRecorder'
import type { LocalRecordingMetadata } from './LearnerJourneyApi'

const allowedCaptureMimeTypes = new Set<LocalRecordingMetadata['mime_type']>([
  'audio/webm',
  'audio/webm;codecs=opus',
  'audio/ogg',
  'audio/ogg;codecs=opus',
  'audio/mp4',
])

export function canonicalCaptureMimeType(
  raw: string,
): LocalRecordingMetadata['mime_type'] | null {
  const value = raw.trim().toLowerCase()
  if (allowedCaptureMimeTypes.has(value as LocalRecordingMetadata['mime_type'])) {
    return value as LocalRecordingMetadata['mime_type']
  }

  const base = value.split(';')[0]?.trim() ?? ''
  const hasOpus = /codec[s]?\s*=\s*"?opus"?/u.test(value)

  if (base === 'audio/webm') {
    return hasOpus ? 'audio/webm;codecs=opus' : 'audio/webm'
  }

  if (base === 'audio/ogg') {
    return hasOpus ? 'audio/ogg;codecs=opus' : 'audio/ogg'
  }

  if (base === 'audio/mp4' || base === 'audio/m4a' || base === 'audio/x-m4a') {
    return 'audio/mp4'
  }

  return null
}

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
    canonicalCaptureMimeType(audio.mimeType) !== null
  )
}
