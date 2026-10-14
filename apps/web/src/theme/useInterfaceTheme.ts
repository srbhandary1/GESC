import { useCallback, useEffect, useState } from 'react'
import {
  applyInterfaceTheme,
  INTERFACE_THEME_STORAGE_KEY,
  persistInterfaceThemePreference,
  readInterfaceThemePreference,
  resolveInterfaceTheme,
  type InterfaceTheme,
  type InterfaceThemePreference,
} from './interfaceTheme'

function canListenToSystemTheme(): boolean {
  return typeof window.matchMedia === 'function'
}

export function useInterfaceTheme() {
  const [preference, setPreferenceState] = useState<InterfaceThemePreference>(
    () => readInterfaceThemePreference(),
  )
  const [systemPrefersDark, setSystemPrefersDark] = useState(
    () => !canListenToSystemTheme() || window.matchMedia('(prefers-color-scheme: dark)').matches,
  )
  const resolvedTheme: InterfaceTheme = resolveInterfaceTheme(
    preference,
    systemPrefersDark,
  )

  useEffect(() => {
    applyInterfaceTheme(preference, systemPrefersDark)
  }, [preference, systemPrefersDark])

  useEffect(() => {
    if (!canListenToSystemTheme()) {
      return undefined
    }

    const media = window.matchMedia('(prefers-color-scheme: dark)')
    const syncSystemTheme = () => {
      setSystemPrefersDark(media.matches)
    }

    media.addEventListener('change', syncSystemTheme)

    return () => {
      media.removeEventListener('change', syncSystemTheme)
    }
  }, [])

  useEffect(() => {
    const onStorage = (event: StorageEvent) => {
      if (event.key !== INTERFACE_THEME_STORAGE_KEY || event.newValue === null) {
        return
      }

      if (
        event.newValue === 'system' ||
        event.newValue === 'light' ||
        event.newValue === 'dark'
      ) {
        setPreferenceState(event.newValue)
      }
    }

    window.addEventListener('storage', onStorage)

    return () => {
      window.removeEventListener('storage', onStorage)
    }
  }, [])

  const setPreference = useCallback(
    (next: InterfaceThemePreference) => {
      persistInterfaceThemePreference(next)
      applyInterfaceTheme(next, systemPrefersDark)
      setPreferenceState(next)
    },
    [systemPrefersDark],
  )

  return {
    preference,
    resolvedTheme,
    setPreference,
  }
}
