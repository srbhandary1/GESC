import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { speakDemoText } from '../demoTts'
import { DemoPhase1Shell } from './DemoPhase1Shell'
import {
  GROVE_WELCOME_STORAGE_KEY,
  persistFirstRunDismissed,
} from '../../ui/firstRunPreference'

vi.mock('../demoTts', () => ({
  speakDemoText: vi.fn().mockResolvedValue('neural'),
  stopDemoSpeech: vi.fn(),
  demoAzureTtsConfigured: vi.fn().mockResolvedValue(true),
  demoTtsUrl: (path: string) => path,
}))

afterEach(() => {
  cleanup()
  window.history.replaceState(null, '', '/')
  window.localStorage.removeItem(GROVE_WELCOME_STORAGE_KEY)
  vi.unstubAllGlobals()
})

beforeEach(() => {
  persistFirstRunDismissed(GROVE_WELCOME_STORAGE_KEY)
  vi.stubGlobal(
    'fetch',
    vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        configured: false,
        providers: { azure: false, sarvam: false, elevenlabs: false },
      }),
    }),
  )
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
      screen.getByRole('navigation', { name: 'Phase 1 modules' }),
    ).toBeInTheDocument()
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
    expect(screen.getByText('Can')).toBeVisible()
    expect(
      screen.queryByText('I can speak English. Can I help you?'),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('heading', { name: '16 speaking lessons' }),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: /Open Greetings and introductions/ }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Attendance' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'AI Teacher' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Daily Activity' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'My Levels' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Notice Board' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Course levels' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Profile' }),
    ).toBeInTheDocument()
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
    expect(
      screen.getByRole('heading', { name: 'Voice providers' }),
    ).toBeInTheDocument()

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
    expect(screen.getByText('Can')).toBeVisible()
    expect(
      screen.getByText('I can speak English. Can I help you?'),
    ).toBeVisible()
    expect(screen.getByText('Choose the correct sentence.')).toBeVisible()
    expect(screen.getByText('I can speak English.')).toBeVisible()
    expect(screen.getAllByRole('radio').length).toBe(15)

    await user.click(
      screen.getByRole('button', { name: 'Play model audio (demo)' }),
    )
    expect(speakDemoText).toHaveBeenCalledWith(
      'I can speak English. Can I help you?',
      'en-IN',
    )

    await user.click(screen.getByRole('button', { name: 'Back' }))
    await user.click(screen.getByRole('button', { name: 'AI Teacher' }))
    expect(
      screen.getByRole('img', { name: 'AI Teacher' }),
    ).toHaveAttribute('data-avatar-slot', 'ai-teacher')
    expect(screen.getByRole('heading', { name: 'Can' })).toBeVisible()
    expect(
      screen.getAllByText('I can speak English. Can I help you?')[0],
    ).toBeVisible()
    expect(
      screen.queryByText(
        'Scripted demo replies. Voice uses Azure neural TTS when configured; no generative AI call.',
      ),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: "Today’s task" }),
    ).not.toBeInTheDocument()
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

    await user.click(screen.getByRole('button', { name: /Audio Lessons/i }))
    expect(screen.getByRole('heading', { name: 'Can' })).toBeVisible()
    expect(
      screen.getByText('I can speak English. Can I help you?'),
    ).toBeVisible()
    await user.click(screen.getByRole('button', { name: 'Back' }))
    await user.click(screen.getByRole('button', { name: /Level 2 Locked/i }))
    await user.click(screen.getByRole('button', { name: /Audio Lessons/i }))
    expect(screen.getByRole('heading', { name: 'Should' })).toBeVisible()
    expect(screen.getByText('You should drink some water.')).toBeVisible()
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
      screen.getByRole('navigation', { name: 'Phase 1 ಮಾಡ್ಯೂಲ್‌ಗಳು' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /ಇಂದಿನ ಪಾಠ ಮುಂದುವರಿಸಿ/ }),
    ).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'AI ಶಿಕ್ಷಕ' }))
    expect(screen.getByRole('heading', { name: 'Can' })).toBeVisible()
    expect(
      screen.getAllByText('I can speak English. Can I help you?')[0],
    ).toBeVisible()
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
    expect(screen.getByRole('heading', { name: 'Can' })).toBeVisible()
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
      screen.getByRole('button', { name: 'Skip and open My Levels' }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('heading', { level: 1, name: 'Today' }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'Download Android demo' }),
    ).toHaveAttribute('href', '/downloads/global-english-demo.apk')

    await user.click(screen.getByRole('button', { name: 'Save and continue' }))
    await user.click(
      screen.getByRole('radio', { name: 'Speak with confidence at work' }),
    )
    await user.click(screen.getByRole('button', { name: 'Save and continue' }))
    await user.click(screen.getByRole('radio', { name: /I am just starting/ }))
    await user.click(screen.getByRole('button', { name: 'Save and continue' }))

    expect(
      screen.getByRole('heading', { level: 1, name: 'Today' }),
    ).toBeInTheDocument()
  })

  it('skips first-run and opens My Levels', async () => {
    window.localStorage.removeItem(GROVE_WELCOME_STORAGE_KEY)
    const user = userEvent.setup()

    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    await user.click(
      screen.getByRole('button', { name: 'Skip and open My Levels' }),
    )

    expect(
      await screen.findByRole('heading', { level: 1, name: 'My Levels' }),
    ).toBeVisible()
    expect(window.location.hash).toBe('#levels')
    expect(
      screen.getByRole('button', { name: /Pre-Level 1/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByText('Audio Lessons'),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('heading', { level: 1, name: 'Today' }),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByRole('heading', { name: '16 speaking lessons' }),
    ).not.toBeInTheDocument()
  })
})
