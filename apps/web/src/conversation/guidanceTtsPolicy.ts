/**
 * Confidence Conversation guidance TTS voice map (ADR-0011).
 * Mirrors apps/api GuidanceTtsPolicy. Text remains the primary surface.
 */

export type GuidanceTtsPurpose = 'model_en' | 'guidance_l1'

export const GUIDANCE_TTS_VOICES = {
  'en-IN': 'en-IN-NeerjaNeural',
  'hi-IN': 'hi-IN-SwaraNeural',
  'kn-IN': 'kn-IN-SapnaNeural',
  'ml-IN': 'ml-IN-SobhanaNeural',
  'ta-IN': 'ta-IN-PallaviNeural',
  'te-IN': 'te-IN-ShrutiNeural',
} as const

export type GuidanceTtsLocale = keyof typeof GUIDANCE_TTS_VOICES

export function guidanceVoiceFor(
  purpose: GuidanceTtsPurpose,
  uiLocale: string,
): string {
  if (purpose === 'model_en') {
    return GUIDANCE_TTS_VOICES['en-IN']
  }

  if (!(uiLocale in GUIDANCE_TTS_VOICES)) {
    throw new Error('Unsupported UI locale for guidance TTS.')
  }

  return GUIDANCE_TTS_VOICES[uiLocale as GuidanceTtsLocale]
}

export const CONFIDENCE_ALLOWS_HUMAN_LESSON_RECORDING = false
export const CONFIDENCE_TEXT_FIRST_FALLBACK_REQUIRED = true
