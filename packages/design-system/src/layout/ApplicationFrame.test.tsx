import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { ApplicationFrame } from './ApplicationFrame'

const navigationLinks = {
  home: { href: '/home', label: 'ಮುಖಪುಟ' },
  learn: { href: '/learn', label: 'ಕಲಿಯಿರಿ' },
  practice: { href: '/practice', label: 'ಅಭ್ಯಾಸ' },
  progress: { href: '/progress', label: 'ಪ್ರಗತಿ' },
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
        currentDestination="practice"
        navigationLabel="ಮುಖ್ಯ ನ್ಯಾವಿಗೇಶನ್"
        navigationLabelLocale="kn-IN"
        navigationLinks={navigationLinks}
        skipToContentLabel="ಮುಖ್ಯ ವಿಷಯಕ್ಕೆ ಹೋಗಿ"
      >
        <h1>ಅಭ್ಯಾಸ</h1>
      </ApplicationFrame>,
    )

    expect(
      screen.getByRole('link', { name: 'ಮುಖ್ಯ ವಿಷಯಕ್ಕೆ ಹೋಗಿ' }),
    ).toHaveAttribute('href', '#main-content')
    expect(
      screen.getByRole('navigation', { name: 'ಮುಖ್ಯ ನ್ಯಾವಿಗೇಶನ್' }),
    ).toBeVisible()
    expect(screen.getAllByRole('link')).toHaveLength(5)
    expect(screen.getByRole('link', { name: 'ಅಭ್ಯಾಸ' })).toHaveAttribute(
      'aria-current',
      'page',
    )
    expect(screen.getByRole('main')).toHaveAttribute('id', 'main-content')
    expect(
      screen.getByRole('heading', { name: 'ಅಭ್ಯಾಸ' }),
    ).toBeInTheDocument()
    expect(
      document.querySelector('.ge-application-frame__brand'),
    ).toHaveAttribute('lang', 'en-IN')
    expect(screen.getByText('Global English')).toBeInTheDocument()
  })

  it('keeps the full name in the tree and exposes a compact mobile wordmark', () => {
    render(
      <ApplicationFrame
        brandLabel="Global English Speaking Course"
        brandLabelLocale="en-IN"
        compactBrandLabel="Global English"
        currentDestination="home"
        navigationLabel="Primary navigation"
        navigationLabelLocale="en-IN"
        navigationLinks={navigationLinks}
        skipToContentLabel="Skip"
      >
        <h1>Home</h1>
      </ApplicationFrame>,
    )

    expect(
      screen.getByText('Global English Speaking Course'),
    ).toBeInTheDocument()
    const shortMark = document.querySelector(
      '.ge-application-frame__brand-short',
    )
    expect(shortMark).toHaveTextContent('Global English')
    expect(shortMark).toHaveAttribute('aria-hidden', 'true')
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
