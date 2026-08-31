import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { ApplicationFrame } from './ApplicationFrame'

const navigationLinks = {
  home: { href: '/home', label: 'ಮುಖಪುಟ' },
  learn: { href: '/learn', label: 'ಕಲಿಯಿರಿ' },
  speak: { href: '/speak', label: 'ಮಾತನಾಡಿ' },
  me: { href: '/me', label: 'ನಾನು' },
} as const

afterEach(() => {
  window.history.replaceState(null, '', '/')
})

describe('ApplicationFrame', () => {
  it('provides landmarks, a skip link and all primary destinations', () => {
    render(
      <ApplicationFrame
        brandLabel="Global English"
        brandLabelLocale="en-IN"
        currentDestination="speak"
        navigationLabel="ಮುಖ್ಯ ನ್ಯಾವಿಗೇಶನ್"
        navigationLabelLocale="kn-IN"
        navigationLinks={navigationLinks}
        skipToContentLabel="ಮುಖ್ಯ ವಿಷಯಕ್ಕೆ ಹೋಗಿ"
      >
        <h1>ಮಾತನಾಡಿ</h1>
      </ApplicationFrame>,
    )

    expect(
      screen.getByRole('link', { name: 'ಮುಖ್ಯ ವಿಷಯಕ್ಕೆ ಹೋಗಿ' }),
    ).toHaveAttribute('href', '#main-content')
    expect(
      screen.getByRole('navigation', { name: 'ಮುಖ್ಯ ನ್ಯಾವಿಗೇಶನ್' }),
    ).toBeVisible()
    expect(screen.getAllByRole('link')).toHaveLength(5)
    expect(screen.getByRole('link', { name: 'ಮಾತನಾಡಿ' })).toHaveAttribute(
      'aria-current',
      'page',
    )
    expect(screen.getByRole('main')).toHaveAttribute('id', 'main-content')
    expect(
      screen.getByRole('heading', { name: 'ಮಾತನಾಡಿ' }),
    ).toBeInTheDocument()
    expect(screen.getByText('Global English').closest('[lang]')).toHaveAttribute(
      'lang',
      'en-IN',
    )
  })

  it('focuses main content without replacing an application route hash', async () => {
    const user = userEvent.setup()
    window.history.replaceState(null, '', '/#learn')

    render(
      <ApplicationFrame
        brandLabel="Global English"
        brandLabelLocale="en-IN"
        currentDestination="learn"
        navigationLabel="ಮುಖ್ಯ ನ್ಯಾವಿಗೇಶನ್"
        navigationLabelLocale="kn-IN"
        navigationLinks={navigationLinks}
        skipToContentLabel="ಮುಖ್ಯ ವಿಷಯಕ್ಕೆ ಹೋಗಿ"
      >
        <h1>ಕಲಿಯಿರಿ</h1>
      </ApplicationFrame>,
    )

    await user.click(
      screen.getByRole('link', { name: 'ಮುಖ್ಯ ವಿಷಯಕ್ಕೆ ಹೋಗಿ' }),
    )

    expect(screen.getByRole('main')).toHaveFocus()
    expect(window.location.hash).toBe('#learn')
  })
})
