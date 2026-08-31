import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { CoachPreferenceProvider } from './CoachPreferenceContext'
import { CoachSelector } from './CoachSelector'

afterEach(() => {
  cleanup()
})

describe('CoachSelector', () => {
  it('selects the male coach and applies a bounded device-local name', async () => {
    const user = userEvent.setup()
    render(
      <CoachPreferenceProvider>
        <CoachSelector locale="en-IN" localizationPolicy="development-preview" />
      </CoachPreferenceProvider>,
    )

    await user.click(screen.getByRole('radio', { name: /Arjun/ }))
    const field = screen.getByRole('textbox', {
      name: 'Coach display name on this device',
    })
    await user.type(field, 'Ravi')

    expect(field).toHaveValue('Ravi')
    expect(
      screen.getByText(
        "This name stays on this device and does not change the AI teacher's governed identity.",
      ),
    ).toBeInTheDocument()
  })
})
