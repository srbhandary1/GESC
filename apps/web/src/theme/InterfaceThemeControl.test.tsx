import {
  DEFAULT_LOCALE,
  resolveMessage,
  type MessageKey,
} from '@global-english/localization'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { InterfaceThemeControl } from './InterfaceThemeControl'
import {
  INTERFACE_THEME_PREFERENCES,
  type InterfaceThemePreference,
} from './interfaceTheme'

describe('InterfaceThemeControl', () => {
  it('offers system, light, and dark appearance options', async () => {
    const user = userEvent.setup()
    let preference: InterfaceThemePreference = 'system'

    render(
      <InterfaceThemeControl
        locale={DEFAULT_LOCALE}
        localizationPolicy="approved-only"
        onPreferenceChange={(next) => {
          preference = next
        }}
        preference={preference}
      />,
    )

    const select = screen.getByRole('combobox', {
      name: label('shell.theme_label'),
    })
    expect(select).toHaveValue('system')

    for (const option of INTERFACE_THEME_PREFERENCES) {
      const key =
        option === 'system'
          ? 'shell.theme_system'
          : option === 'light'
            ? 'shell.theme_light'
            : 'shell.theme_dark'

      expect(
        screen.getByRole('option', {
          name: label(key),
        }),
      ).toBeInTheDocument()
    }

    await user.selectOptions(select, 'dark')
    expect(preference).toBe('dark')
  })
})

function label(key: MessageKey): string {
  return resolveMessage(key, {
    policy: 'approved-only',
    requestedLocale: DEFAULT_LOCALE,
  }).text
}
