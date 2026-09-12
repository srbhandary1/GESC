import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { createRef } from 'react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { MessageKey } from '@global-english/localization'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { OnboardingStepper } from './OnboardingStepper'

afterEach(() => {
  cleanup()
})

const message = (
  key: MessageKey,
  parameters?: Parameters<typeof resolveInterfaceMessage>[3],
) => resolveInterfaceMessage(key, 'en-IN', 'development-preview', parameters)

describe('OnboardingStepper', () => {
  it('asks one question per screen and keeps Continue sticky', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn()

    render(
      <OnboardingStepper
        locale="en-IN"
        message={message}
        onSubmit={onSubmit}
        steps={['adult', 'goal', 'level']}
        titleRef={createRef<HTMLHeadingElement>()}
      />,
    )

    expect(
      screen.getByRole('heading', { name: 'Are you 18 or older?' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Continue' }),
    ).toBeDisabled()

    await user.click(
      screen.getByRole('checkbox', {
        name: 'I confirm that I am 18 years old or older.',
      }),
    )
    await user.click(screen.getByRole('button', { name: 'Continue' }))

    expect(
      screen.getByRole('heading', {
        name: 'What do you want to speak English for?',
      }),
    ).toBeInTheDocument()
    await user.click(
      screen.getByRole('radio', {
        name: 'Speak with confidence at work',
      }),
    )
    await user.click(screen.getByRole('button', { name: 'Continue' }))

    await user.click(
      screen.getByRole('radio', { name: /I am just starting/ }),
    )
    await user.click(screen.getByRole('button', { name: 'Continue' }))

    expect(onSubmit).toHaveBeenCalledWith({
      adultConfirmed: true,
      goal: 'workplace-confidence',
      level: 'just-starting',
    })
  })
})
