import { AvatarGesture } from '@global-english/contracts'
import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { CoachPreferenceProvider } from '../coach/CoachPreferenceContext'
import { SwathiAvatar } from './SwathiAvatar'

afterEach(() => {
  cleanup()
})

describe('SwathiAvatar', () => {
  it('renders the selected realistic coach in its idle state', () => {
    const { container } = render(
      <CoachPreferenceProvider>
        <SwathiAvatar locale="en-IN" localizationPolicy="development-preview" />
      </CoachPreferenceProvider>,
    )

    const avatar = container.querySelector('[data-testid="interactive-avatar"]')
    expect(avatar).toHaveAttribute('data-coach', 'female')
    expect(avatar).toHaveAttribute('data-mouth-shape', 'rest')
    expect(container.querySelector('[data-testid="interactive-avatar-mouth"]')).not.toBeNull()
    expect(screen.getByText('Swathi')).toBeInTheDocument()
  })

  it('renders the mouth rig and gesture when audio and viseme timestamps are valid', () => {
    const audio = { currentTime: 0, paused: false, ended: false } as HTMLAudioElement
    const { container } = render(
      <CoachPreferenceProvider>
        <SwathiAvatar
          audio={audio}
          locale="en-IN"
          localizationPolicy="development-preview"
          payload={{
            audio_url: 'blob:reply',
            viseme_timestamps: [{ mouth_shape: 'open', timestamp_ms: 0 }],
            avatar_gesture_trigger: AvatarGesture.WARM_SMILE,
          }}
        />
      </CoachPreferenceProvider>,
    )

    const avatar = container.querySelector('[data-testid="interactive-avatar"]')
    expect(avatar).not.toBeNull()
    expect(avatar).toHaveAttribute('data-gesture', AvatarGesture.WARM_SMILE)
    expect(container.querySelector('[data-testid="interactive-avatar-mouth"]')).not.toBeNull()
  })
})
