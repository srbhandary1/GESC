import { afterEach, describe, expect, it } from 'vitest'
import {
  INTERFACE_THEME_STORAGE_KEY,
  applyInterfaceTheme,
  readInterfaceThemePreference,
  resolveInterfaceTheme,
} from './interfaceTheme'

describe('interfaceTheme', () => {
  afterEach(() => {
    document.documentElement.removeAttribute('data-ge-theme')
    document.documentElement.removeAttribute('data-ge-theme-preference')
    document.documentElement.style.removeProperty('color-scheme')
    window.localStorage.removeItem(INTERFACE_THEME_STORAGE_KEY)
  })

  it('defaults to nocturnal dark when nothing is stored', () => {
    expect(readInterfaceThemePreference()).toBe('dark')
  })

  it('ignores the previous storage key after the academy template reset', () => {
    window.localStorage.setItem('ge-interface-theme-preference', 'light')

    expect(readInterfaceThemePreference()).toBe('dark')
  })

  it('resolves explicit light and dark preferences', () => {
    expect(resolveInterfaceTheme('light', true)).toBe('light')
    expect(resolveInterfaceTheme('dark', false)).toBe('dark')
    expect(resolveInterfaceTheme('system', true)).toBe('dark')
    expect(resolveInterfaceTheme('system', false)).toBe('light')
  })

  it('applies resolved theme attributes to the document root', () => {
    applyInterfaceTheme('light', true)

    expect(document.documentElement.dataset.geTheme).toBe('light')
    expect(document.documentElement.dataset.geThemePreference).toBe('light')
    expect(document.documentElement.dataset.theme).toBe('light')
    expect(document.documentElement.style.colorScheme).toBe('light')
  })

  it('sets GESC green browser chrome for light and nocturnal dark', () => {
    const themeColor = document.createElement('meta')
    themeColor.setAttribute('name', 'theme-color')
    document.head.append(themeColor)

    applyInterfaceTheme('light', false)
    expect(themeColor.content).toBe('#E8F3EC')

    applyInterfaceTheme('dark', true)
    expect(themeColor.content).toBe('#0B1210')

    themeColor.remove()
  })
})
