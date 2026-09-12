export const PRACTICE_ABOUT_STORAGE_KEY = 'ge-ui-practice-about-dismissed'

export function readFirstRunDismissed(key: string): boolean {
  try {
    return globalThis.localStorage?.getItem(key) === '1'
  } catch {
    return false
  }
}

export function persistFirstRunDismissed(key: string): void {
  try {
    globalThis.localStorage?.setItem(key, '1')
  } catch {
    // First-run chrome only; never credentials or learner records.
  }
}
