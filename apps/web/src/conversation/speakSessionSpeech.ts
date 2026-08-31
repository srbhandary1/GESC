import type {
  ConversationAnimationPayload,
  ConversationSpeechResult,
  SynthesizeConversationSpeechRequest,
} from '@global-english/contracts'
import { speakDemoText } from '../demo/demoTts'
import { createClientId } from '../shared/createClientId'
import type { ConversationJourneyApi } from './ConversationJourneyApi'
import { playBase64Audio } from './playBase64Audio'

const MAXIMUM_SPEECH_CHARACTERS = 400

export async function speakSessionSpeech(options: {
  readonly api: ConversationJourneyApi
  readonly demoMode?: boolean
  readonly locale: string
  readonly purpose: SynthesizeConversationSpeechRequest['purpose']
  readonly sessionId: string
  readonly text: string
  readonly onAnimationPayload?: (
    payload: ConversationAnimationPayload | null,
  ) => void
  readonly onAudioElement?: (audio: HTMLAudioElement) => void
}): Promise<void> {
  const trimmed = options.text.trim()
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
    options.onAnimationPayload?.(speech.animation_payload ?? null)
    await playSpeechAudio(
      speech,
      options.purpose,
      options.onAudioElement,
    )
  }
}

async function playSpeechAudio(
  speech: ConversationSpeechResult,
  purpose: SynthesizeConversationSpeechRequest['purpose'],
  onAudioElement?: (audio: HTMLAudioElement) => void,
): Promise<void> {
  const audio =
    purpose === 'guidance_l1'
      ? speech.guidance_audio_base64
      : speech.model_audio_base64
  const mimeType =
    purpose === 'guidance_l1'
      ? speech.guidance_audio_mime_type
      : speech.model_audio_mime_type

  if (typeof audio !== 'string' || audio === '') {
    return
  }

  await playBase64Audio(audio, mimeType ?? 'audio/wav', {
    onElement: onAudioElement,
  })
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
