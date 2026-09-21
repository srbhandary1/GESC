/**
 * Unsigned guest-home Listen uses one static WAV per demo phrase.
 * Do not call /demo/tts or Azure from this surface.
 */

export const GUEST_SAMPLE_AUDIO_BY_PHRASE: Readonly<Record<string, string>> = {
  'I would like black tea.':
    '/guest-sample/level-12-i-would-like-black-tea.wav',
  'Would you like some water?':
    '/guest-sample/level-12-would-you-like-some-water.wav',
  'Would you pass the sugar, please?':
    '/guest-sample/level-12-would-you-pass-the-sugar.wav',
}

export function guestSampleAudioSrc(phrase: string): string | null {
  return GUEST_SAMPLE_AUDIO_BY_PHRASE[phrase] ?? null
}
