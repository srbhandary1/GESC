import { describe, expect, it } from 'vitest'
import {
  bindChapterPractice,
  chapterFriendReply,
  chapterFromCurriculum,
  chapterListenLines,
  chapterOpeningLine,
  currentChapterPractice,
  explanationForLocale,
} from './chapterPractice'
import { curriculumByLevelId } from './curriculum/levelCurriculum'

describe('chapterPractice', () => {
  it('binds Can listen lines and a Kannada correction for the model sentence', () => {
    const can = curriculumByLevelId('level-1')
    expect(can).toBeDefined()
    const chapter = chapterFromCurriculum(can!)
    expect(chapter?.title).toBe('Can')
    expect(chapterListenLines(chapter!)).toContain(
      'I can speak English. Can I help you?',
    )
    expect(chapterOpeningLine(chapter!)).toContain('?')
    bindChapterPractice(chapter)
    expect(currentChapterPractice()?.id).toBe('level-1')
    expect(explanationForLocale('kn-IN', 'gentle', chapter!.modelLine)).toBe(
      'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I can speak English. Can I help you?"',
    )
    expect(explanationForLocale('en-IN', 'gentle', chapter!.modelLine)).toBe(
      'Say it more clearly as: "I can speak English. Can I help you?"',
    )
    expect(chapterFriendReply('I can help.', chapter!, 1)).toContain('Can')
    bindChapterPractice(null)
  })
})
