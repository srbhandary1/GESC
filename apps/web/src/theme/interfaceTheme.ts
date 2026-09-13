export const INTERFACE_THEME_STORAGE_KEY = 'ge-interface-theme-preference-v2'

export type InterfaceThemePreference = 'system' | 'light' | 'dark'
export type InterfaceTheme = 'light' | 'dark'

export const INTERFACE_THEME_PREFERENCES: readonly InterfaceThemePreference[] =
  ['system', 'light', 'dark']

/** Keep the application default storage-free and compatible with existing surfaces. */
export const DEFAULT_INTERFACE_THEME_PREFERENCE: InterfaceThemePreference =
  'light'

const THEME_COLOR: Readonly<Record<InterfaceTheme, string>> = {
  dark: '#0B1210',
  light: '#006B3F',
}

function canUseLocalStorage(): boolean {
  try {
    const probe = '__ge_theme_probe__'
    window.localStorage.setItem(probe, probe)
    window.localStorage.removeItem(probe)
    return true
  } catch {
    return false
  }
}

function systemPrefersDark(): boolean {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return true
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

export function readInterfaceThemePreference(): InterfaceThemePreference {
  if (typeof window === 'undefined' || !canUseLocalStorage()) {
    return DEFAULT_INTERFACE_THEME_PREFERENCE
  }

  const stored = window.localStorage.getItem(INTERFACE_THEME_STORAGE_KEY)

  if (
    stored === 'system' ||
    stored === 'light' ||
    stored === 'dark'
  ) {
    return stored
  }

  return DEFAULT_INTERFACE_THEME_PREFERENCE
}

export function resolveInterfaceTheme(
  preference: InterfaceThemePreference,
  prefersDark = systemPrefersDark(),
): InterfaceTheme {
  if (preference === 'light') {
    return 'light'
  }

  if (preference === 'dark') {
    return 'dark'
  }

  return prefersDark ? 'dark' : 'light'
}

export function applyInterfaceTheme(
  preference: InterfaceThemePreference,
  prefersDark?: boolean,
): InterfaceTheme {
  const resolved = resolveInterfaceTheme(
    preference,
    prefersDark ?? systemPrefersDark(),
  )

  document.documentElement.dataset.geTheme = resolved
  document.documentElement.dataset.geThemePreference = preference
  document.documentElement.style.colorScheme = resolved

  const themeColor = document.querySelector('meta[name="theme-color"]')
  if (themeColor instanceof HTMLMetaElement) {
    themeColor.content = THEME_COLOR[resolved]
  }

  return resolved
}

export function persistInterfaceThemePreference(
  preference: InterfaceThemePreference,
): void {
  if (!canUseLocalStorage()) {
    return
  }

  try {
    window.localStorage.setItem(INTERFACE_THEME_STORAGE_KEY, preference)
  } catch {
    // Preference stays in memory for this session when storage is blocked.
  }
}

export function initializeInterfaceTheme(): InterfaceTheme {
  return applyInterfaceTheme(readInterfaceThemePreference())
}
