export const COLOR_SCHEME_STORAGE_KEY = 'ge-ui-color-scheme'

export type ColorScheme = 'light' | 'dark'

function isColorScheme(value: string | null): value is ColorScheme {
  return value === 'light' || value === 'dark'
}

export function systemColorScheme(): ColorScheme {
  if (typeof globalThis.matchMedia !== 'function') {
    return 'dark'
  }

  return globalThis.matchMedia('(prefers-color-scheme: light)').matches
    ? 'light'
    : 'dark'
}

export function readStoredColorScheme(): ColorScheme | null {
  try {
    const stored = globalThis.localStorage?.getItem(COLOR_SCHEME_STORAGE_KEY)
    return isColorScheme(stored) ? stored : null
  } catch {
    return null
  }
}

export function persistColorScheme(scheme: ColorScheme): void {
  try {
    globalThis.localStorage?.setItem(COLOR_SCHEME_STORAGE_KEY, scheme)
  } catch {
    // Theme preference is optional chrome, never credentials.
  }
}

export function applyColorScheme(scheme: ColorScheme): void {
  const root = globalThis.document?.documentElement
  if (root === undefined) {
    return
  }

  root.dataset.theme = scheme
  root.dataset.geTheme = scheme
  root.style.colorScheme = scheme
}

export function resolveColorScheme(): ColorScheme {
  return readStoredColorScheme() ?? 'dark'
}
