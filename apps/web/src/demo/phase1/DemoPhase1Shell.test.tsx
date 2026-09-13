import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { DemoPhase1Shell } from './DemoPhase1Shell'

vi.mock('../demoTts', () => ({
  speakDemoText: vi.fn().mockResolvedValue('azure'),
  stopDemoSpeech: vi.fn(),
  demoAzureTtsConfigured: vi.fn().mockResolvedValue(true),
}))

afterEach(() => {
  cleanup()
  window.history.replaceState(null, '', '/')
  window.localStorage.removeItem('ge-ui-grove-welcome-dismissed')
})

function dismissFirstRun(): void {
  window.localStorage.setItem('ge-ui-grove-welcome-dismissed', '1')
}

describe('DemoPhase1Shell', () => {
  it('opens Grove first-run before the dashboard', () => {
    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('heading', {
        name: 'What do you want to speak English for?',
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('img', { name: 'Mira' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Skip and open My Levels' }),
    ).toBeInTheDocument()
  })

  it('opens the dashboard with five module entries', () => {
    dismissFirstRun()
    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('heading', { level: 1, name: 'Dashboard' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('navigation', { name: 'Phase 1 modules' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /Attendance/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /AI Teacher/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /Daily Activity/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /My Levels/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /Notice Board/i }),
    ).toBeInTheDocument()
  })

  it('navigates to attendance, AI teacher, daily quiz, levels and notices', async () => {
    const user = userEvent.setup()
    dismissFirstRun()

    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    await user.click(screen.getByRole('link', { name: 'Attendance' }))
    expect(
      screen.getByRole('heading', { level: 1, name: 'Attendance' }),
    ).toBeInTheDocument()
    expect(screen.getByText('75%')).toBeInTheDocument()
    expect(screen.getByText('August 2026')).toBeInTheDocument()

    await user.click(screen.getByRole('link', { name: 'AI Teacher' }))
    expect(
      screen.getByText(
        'Scripted demo replies. Voice uses Azure neural TTS when configured; no generative AI call.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByText(/Welcome\. You are on Level 1/),
    ).toBeInTheDocument()

    await user.click(screen.getByRole('link', { name: 'Daily Activity' }))
    expect(screen.getByText("Today's 5-mark check")).toBeInTheDocument()
    expect(screen.getAllByRole('radio').length).toBe(15)

    await user.click(screen.getByRole('link', { name: 'My Levels' }))
    expect(
      screen.getByRole('button', { name: /Pre-Level 1/i }),
    ).toBeInTheDocument()
    expect(screen.getByText('Introduction')).toBeInTheDocument()
    expect(
      screen.getByRole('heading', { name: 'Can — ability and permission' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'Open level' }),
    ).toHaveAttribute('href', '#learn')
    expect(screen.getByText('Should — advice and suggestions')).toBeInTheDocument()
    expect(screen.queryByText('Phase 1 learner shell')).not.toBeInTheDocument()
    expect(
      screen.queryByText(/Synthetic dashboard for Attendance/),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByText('Reading and phrase notes for today\'s outcomes.'),
    ).not.toBeInTheDocument()

    await user.click(screen.getByRole('link', { name: 'Notice Board' }))
    expect(
      screen.getByText('Welcome to the Phase 1 demo pathway'),
    ).toBeInTheDocument()
  })

  it('renders Kannada UI chrome and AI replies under kn-IN', async () => {
    const user = userEvent.setup()
    dismissFirstRun()

    render(
      <DemoPhase1Shell
        locale="kn-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('heading', { level: 1, name: 'ಡ್ಯಾಶ್‌ಬೋರ್ಡ್' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('navigation', { name: 'Phase 1 ಮಾಡ್ಯೂಲ್‌ಗಳು' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'ಹಾಜರಾತಿ' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'AI ಶಿಕ್ಷಕ' }),
    ).toBeInTheDocument()

    await user.click(screen.getByRole('link', { name: 'AI ಶಿಕ್ಷಕ' }))
    expect(
      screen.getByText(/ಸ್ವಾಗತ\. ನೀವು Level 1 ರಲ್ಲಿದ್ದೀರಿ/),
    ).toBeInTheDocument()
    expect(screen.queryByText(/Svagata|Neevu Level/)).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'ಕನ್ನಡ' })).toHaveClass(
      'is-active',
    )

    await user.click(screen.getByRole('link', { name: 'ಹಾಜರಾತಿ' }))
    expect(screen.getByText('ಆಗಸ್ಟ್ ೨೦೨೬')).toBeInTheDocument()
    expect(screen.getByText('ಗೈರು')).toBeInTheDocument()

    await user.click(screen.getByRole('link', { name: 'ಸೂಚನಾ ಫಲಕ' }))
    expect(
      screen.getByRole('heading', {
        level: 2,
        name: 'Phase 1 ಡೆಮೋ ಮಾರ್ಗಕ್ಕೆ ಸ್ವಾಗತ',
      }),
    ).toBeInTheDocument()
  })

  it('renders Hindi UI chrome when hi-IN is selected', async () => {
    const user = userEvent.setup()
    dismissFirstRun()

    render(
      <DemoPhase1Shell
        locale="hi-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('heading', { level: 1, name: 'डैशबोर्ड' }),
    ).toBeInTheDocument()
    await user.click(screen.getByRole('link', { name: 'AI शिक्षक' }))
    expect(screen.getByText(/स्वागत है। आप Level 1 पर हैं/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'हिन्दी' })).toHaveClass(
      'is-active',
    )
  })

  it('resets the AI reply language when the interface locale changes', async () => {
    const user = userEvent.setup()
    dismissFirstRun()
    const { rerender } = render(
      <DemoPhase1Shell
        locale="kn-IN"
        localizationPolicy="development-preview"
      />,
    )

    await user.click(screen.getByRole('link', { name: 'AI ಶಿಕ್ಷಕ' }))
    await user.click(screen.getByRole('button', { name: 'English' }))
    expect(screen.getByRole('button', { name: 'English' })).toHaveClass(
      'is-active',
    )

    rerender(
      <DemoPhase1Shell
        locale="hi-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(screen.getByRole('button', { name: 'हिन्दी' })).toHaveClass(
      'is-active',
    )
  })
})
