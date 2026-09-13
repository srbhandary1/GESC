import { describe, expect, it } from 'vitest'
import { demoLevelFixture } from './demoLevelFixtures'
import { demoLevelSessionSpokenLine } from './demoLevelSessionAudio'

describe('demoLevelSessionSpokenLine', () => {
  it('uses introduction lesson patterns instead of conversation drill lines', () => {
    const fixture = demoLevelFixture(1)
    expect(fixture).toBeDefined()

    const spoken = demoLevelSessionSpokenLine(fixture!, 'introduction')

    expect(spoken).toContain('Listen. Say:')
    expect(spoken).not.toContain('Can I use your pen?')
  })

  it('uses the conversation scenario for conversation audio', () => {
    const fixture = demoLevelFixture(1)
    expect(fixture).toBeDefined()

    const spoken = demoLevelSessionSpokenLine(fixture!, 'conversation')

    expect(spoken).toContain('community centre')
    expect(spoken).not.toContain('Listen. Say:')
  })
})
