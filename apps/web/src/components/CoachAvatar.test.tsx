import { AvatarGesture } from '@global-english/contracts'
import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { CoachAvatar } from './CoachAvatar'

afterEach(() => {
  cleanup()
})

describe('CoachAvatar', () => {
  it('renders the selected realistic female coach with a custom device name', () => {
    const { container } = render(
      <CoachAvatar
        coachGender="female"
        coachName="Meera"
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(container.querySelector('[data-coach]')).toHaveAttribute(
      'data-coach',
      'female',
    )
    expect(container.querySelector('[data-testid="interactive-avatar-mouth"]')).not.toBeNull()
    expect(screen.getByText('Meera')).toBeInTheDocument()
  })

  it('renders the male coach and animated mouth payload', () => {
    const audio = { currentTime: 0, paused: false, ended: false } as HTMLAudioElement
    const { container } = render(
      <CoachAvatar
        audio={audio}
        coachGender="male"
        locale="en-IN"
        localizationPolicy="development-preview"
        mood="speaking"
        payload={{
          viseme_timestamps: [{ mouth_shape: 'open', timestamp_ms: 0 }],
          avatar_gesture_trigger: AvatarGesture.WARM_SMILE,
        }}
      />,
    )

    expect(container.querySelector('[data-coach]')).toHaveAttribute(
      'data-coach',
      'male',
    )
    expect(container.querySelector('[data-mouth-shape]')).not.toBeNull()
    expect(container.querySelector('[data-gesture]')).toHaveAttribute(
      'data-gesture',
      AvatarGesture.WARM_SMILE,
    )
    expect(container.querySelector('[data-presence]')).toHaveAttribute(
      'data-presence',
      'speaking',
    )
    expect(screen.getByText('Arjun')).toBeInTheDocument()
  })
})
