import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import sources from './instructionSources.json'
import pack from './kannadaInstructions.json'
import { lessonInstruction } from './lessonInstruction'

describe('source-linked Kannada lesson directions', () => {
  it('keeps every pinned curriculum source current and every variant in Kannada', () => {
    for (const [path, hash] of Object.entries(pack.sourceFiles)) {
      expect(createHash('sha256').update(readFileSync(resolve('../..', path))).digest('hex')).toBe(hash)
    }
    expect(Object.keys(pack.entries)).toHaveLength(187)
    for (const [source, text] of Object.entries(pack.entries)) {
      expect(text).toMatch(/[\u0c80-\u0cff]/u)
      expect(lessonInstruction(source, 'kn-IN', 'development-preview')).toEqual({ text, lang: 'kn-IN' })
      expect(lessonInstruction(source, 'en-IN', 'development-preview')).toEqual({ text: source, lang: 'en-IN' })
      expect(lessonInstruction(source, 'kn-IN', 'approved-only').text).toBe(source)
    }
    expect(pack.status).toBe('approved')
    expect(pack.reviewer).toBe('S R Bhandary')
    expect(pack.approvedAt).toBe('2026-09-26T13:18:00+05:30')
    expect(pack.independentReview).toBe(true)
  })

  it('covers every scenario, goal, task, quiz prompt and explanation in all 17 source lessons', () => {
    expect(Object.values(sources).map(({ level }) => level).sort((a, b) => a - b)).toEqual(Array.from({ length: 17 }, (_, i) => i + 1))
    for (const { instructions } of Object.values(sources)) {
      for (const source of instructions) {
        expect(lessonInstruction(source, 'kn-IN', 'development-preview').lang, source).toBe('kn-IN')
      }
    }
  })

  it('translates checkpoint prefixes and directions without translating English patterns', () => {
    expect(lessonInstruction('Level review: Choose the correct sentence.', 'kn-IN', 'development-preview').text).toBe('ಹಂತದ ಮರುಪರಿಶೀಲನೆ: ಸರಿಯಾದ ವಾಕ್ಯವನ್ನು ಆಯ್ಕೆಮಾಡಿ.')
    expect(lessonInstruction('Notice these patterns: I can + base verb.', 'kn-IN', 'development-preview').text).toBe('ಈ ವಾಕ್ಯರಚನೆಗಳನ್ನು ಗಮನಿಸಿ: I can + base verb.')
  })

  it('does not attach a stale translation to changed or unknown content', () => {
    for (const text of ['Choose the correct sentence. Changed.', 'Level review: A new question', 'I can speak English.']) {
      expect(lessonInstruction(text, 'kn-IN', 'development-preview')).toEqual({ text, lang: 'en-IN' })
    }
  })
})
