import { describe, expect, it } from 'vitest'
import {
  lessonDrillModelUtterance,
  lessonDrillOpening,
  lessonDrillReply,
  lessonDrillUtteranceMatchesModel,
} from './lessonDrillStepper'

describe('lessonDrillStepper', () => {
  it('fills grammar slots into one spoken model sentence', () => {
    expect(lessonDrillModelUtterance('I can + base verb.')).toBe(
      'I can drink water.',
    )
    expect(
      lessonDrillModelUtterance('Yes, you can. / No, you cannot.'),
    ).toBe('Yes, you can.')
  })

  it('opens introduction on the first model only', () => {
    expect(
      lessonDrillOpening('introduction', [
        'I can + base verb.',
        'I cannot + base verb.',
      ]),
    ).toBe('Listen. Say: I can drink water. Now you try.')
  })

  it('advances introduction one pattern per learner try', () => {
    const patterns = [
      'I can + base verb.',
      'I cannot + base verb.',
      'Can I + base verb?',
      'Yes, you can. / No, you cannot.',
    ]

    expect(lessonDrillReply('introduction', patterns, 0)).toBe(
      'Good. Now listen. Say: I cannot drink water. Your turn.',
    )
    expect(lessonDrillReply('introduction', patterns, 3)).toBe(
      'Good. You practised each pattern. You can mark Introduction complete.',
    )
    expect(lessonDrillReply('introduction', patterns, 0)).not.toContain(
      'Do not start a free conversation',
    )
  })

  it('prefers authored fallback turns over template patterns', () => {
    expect(
      lessonDrillOpening(
        'introduction',
        ['If + present, will + base verb.'],
        ['If it rains, we will stay inside.'],
      ),
    ).toBe('Listen. Say: If it rains, we will stay inside. Now you try.')
  })

  it('retries the current model when the utterance does not match', () => {
    expect(
      lessonDrillUtteranceMatchesModel(
        'We cannot drink water.',
        'I cannot drink water.',
      ),
    ).toBe(false)
  })

  it('opens practice as a transform into the next form', () => {
    expect(
      lessonDrillOpening('practice', [
        'I can + base verb.',
        'I cannot + base verb.',
      ]),
    ).toBe('Practice time. I can drink water. Now say: I cannot drink water.')
  })
})
