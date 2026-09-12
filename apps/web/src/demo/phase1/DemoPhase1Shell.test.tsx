import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DemoPhase1Shell } from './DemoPhase1Shell'
import {
  GROVE_WELCOME_STORAGE_KEY,
  persistFirstRunDismissed,
} from '../../ui/firstRunPreference'

vi.mock('../demoTts', () => ({
  speakDemoText: vi.fn().mockResolvedValue('azure'),
  stopDemoSpeech: vi.fn(),
  demoAzureTtsConfigured: vi.fn().mockResolvedValue(true),
}))

afterEach(() => {
  cleanup()
  window.history.replaceState(null, '', '/')
  window.localStorage.removeItem(GROVE_WELCOME_STORAGE_KEY)
})

beforeEach(() => {
  persistFirstRunDismissed(GROVE_WELCOME_STORAGE_KEY)
})

describe('DemoPhase1Shell', () => {
  it('opens a focused home with one next-lesson action', () => {
    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('heading', { level: 1, name: 'Today' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('img', { name: 'AI Teacher' }),
    ).toHaveAttribute('data-avatar-slot', 'home-teacher')
    expect(
      screen.queryByRole('navigation', { name: 'Phase 1 modules' }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /Continue today’s lesson/i }),
    ).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Speak now' })).toHaveAttribute(
      'href',
      '#practice',
    )
    expect(
      screen.getByRole('link', { name: 'Download Android demo' }),
    ).toHaveAttribute('href', '/downloads/global-english-demo.apk')
    expect(
      screen.getByText(
        'Same synthetic demo for phone testing. Sideload only — not Play Store.',
      ),
    ).toBeVisible()
    expect(
      screen.getByRole('button', { name: 'My Levels' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Profile' }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: /Notice Board/i }),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: /^Attendance$/i }),
    ).not.toBeInTheDocument()
  })

  it('keeps attendance and notices on Profile, not on Home', async () => {
    const user = userEvent.setup()

    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    await user.click(screen.getByRole('button', { name: 'Profile' }))
    expect(
      screen.getByRole('heading', { level: 1, name: 'Profile' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', { name: 'Demo status' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('complementary', { name: 'Synthetic local demo' }),
    ).toHaveAttribute('data-demo-mode', 'synthetic-local')
    expect(
      screen.getByRole('button', { name: 'Attendance report' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Notices' })).toBeInTheDocument()
    expect(screen.getByText('About this school')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Attendance report' }))
    expect(
      screen.getByRole('heading', { level: 1, name: 'Attendance' }),
    ).toBeInTheDocument()
    expect(screen.getByText('75%')).toBeInTheDocument()
    expect(screen.getByText('August 2026')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Back' }))
    await user.click(screen.getByRole('button', { name: 'Profile' }))
    await user.click(screen.getByRole('button', { name: 'Notices' }))
    expect(
      screen.getByText('Welcome to the Phase 1 demo pathway'),
    ).toBeInTheDocument()
  })

  it('opens today’s lesson, a focused AI Teacher, and scannable levels', async () => {
    const user = userEvent.setup()

    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    await user.click(
      screen.getByRole('button', { name: /Continue today’s lesson/i }),
    )
    expect(screen.getByText("Today's 5-mark check")).toBeInTheDocument()
    expect(screen.getAllByRole('radio').length).toBe(15)

    await user.click(screen.getByRole('button', { name: 'Back' }))
    await user.click(screen.getByRole('button', { name: 'AI Teacher' }))
    expect(
      screen.getByRole('img', { name: 'AI Teacher' }),
    ).toHaveAttribute('data-avatar-slot', 'ai-teacher')
    expect(
      screen.getByText(
        'Scripted demo replies. Voice uses Azure neural TTS when configured; no generative AI call.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByText(/Welcome\. You are on Level 1/),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: "Today’s task" }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'My level' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Next step' }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Explain the course' }),
    ).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Back' }))
    await user.click(screen.getByRole('button', { name: 'My Levels' }))
    expect(
      screen.getByRole('button', { name: /Pre-Level 1/i }),
    ).toBeInTheDocument()
    expect(screen.getByText('Content Room')).toBeInTheDocument()
    expect(
      screen.queryByText('Reading and phrase notes for today'),
    ).not.toBeInTheDocument()
  })

  it('renders Kannada UI chrome and AI replies under kn-IN', async () => {
    const user = userEvent.setup()

    render(
      <DemoPhase1Shell
        locale="kn-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('heading', { level: 1, name: 'ಇಂದು' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('img', { name: 'AI ಶಿಕ್ಷಕ' }),
    ).toHaveAttribute('data-avatar-slot', 'home-teacher')
    expect(
      screen.queryByRole('navigation', { name: 'Phase 1 ಮಾಡ್ಯೂಲ್‌ಗಳು' }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /ಇಂದಿನ ಪಾಠ ಮುಂದುವರಿಸಿ/ }),
    ).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'AI ಶಿಕ್ಷಕ' }))
    expect(
      screen.getByText(/ಸ್ವಾಗತ\. ನೀವು Level 1 ರಲ್ಲಿದ್ದೀರಿ/),
    ).toBeInTheDocument()
    expect(screen.queryByText(/Svagata|Neevu Level/)).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'ಕನ್ನಡ' })).toHaveClass(
      'is-active',
    )

    await user.click(screen.getByRole('button', { name: 'ಹಿಂದೆ' }))
    await user.click(screen.getByRole('button', { name: 'ಪ್ರೊಫೈಲ್' }))
    await user.click(screen.getByRole('button', { name: 'ಹಾಜರಾತಿ ವರದಿ' }))
    expect(screen.getByText('ಆಗಸ್ಟ್ ೨೦೨೬')).toBeInTheDocument()
    expect(screen.getByText('ಗೈರು')).toBeInTheDocument()
  })

  it('renders Hindi UI chrome when hi-IN is selected', async () => {
    const user = userEvent.setup()

    render(
      <DemoPhase1Shell
        locale="hi-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('heading', { level: 1, name: 'आज' }),
    ).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'AI शिक्षक' }))
    expect(screen.getByText(/स्वागत है। आप Level 1 पर हैं/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'हिन्दी' })).toHaveClass(
      'is-active',
    )
  })

  it('walks a one-question first-run before Home', async () => {
    window.localStorage.removeItem(GROVE_WELCOME_STORAGE_KEY)
    const user = userEvent.setup()
    const onLocaleChange = vi.fn()

    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
        onLocaleChange={onLocaleChange}
      />,
    )

    expect(
      screen.getByRole('heading', { name: 'Interface language' }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('heading', { level: 1, name: 'Today' }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'Download Android demo' }),
    ).toHaveAttribute('href', '/downloads/global-english-demo.apk')

    await user.click(screen.getByRole('button', { name: 'Continue' }))
    await user.click(
      screen.getByRole('radio', { name: 'Speak with confidence at work' }),
    )
    await user.click(screen.getByRole('button', { name: 'Continue' }))
    await user.click(screen.getByRole('radio', { name: /I am just starting/ }))
    await user.click(screen.getByRole('button', { name: 'Continue' }))

    expect(
      screen.getByRole('heading', { level: 1, name: 'Today' }),
    ).toBeInTheDocument()
  })
})
