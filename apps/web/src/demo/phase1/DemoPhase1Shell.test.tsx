import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { DemoPhase1Shell } from './DemoPhase1Shell'

vi.mock('../demoTts', () => ({
  speakDemoText: vi.fn().mockResolvedValue('azure'),
  stopDemoSpeech: vi.fn(),
  demoAzureTtsConfigured: vi.fn().mockResolvedValue(true),
}))

const missingAuthoredAudio = async () => null

afterEach(() => {
  cleanup()
  window.history.replaceState(null, '', '/')
})

describe('DemoPhase1Shell', () => {
  it('opens the dashboard with five module entries', () => {
    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
        resolveAuthoredLevelAudio={missingAuthoredAudio}
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
      screen.getByRole('button', { name: /Daily Activity.*Open module/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /My Levels/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /Notice Board/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', { level: 2, name: 'Today' }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /Open today's Daily Activity/i }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', {
        name: /Continue current level · listen/i,
      }),
    ).toBeInTheDocument()
  })

  it('navigates to attendance, AI teacher, daily quiz, levels and notices', async () => {
    const user = userEvent.setup()

    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
        resolveAuthoredLevelAudio={missingAuthoredAudio}
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
      screen.getByText(/You are on Level 1/),
    ).toBeInTheDocument()

    await user.click(screen.getByRole('link', { name: 'Daily Activity' }))
    expect(screen.getByText("Today's 5-mark check")).toBeInTheDocument()
    expect(screen.getAllByRole('radio').length).toBe(15)

    await user.click(screen.getByRole('link', { name: 'My Levels' }))
    expect(
      screen.getByRole('button', { name: /Pre-Level 1/i }),
    ).toBeInTheDocument()
    expect(screen.getByText('Content Room')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: /Level 17/i }))
    await user.click(screen.getByRole('button', { name: 'Audio Lessons' }))
    expect(
      await screen.findByText('Model audio not yet loaded'),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Hear scripted stand-in' }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Listen to this level' }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Train with Swathi' }),
    ).toBeInTheDocument()

    await user.click(screen.getByRole('link', { name: 'Notice Board' }))
    expect(
      screen.getByText('Welcome to the Phase 1 demo pathway'),
    ).toBeInTheDocument()
  })

  it('renders Kannada UI chrome and AI replies under kn-IN', async () => {
    const user = userEvent.setup()

    render(
      <DemoPhase1Shell
        locale="kn-IN"
        localizationPolicy="development-preview"
        resolveAuthoredLevelAudio={missingAuthoredAudio}
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
      screen.getByText(/ನೀವು Level 1 ರಲ್ಲಿದ್ದೀರಿ/),
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

    render(
      <DemoPhase1Shell
        locale="hi-IN"
        localizationPolicy="development-preview"
        resolveAuthoredLevelAudio={missingAuthoredAudio}
      />,
    )

    expect(
      screen.getByRole('heading', { level: 1, name: 'डैशबोर्ड' }),
    ).toBeInTheDocument()
    await user.click(screen.getByRole('link', { name: 'AI शिक्षक' }))
    expect(screen.getByText(/आप Level 1 पर हैं/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'हिन्दी' })).toHaveClass(
      'is-active',
    )
  })

  it('asks for a learner session when private media returns unauthenticated', async () => {
    const user = userEvent.setup()
    const onRequestSignIn = vi.fn()

    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
        onRequestSignIn={onRequestSignIn}
        resolveAuthoredLevelAudio={async () => ({
          status: 'unauthenticated',
        })}
      />,
    )

    await user.click(screen.getByRole('link', { name: 'My Levels' }))
    await user.click(screen.getByRole('button', { name: 'Audio Lessons' }))
    const signIn = await screen.findByRole('button', {
      name: 'Sign in to hear authored audio',
    })
    expect(signIn).toBeInTheDocument()
    expect(
      screen.getByText(
        'This host is up. Private lesson audio needs a learner session. Scripted stand-in is not the authored file.',
      ),
    ).toBeInTheDocument()
    await user.click(signIn)
    expect(onRequestSignIn).toHaveBeenCalledOnce()
    expect(
      screen.getByRole('button', { name: 'Hear scripted stand-in' }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Listen to this level' }),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Train with Swathi' }),
    ).toBeInTheDocument()
  })

  it('plays an authored file when the drop-in contract resolves one', async () => {
    const user = userEvent.setup()

    render(
      <DemoPhase1Shell
        locale="en-IN"
        localizationPolicy="development-preview"
        resolveAuthoredLevelAudio={async () => ({
          levelId: 'level-1',
          url: 'https://example.test/level-1.mp3',
          mimeType: 'audio/mpeg',
          file: 'level-1.mp3',
          source: 'manifest',
        })}
      />,
    )

    await user.click(screen.getByRole('link', { name: 'My Levels' }))
    await user.click(screen.getByRole('button', { name: 'Audio Lessons' }))
    expect(
      await screen.findByText('Authored model audio'),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: 'Listen to this level' }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: 'Hear scripted stand-in' }),
    ).not.toBeInTheDocument()
    expect(screen.getByTestId('phase1-level-audio')).toHaveAttribute(
      'src',
      'https://example.test/level-1.mp3',
    )
    expect(
      screen.getByRole('button', { name: 'Train with Swathi' }),
    ).toBeInTheDocument()
  })
})
