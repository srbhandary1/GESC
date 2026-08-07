import { describe, expect, it } from 'vitest'
import {
  CONFIDENCE_ALLOWS_HUMAN_LESSON_RECORDING,
  CONFIDENCE_TEXT_FIRST_FALLBACK_REQUIRED,
  guidanceVoiceFor,
} from './guidanceTtsPolicy'

describe('guidanceTtsPolicy', () => {
  it('uses en-IN neural voice for English model lines', () => {
    expect(guidanceVoiceFor('model_en', 'kn-IN')).toBe('en-IN-NeerjaNeural')
  })

  it('maps Phase 1 L1 guidance voices', () => {
    expect(guidanceVoiceFor('guidance_l1', 'hi-IN')).toBe('hi-IN-SwaraNeural')
    expect(guidanceVoiceFor('guidance_l1', 'kn-IN')).toBe('kn-IN-SapnaNeural')
  })

  it('forbids human lesson recordings and requires text-first fallback', () => {
    expect(CONFIDENCE_ALLOWS_HUMAN_LESSON_RECORDING).toBe(false)
    expect(CONFIDENCE_TEXT_FIRST_FALLBACK_REQUIRED).toBe(true)
  })
})
