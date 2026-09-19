import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import {
  INTERFACE_THEME_PREFERENCES,
  type InterfaceThemePreference,
} from './interfaceTheme'

const themeMessageKeys: Readonly<
  Record<InterfaceThemePreference, MessageKey>
> = {
  system: 'shell.theme_system',
  light: 'shell.theme_light',
  dark: 'shell.theme_dark',
}

export interface InterfaceThemeControlProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly preference: InterfaceThemePreference
  readonly onPreferenceChange: (preference: InterfaceThemePreference) => void
}

export function InterfaceThemeControl({
  locale,
  localizationPolicy,
  preference,
  onPreferenceChange,
}: InterfaceThemeControlProps) {
  const [announcedPreference, setAnnouncedPreference] =
    useState<InterfaceThemePreference | null>(null)
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  return (
    <div className="shell-theme-control shell-theme-control--compact">
      <span aria-hidden="true" className="shell-toolbar-icon">
        ◐
      </span>
      <label className="visually-hidden" htmlFor="interface-theme">
        <LocalizedMessage resolution={message('shell.theme_label')} />
      </label>
      <select
        className="shell-theme-control__select"
        id="interface-theme"
        value={preference}
        onChange={(event) => {
          const next = event.currentTarget.value as InterfaceThemePreference
          onPreferenceChange(next)
          setAnnouncedPreference(next)
        }}
      >
        {INTERFACE_THEME_PREFERENCES.map((option) => (
          <option key={option} value={option}>
            {message(themeMessageKeys[option]).text}
          </option>
        ))}
      </select>
      <p className="visually-hidden" aria-atomic="true" aria-live="polite">
        {announcedPreference === null ? null : (
          <LocalizedMessage
            resolution={resolveInterfaceMessage(
              'shell.theme_changed',
              locale,
              localizationPolicy,
              {
                theme: message(themeMessageKeys[announcedPreference]).text,
              },
            )}
          />
        )}
      </p>
    </div>
  )
}
