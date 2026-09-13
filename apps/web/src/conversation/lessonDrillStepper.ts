/**
 * Turns written target_patterns into one spoken model sentence at a time.
 * Introduction repeats the model. Practice asks for the next form.
 */
export function lessonDrillModelUtterance(pattern: string): string {
  let text = pattern.trim()
  text = text.replaceAll(/\bShe\/He\b/gu, 'She')
  text = text.replaceAll(/\bhe\/she\b/gu, 'she')
  text = text.replaceAll(/\s*\+\s*base verb/giu, ' drink water')
  text = text.replaceAll(/\s*\+\s*-ing/giu, ' waiting')
  text = text.replaceAll(/\s*\+\s*past verb/giu, ' cooked')
  const slash = text.match(/^(.+?)\s+\/\s+/u)
  if (slash?.[1] !== undefined) {
    text = slash[1].trim()
    if (!/[.?!]$/u.test(text)) {
      text += '.'
    }
  }

  return text.trim()
}

function modelLooksLikeTemplate(model: string): boolean {
  return /\+|…|\.\.\./u.test(model)
}

export function lessonDrillModels(
  patterns: readonly string[],
  fallbackTurns?: readonly string[],
): readonly string[] {
  return patterns
    .map((pattern, index) => {
      const model = lessonDrillModelUtterance(pattern)
      const fallback = fallbackTurns?.[index]?.trim()
      if (fallback !== undefined && fallback !== '' && modelLooksLikeTemplate(model)) {
        return fallback
      }
      return model
    })
    .filter((model) => model !== '')
}

export function lessonDrillOpening(
  partId: 'introduction' | 'practice',
  patterns: readonly string[],
  fallbackTurns?: readonly string[],
): string {
  const models = lessonDrillModels(patterns, fallbackTurns)
  const first = models[0]
  if (first === undefined) {
    return ''
  }

  if (partId === 'practice') {
    const second = models[1] ?? first
    return `Practice time. ${first} Now say: ${second}`
  }

  return `Listen. Say: ${first} Now you try.`
}

export function lessonDrillNormalizeForMatch(text: string): string {
  return text
    .trim()
    .toLowerCase()
    .replaceAll(/[^\p{L}\p{N}\s]/gu, '')
    .replaceAll(/\s+/gu, ' ')
    .trim()
}

export function lessonDrillUtteranceMatchesModel(
  utterance: string,
  model: string,
): boolean {
  return (
    lessonDrillNormalizeForMatch(utterance)
    === lessonDrillNormalizeForMatch(model)
  )
}

export function lessonDrillCurrentModel(
  patterns: readonly string[],
  acceptedPatternCount: number,
  fallbackTurns?: readonly string[],
): string | undefined {
  const models = lessonDrillModels(patterns, fallbackTurns)
  return models[acceptedPatternCount]
}

export function lessonDrillRetryReply(
  partId: 'introduction' | 'practice',
  currentModel: string,
): string {
  if (partId === 'practice') {
    return `Good try. Now say: ${currentModel}`
  }

  return `Good try. Listen again. Say: ${currentModel} Your turn.`
}

export function lessonDrillReply(
  partId: 'introduction' | 'practice',
  patterns: readonly string[],
  completedTurnsBeforeThis: number,
  fallbackTurns?: readonly string[],
): string {
  const models = lessonDrillModels(patterns, fallbackTurns)
  if (models.length === 0) {
    return ''
  }

  const nextIndex = Math.max(0, completedTurnsBeforeThis) + 1
  const next = models[nextIndex]
  if (next === undefined) {
    return partId === 'practice'
      ? 'Good. Finish the Check items on this page.'
      : 'Good. You practised each pattern. You can mark Introduction complete.'
  }

  if (partId === 'practice') {
    return `Good. Now say: ${next}`
  }

  return `Good. Now listen. Say: ${next} Your turn.`
}
