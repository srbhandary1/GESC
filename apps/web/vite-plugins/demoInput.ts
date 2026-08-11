export function containsDisallowedControlCharacter(value: string): boolean {
  for (const character of value) {
    const codePoint = character.codePointAt(0)

    if (
      codePoint === 0x7f ||
      (codePoint !== undefined &&
        codePoint <= 0x1f &&
        codePoint !== 0x09 &&
        codePoint !== 0x0a &&
        codePoint !== 0x0d)
    ) {
      return true
    }
  }

  return false
}
