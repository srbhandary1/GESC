/**
 * Turns written grammar formulas into speech-friendly English.
 *
 * Lesson cards keep "+" / "/". Ask AI Tutor TTS must not say "plus" or
 * "slash". Free-speech rooms leave those characters alone so a mathematical
 * "plus" still sounds like plus.
 */
export function spokenGrammarNotation(
  text: string,
  grammarDrill = false,
): string {
  if (!grammarDrill) {
    return text
  }

  return text
    .replaceAll(/\s+[/\u2044\u2215\uFF0F]\s+/gu, ' Or ')
    .replaceAll(/\s+[+\uFF0B]\s+/gu, ' and ')
}
