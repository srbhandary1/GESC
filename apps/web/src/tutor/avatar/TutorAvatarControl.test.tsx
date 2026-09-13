import {
  DEFAULT_LOCALE,
  resolveMessage,
  type MessageKey,
} from '@global-english/localization'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { TutorAvatarControl } from './TutorAvatarControl'

describe('TutorAvatarControl', () => {
  it('offers portrait and licensed GLB options', async () => {
    const user = userEvent.setup()
    let selected = 'portrait'

    render(
      <TutorAvatarControl
        avatars={[
          { id: 'portrait', label: 'Portrait', path: null },
          {
            id: 'swathi-1',
            label: 'Character A',
            path: '/tutor/avatars/swathi-1.glb',
          },
        ]}
        locale={DEFAULT_LOCALE}
        localizationPolicy="approved-only"
        onAvatarChange={(next) => {
          selected = next
        }}
        selectedAvatarId={selected}
      />,
    )

    const select = screen.getByRole('combobox', {
      name: label('tutor.avatar_label'),
    })
    expect(select).toHaveValue('portrait')
    expect(
      screen.getByRole('option', { name: label('tutor.avatar_option_portrait') }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('option', { name: 'Character A' }),
    ).toBeInTheDocument()

    await user.selectOptions(select, 'swathi-1')
    expect(selected).toBe('swathi-1')
  })
})

function label(key: MessageKey): string {
  return resolveMessage(key, {
    policy: 'approved-only',
    requestedLocale: DEFAULT_LOCALE,
  }).text
}
