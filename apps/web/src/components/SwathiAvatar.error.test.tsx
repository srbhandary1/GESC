import { AvatarGesture } from '@global-english/contracts'
import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { CoachPreferenceProvider } from '../coach/CoachPreferenceContext'

vi.mock('@global-english/contracts', async () => {
  const actual =
    await vi.importActual<typeof import('@global-english/contracts')>(
      '@global-english/contracts',
    )
  return {
    ...actual,
    normalizeVisemeTimestamps: () => {
      throw new Error('viseme rig failed')
    },
  }
})

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
  vi.resetModules()
})

describe('SwathiAvatar error boundary', () => {
  it('falls back to the independent static badge when the viseme rig throws', async () => {
    const consoleError = vi
      .spyOn(console, 'error')
      .mockImplementation(() => undefined)
    const { SwathiAvatar } = await import('./SwathiAvatar')
    const { container } = render(
      <CoachPreferenceProvider>
        <SwathiAvatar
          locale="en-IN"
          localizationPolicy="development-preview"
          payload={{
            audio_url: 'blob:reply',
            viseme_timestamps: [{ mouth_shape: 'open', timestamp_ms: 0 }],
            avatar_gesture_trigger: AvatarGesture.ENCOURAGING_NOD,
          }}
        />
      </CoachPreferenceProvider>,
    )

    expect(screen.getByTestId('interactive-avatar-fallback')).toBeInTheDocument()
    expect(container.querySelector('[data-testid="interactive-avatar"]')).toBeNull()
    expect(screen.getByText('Swathi')).toBeInTheDocument()
    consoleError.mockRestore()
  })
})
