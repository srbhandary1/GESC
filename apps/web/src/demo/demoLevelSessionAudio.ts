import { lessonDrillOpening } from '../conversation/lessonDrillStepper'
import type { SessionPartId } from '../learner/LevelSessionsApi'
import type { DemoLevelFixture } from './demoLevelFixtures'

/**
 * Demo TTS must follow the session part, not reuse conversation drill lines
 * for Introduction / Practice listening.
 */
export function demoLevelSessionSpokenLine(
  fixture: DemoLevelFixture,
  partId: SessionPartId,
): string {
  if (partId === 'introduction' || partId === 'practice') {
    const opening = lessonDrillOpening(
      partId,
      fixture.target_patterns,
      fixture.talk.fallback_turns,
    )
    if (opening !== '') {
      return opening
    }
  }

  if (partId === 'conversation') {
    const scenario = fixture.talk.scenario.trim()
    if (scenario !== '') {
      return scenario
    }
  }

  if (partId === 'interaction') {
    const task = fixture.apply.task.trim()
    if (task !== '') {
      return task
    }
  }

  return (
    fixture.target_patterns[0]?.trim() ||
    fixture.objective.trim() ||
    fixture.talk.fallback_turns[0]?.trim() ||
    ''
  )
}
