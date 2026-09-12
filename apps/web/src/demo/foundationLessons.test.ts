import { describe, expect, it } from 'vitest'
import {
  FOUNDATION_LESSONS,
  destinationFromLessonHash,
  foundationLessonById,
  lessonIdFromHash,
} from './foundationLessons'

describe('foundation lesson fixtures', () => {
  it('keeps 16 unique listen-and-learn lessons', () => {
    expect(FOUNDATION_LESSONS).toHaveLength(16)
    expect(new Set(FOUNDATION_LESSONS.map((lesson) => lesson.id)).size).toBe(16)
    expect(FOUNDATION_LESSONS[0]?.title).toBe('Greetings and introductions')
    expect(FOUNDATION_LESSONS[15]?.title).toBe(
      'Office introductions and daily stand-up',
    )
  })

  it('resolves Learn and Speak lesson hashes', () => {
    expect(lessonIdFromHash('#learn/greetings')).toBe('greetings')
    expect(lessonIdFromHash('#practice/office')).toBe('office')
    expect(lessonIdFromHash('#learn/missing')).toBeNull()
    expect(destinationFromLessonHash('#learn/coffee-shop')).toBe('learn')
    expect(destinationFromLessonHash('#practice/travel')).toBe('practice')
    expect(foundationLessonById('shopping')?.number).toBe(11)
  })
})
