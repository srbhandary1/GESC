import type { CapturedAudio } from './BrowserAudioRecorder'
import {
  encodeMonoPcmWav,
  hasAudibleSpeech,
  PCM_WAV_SAMPLE_RATE,
  resampleMono,
} from '../shared/pcmWav'

export function encodeAudiblePcmCapture(
  pcm: Float32Array,
  sampleRate: number,
  receiptMime: string,
  durationFallbackMs: number,
): CapturedAudio | null {
  if (pcm.length === 0 || !hasAudibleSpeech(pcm)) {
    return null
  }

  const durationMs = Math.max(
    1,
    Math.round((pcm.length / sampleRate) * 1000) || durationFallbackMs,
  )
  const wav = encodeMonoPcmWav(
    resampleMono(pcm, sampleRate, PCM_WAV_SAMPLE_RATE),
  )

  return {
    blob: new Blob([wav], { type: 'audio/wav' }),
    durationMs,
    mimeType: receiptMime,
  }
}
