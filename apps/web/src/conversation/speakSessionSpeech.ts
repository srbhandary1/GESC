import type {
  ConversationSpeechResult,
  SynthesizeConversationSpeechRequest,
} from '@global-english/contracts'
import { speakDemoText } from '../demo/demoTts'
import { createClientId } from '../shared/createClientId'
import {
  base64AudioDurationMs,
  estimateVisemeTimeline,
} from '../tutor/avatar/clientVisemeTimeline'
import type { AvatarPlaybackState } from '../tutor/avatar/types'
import type { ConversationJourneyApi } from './ConversationJourneyApi'
import { playBase64Audio } from './playBase64Audio'
import { playPrivateSpeechStream } from './playPrivateSpeechStream'
import { spokenGrammarNotation } from './spokenGrammarNotation'

const MAXIMUM_SPEECH_CHARACTERS = 400

export async function speakSessionSpeech(options: {
  readonly api: ConversationJourneyApi
  readonly demoMode?: boolean
  readonly grammarDrill?: boolean
  readonly locale: string
  readonly purpose: SynthesizeConversationSpeechRequest['purpose']
  readonly sessionId: string
  readonly text: string
  readonly onPlaybackChange?: (playback: AvatarPlaybackState | null) => void
  readonly onPlaybackStarted?: () => void
}): Promise<void> {
  const trimmed = spokenGrammarNotation(
    options.text.trim(),
    options.grammarDrill === true && options.purpose === 'model_en',
  )
  if (trimmed === '') {
    return
  }

  if (options.demoMode === true) {
    await speakDemoText(trimmed, options.locale)
    return
  }

  if (options.api.synthesizeSpeech === undefined) {
    return
  }

  const chunks =
    trimmed.length <= MAXIMUM_SPEECH_CHARACTERS
      ? [trimmed]
      : splitSpeechChunks(trimmed)

  for (const chunk of chunks) {
    const speech = await options.api.synthesizeSpeech(
      options.sessionId,
      { purpose: options.purpose, text: chunk },
      createClientId(),
    )
    await playConversationSpeechResult({
      onPlaybackChange: options.onPlaybackChange,
      onPlaybackStarted: options.onPlaybackStarted,
      purpose: options.purpose,
      speech,
      text: chunk,
    })
  }
}

export async function playConversationSpeechResult(options: {
  readonly onPlaybackChange?: (playback: AvatarPlaybackState | null) => void
  readonly onPlaybackStarted?: () => void
  readonly purpose: SynthesizeConversationSpeechRequest['purpose']
  readonly signal?: AbortSignal
  readonly speech: ConversationSpeechResult
  readonly text?: string | null
}): Promise<void> {
  const { purpose, speech } = options
  const streamPath =
    purpose === 'guidance_l1'
      ? speech.guidance_audio_stream_path
      : speech.model_audio_stream_path
  const audio =
    purpose === 'guidance_l1'
      ? speech.guidance_audio_base64
      : speech.model_audio_base64
  const mimeType =
    purpose === 'guidance_l1'
      ? speech.guidance_audio_mime_type
      : speech.model_audio_mime_type

  if (
    (typeof streamPath !== 'string' || streamPath === '') &&
    (typeof audio !== 'string' || audio === '')
  ) {
    return
  }

  const timeline =
    options.onPlaybackChange === undefined
      ? null
      : ((purpose === 'guidance_l1'
          ? speech.guidance_avatar_viseme_timeline
          : speech.model_avatar_viseme_timeline) ??
        (typeof audio === 'string' && audio !== ''
          ? await estimatePlaybackTimeline(
              options.text,
              audio,
              mimeType ?? 'audio/wav',
            )
          : null))

  if (timeline !== null) {
    options.onPlaybackChange?.({ timeline, timeMs: 0 })
  }

  try {
    const playbackOptions =
      options.onPlaybackChange === undefined &&
      options.onPlaybackStarted === undefined &&
      options.signal === undefined
        ? undefined
        : {
            signal: options.signal,
            onStarted: options.onPlaybackStarted,
            onTimeUpdate: (timeMs: number) => {
              if (timeline !== null) {
                options.onPlaybackChange?.({ timeline, timeMs })
              }
            },
          }

    if (typeof streamPath === 'string' && streamPath !== '') {
      await playPrivateSpeechStream(
        streamPath,
        mimeType ?? 'audio/wav',
        playbackOptions,
      )
    } else if (playbackOptions === undefined) {
      await playBase64Audio(audio as string, mimeType ?? 'audio/wav')
    } else {
      await playBase64Audio(
        audio as string,
        mimeType ?? 'audio/wav',
        playbackOptions,
      )
    }
  } finally {
    options.onPlaybackChange?.(null)
  }
}

async function estimatePlaybackTimeline(
  text: string | null | undefined,
  base64: string,
  mimeType: string,
) {
  const trimmed = text?.trim() ?? ''
  if (trimmed === '') {
    return null
  }

  const durationMs =
    (await base64AudioDurationMs(base64, mimeType)) ??
    Math.max(320, Math.round(trimmed.length * 42))

  return estimateVisemeTimeline(trimmed, durationMs)
}

function splitSpeechChunks(text: string): string[] {
  const sentences = text.split(/(?<=[.!?])\s+/u).filter((part) => part !== '')
  const chunks: string[] = []
  let current = ''

  for (const sentence of sentences) {
    if (sentence.length > MAXIMUM_SPEECH_CHARACTERS) {
      if (current !== '') {
        chunks.push(current)
        current = ''
      }
      chunks.push(sentence.slice(0, MAXIMUM_SPEECH_CHARACTERS))
      continue
    }

    const next = current === '' ? sentence : `${current} ${sentence}`
    if (next.length > MAXIMUM_SPEECH_CHARACTERS) {
      chunks.push(current)
      current = sentence
    } else {
      current = next
    }
  }

  if (current !== '') {
    chunks.push(current)
  }

  return chunks
}
