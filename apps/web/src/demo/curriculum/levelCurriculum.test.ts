import { describe, expect, it } from 'vitest'
import {
  authoredLevelCount,
  curriculumByLevelId,
  LEVEL_CURRICULUM,
} from './levelCurriculum'

describe('levelCurriculum', () => {
  it('restores Can plus APP-0065 Levels 2–17 and flags missing Pre-Levels', () => {
    expect(LEVEL_CURRICULUM).toHaveLength(19)
    expect(authoredLevelCount()).toBe(17)
    expect(curriculumByLevelId('pre-1')?.availability).toBe('missing')
    expect(curriculumByLevelId('pre-2')?.availability).toBe('missing')
    expect(curriculumByLevelId('level-1')).toMatchObject({
      conceptKey: 'can',
      title: 'Can',
      modelLine: 'I can speak English. Can I help you?',
    })
    expect(curriculumByLevelId('level-2')).toMatchObject({
      conceptKey: 'should',
      title: 'Should',
      modelLine: 'You should drink some water.',
    })
    expect(curriculumByLevelId('level-17')).toMatchObject({
      conceptKey: 'used_to',
      title: 'Used to',
      modelLine: 'I used to walk to school.',
    })
    expect(
      LEVEL_CURRICULUM.some((entry) =>
        (entry.modelLine ?? '').toLowerCase().includes('coffee'),
      ),
    ).toBe(false)
  })
})
