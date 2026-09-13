import type { SupportedLocale } from '@global-english/localization'
import type { LevelCurriculum } from './curriculum/levelCurriculum'

export interface ChapterPracticeFocus {
  readonly id: string
  readonly title: string
  readonly conceptKey: string | null
  readonly modelLine: string
  readonly fallbackTurns: readonly string[]
  readonly targetPatterns: readonly string[]
  readonly scenario: string | null
}

let boundChapter: ChapterPracticeFocus | null = null

export function chapterFromCurriculum(
  session: LevelCurriculum,
): ChapterPracticeFocus | null {
  if (session.availability !== 'authored' || session.modelLine === null) {
    return null
  }

  return {
    id: session.id,
    title: session.title,
    conceptKey: session.conceptKey,
    modelLine: session.modelLine,
    fallbackTurns: session.fallbackTurns,
    targetPatterns: session.targetPatterns,
    scenario: session.scenario,
  }
}

export function bindChapterPractice(
  chapter: ChapterPracticeFocus | null,
): void {
  boundChapter = chapter
}

export function currentChapterPractice(): ChapterPracticeFocus | null {
  return boundChapter
}

export function chapterOpeningLine(
  chapter: ChapterPracticeFocus,
): string {
  const spokenQuestion = chapter.fallbackTurns
    .map((turn) => turn.trim())
    .find((turn) => turn.includes('?'))

  return spokenQuestion ?? chapter.modelLine
}

export function chapterListenLines(
  chapter: ChapterPracticeFocus,
): readonly string[] {
  const lines = [chapter.modelLine, ...chapter.fallbackTurns].filter(
    (line, index, all) => line.trim() !== '' && all.indexOf(line) === index,
  )
  return lines.length > 0 ? lines : [chapter.modelLine]
}

const GENTLE_CLEARER: Readonly<Record<SupportedLocale, string>> = {
  'en-IN': 'Say it more clearly as: "{sentence}"',
  'kn-IN': 'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "{sentence}"',
  'hi-IN': 'स्पष्ट रूप से कहें: "{sentence}"',
  'ml-IN': 'വ്യക്തമായി പറയുക: "{sentence}"',
  'ta-IN': 'தெளிவாகச் சொல்லுங்கள்: "{sentence}"',
  'te-IN': 'స్పష్టంగా చెప్పండి: "{sentence}"',
}

const TUTOR_SAY: Readonly<Record<SupportedLocale, string>> = {
  'en-IN': 'Say: "{sentence}"',
  'kn-IN': 'ಹೀಗೆ ಹೇಳಿ: "{sentence}"',
  'hi-IN': 'इस तरह कहें: "{sentence}"',
  'ml-IN': 'ഇങ്ങനെ പറയുക: "{sentence}"',
  'ta-IN': 'இப்படிச் சொல்லுங்கள்: "{sentence}"',
  'te-IN': 'ఇలా చెప్పండి: "{sentence}"',
}

const FLUENCY: Readonly<Record<SupportedLocale, string>> = {
  'en-IN': 'Keep one idea per sentence so the flow stays smooth.',
  'kn-IN': 'ಹರಿವು ಸುಗಮವಾಗಿರಲು ಒಂದು ವಾಕ್ಯದಲ್ಲಿ ಒಂದೇ ಆಲೋಚನೆ ಇರಿಸಿ.',
  'hi-IN': 'प्रवाह सहज रखने के लिए एक वाक्य में एक ही विचार रखें।',
  'ml-IN': 'ഒഴുക്ക് സുഗമമാക്കാൻ ഒരു വാക്യത്തിൽ ഒരു ആശയം മാത്രം വയ്ക്കുക.',
  'ta-IN': 'ஓட்டம் இயல்பாக இருக்க ஒரு வாக்கியத்தில் ஒரு எண்ணமே இருக்கட்டும்.',
  'te-IN': 'ప్రవాహం సాఫీగా ఉండేలా ఒక వాక్యంలో ఒకే ఆలోచన ఉంచండి.',
}

export function explanationForLocale(
  locale: SupportedLocale,
  coachingMode: 'gentle' | 'tutor' | 'fluency',
  sentence: string,
): string {
  if (coachingMode === 'fluency') {
    return FLUENCY[locale] ?? FLUENCY['en-IN']
  }

  const template =
    coachingMode === 'tutor'
      ? (TUTOR_SAY[locale] ?? TUTOR_SAY['en-IN'])
      : (GENTLE_CLEARER[locale] ?? GENTLE_CLEARER['en-IN'])

  return template.replace('{sentence}', sentence)
}

export function chapterFriendReply(
  utterance: string,
  chapter: ChapterPracticeFocus,
  turnNumber: number,
): string {
  const questions = chapter.fallbackTurns.filter((turn) => turn.includes('?'))
  const followUp =
    questions[(turnNumber - 1) % Math.max(questions.length, 1)] ??
    `Can you use ${chapter.title} in one short sentence?`
  const mention = utterance.trim() === '' ? '' : 'I heard you. '
  return `${mention}This lesson is ${chapter.title}. Try: ${chapter.modelLine} ${followUp}`
}

export function chapterUsesPattern(
  utterance: string,
  chapter: ChapterPracticeFocus,
): boolean {
  const text = utterance.trim().toLowerCase()
  if (text === '') {
    return false
  }

  const concept = (chapter.conceptKey ?? chapter.title)
    .replaceAll('_', ' ')
    .toLowerCase()
  const conceptWords = concept.split(/\s+/u).filter((word) => word.length > 1)
  return conceptWords.some((word) => text.includes(word))
}
