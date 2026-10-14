import type { ResolutionPolicy } from '@global-english/localization'
import pack from './kannadaInstructions.json'

const entries: Readonly<Record<string, string>> = pack.entries

/** Source-exact reading variants; never modify the assessment or published source. */
export function lessonInstruction(
  source: string,
  locale: string,
  policy: ResolutionPolicy,
): { text: string; lang: string } {
  const original = { text: source, lang: 'en-IN' }
  if (locale !== 'kn-IN' || policy !== 'development-preview') return original
  const translated = entries[source]
  if (translated) return { text: translated, lang: 'kn-IN' }
  const reviewPrefix = 'Level review: '
  if (source.startsWith(reviewPrefix)) {
    const prompt = entries[source.slice(reviewPrefix.length)]
    if (prompt) return { text: `ಹಂತದ ಮರುಪರಿಶೀಲನೆ: ${prompt}`, lang: 'kn-IN' }
  }
  // Server-owned activity prefix; preserve the actual English practice patterns.
  const noticePrefix = 'Notice these patterns: '
  if (source.startsWith(noticePrefix)) {
    return { text: `ಈ ವಾಕ್ಯರಚನೆಗಳನ್ನು ಗಮನಿಸಿ: ${source.slice(noticePrefix.length)}`, lang: 'kn-IN' }
  }
  return original
}
