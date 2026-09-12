import { useEffect, useState } from 'react'
import {
  applyColorScheme,
  persistColorScheme,
  readStoredColorScheme,
  resolveColorScheme,
  systemColorScheme,
  type ColorScheme,
} from './colorScheme'

export function useColorScheme() {
  const [scheme, setScheme] = useState<ColorScheme>(() => resolveColorScheme())

  useEffect(() => {
    applyColorScheme(scheme)
  }, [scheme])

  useEffect(() => {
    if (typeof globalThis.matchMedia !== 'function') {
      return
    }

    const media = globalThis.matchMedia('(prefers-color-scheme: dark)')
    const syncFromSystem = () => {
      if (readStoredColorScheme() === null) {
        setScheme(systemColorScheme())
      }
    }

    media.addEventListener('change', syncFromSystem)
    return () => media.removeEventListener('change', syncFromSystem)
  }, [])

  const toggleScheme = () => {
    setScheme((current) => {
      const next = current === 'dark' ? 'light' : 'dark'
      persistColorScheme(next)
      applyColorScheme(next)
      return next
    })
  }

  return { scheme, setScheme, toggleScheme }
}
