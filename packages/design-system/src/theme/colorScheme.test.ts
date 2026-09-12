import { afterEach, describe, expect, it } from 'vitest'
import {
  COLOR_SCHEME_STORAGE_KEY,
  applyColorScheme,
  persistColorScheme,
  readStoredColorScheme,
  resolveColorScheme,
} from './colorScheme'

afterEach(() => {
  document.documentElement.removeAttribute('data-theme')
  document.documentElement.removeAttribute('data-ge-theme')
  document.documentElement.style.colorScheme = ''
  window.localStorage.removeItem(COLOR_SCHEME_STORAGE_KEY)
})

describe('colorScheme', () => {
  it('persists a chrome-only theme preference and applies it to the document', () => {
    persistColorScheme('dark')
    expect(readStoredColorScheme()).toBe('dark')
    expect(resolveColorScheme()).toBe('dark')

    applyColorScheme('dark')
    expect(document.documentElement.dataset.theme).toBe('dark')
    expect(document.documentElement.dataset.geTheme).toBe('dark')
    expect(document.documentElement.style.colorScheme).toBe('dark')
  })
})
