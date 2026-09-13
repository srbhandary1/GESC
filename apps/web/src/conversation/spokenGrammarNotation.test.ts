import { describe, expect, it } from 'vitest'
import { spokenGrammarNotation } from './spokenGrammarNotation'

describe('spokenGrammarNotation', () => {
  it('reads drill plus as and and slash as Or', () => {
    expect(
      spokenGrammarNotation(
        'Let us practise: I can + base verb. I cannot + base verb. Can I + base verb? Yes, you can. / No, you cannot. Say one short sentence.',
        true,
      ),
    ).toBe(
      'Let us practise: I can and base verb. I cannot and base verb. Can I and base verb? Yes, you can. Or No, you cannot. Say one short sentence.',
    )
  })

  it('leaves free-speech plus and slash unchanged', () => {
    expect(
      spokenGrammarNotation('Two plus two is 2 + 2, not 4 / 0.'),
    ).toBe('Two plus two is 2 + 2, not 4 / 0.')
  })

  it('leaves ordinary speech unchanged', () => {
    expect(spokenGrammarNotation('I can speak English.', true)).toBe(
      'I can speak English.',
    )
  })
})
