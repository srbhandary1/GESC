import fixtures from './demoLevelFixtures.json'
import type { SessionPartId } from '../learner/LevelSessionsApi'

export interface DemoLevelFixture {
  level_number: number
  concept_key: string
  objective: string
  target_patterns: string[]
  kannada_summary: string
  talk: {
    scenario: string
    fallback_turns: string[]
  }
  check: Array<{
    id: string
    weight: number
    prompt: string
    options: string[]
    correct_option: number
  }>
  apply: {
    task: string
  }
}

export const DEMO_LEVEL_FIXTURES: readonly DemoLevelFixture[] =
  fixtures as DemoLevelFixture[]

export const DEMO_SESSION_PARTS: readonly SessionPartId[] = [
  'introduction',
  'practice',
  'conversation',
  'interaction',
]

export function demoLevelFixture(
  levelNumber: number,
): DemoLevelFixture | undefined {
  return DEMO_LEVEL_FIXTURES.find(
    (level) => level.level_number === levelNumber,
  )
}
