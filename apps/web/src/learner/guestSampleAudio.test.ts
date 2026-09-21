import { describe, expect, it } from 'vitest'
import { guestSampleAudioSrc } from './guestSampleAudio'

describe('guestSampleAudioSrc', () => {
  it('maps each Level 12 demo phrase to a static WAV', () => {
    expect(guestSampleAudioSrc('I would like black tea.')).toBe(
      '/guest-sample/level-12-i-would-like-black-tea.wav',
    )
    expect(guestSampleAudioSrc('Would you like some water?')).toBe(
      '/guest-sample/level-12-would-you-like-some-water.wav',
    )
    expect(guestSampleAudioSrc('Would you pass the sugar, please?')).toBe(
      '/guest-sample/level-12-would-you-pass-the-sugar.wav',
    )
  })

  it('does not invent a path for unknown lines', () => {
    expect(guestSampleAudioSrc('Hello')).toBeNull()
  })
})
