export const PRACTICE_ABOUT_STORAGE_KEY = 'ge-ui-practice-about-dismissed'
export const GROVE_WELCOME_STORAGE_KEY = 'ge-ui-grove-welcome-dismissed'
export const SPEAKING_COMFORT_STORAGE_KEY = 'ge-ui-speaking-comfort'

export function persistSpeakingComfort(level: string): void {
  try {
    globalThis.localStorage?.setItem(SPEAKING_COMFORT_STORAGE_KEY, level)
  } catch {
    // Speaking comfort is chrome-only and never a credential.
  }
}

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
