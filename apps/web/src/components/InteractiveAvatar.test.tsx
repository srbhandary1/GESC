import { AvatarGesture } from '@global-english/contracts'
import { act, cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { InteractiveAvatar } from './InteractiveAvatar'

afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

function installAnimationFrame(): { tick: () => void } {
  let callback: FrameRequestCallback | null = null

  vi.stubGlobal('requestAnimationFrame', (next: FrameRequestCallback) => {
    callback = next
    return 1
  })
  vi.stubGlobal('cancelAnimationFrame', () => {
    callback = null
  })

  return {
    tick: () => {
      const current = callback
      callback = null
      current?.(0)
    },
  }
}

interface FakeAudio {
  currentTime: number
  paused: boolean
  ended: boolean
}

function fakeAudio(overrides: Partial<FakeAudio> = {}): FakeAudio {
  return {
    currentTime: 0,
    paused: false,
    ended: false,
    ...overrides,
  }
}

function asAudioElement(audio: FakeAudio): HTMLAudioElement {
  return audio as unknown as HTMLAudioElement
}

describe('InteractiveAvatar', () => {
  it('renders the idle blink fallback when there is no audio or viseme metadata', () => {
    const { container } = render(<InteractiveAvatar name="Swathi" />)

    const avatar = screen.getByTestId('interactive-avatar')
    expect(avatar).toHaveAttribute('data-renderer', 'svg')
    expect(avatar).toHaveAttribute('data-mouth-shape', 'rest')
    expect(avatar).toHaveAttribute('data-speaking', 'false')
    expect(avatar).toHaveAttribute('data-gesture', 'none')

    const mouth = screen.getByTestId('interactive-avatar-mouth')
    expect(mouth).toBeInTheDocument()

    const blinkingEyes = container.querySelectorAll('.animate-avatar-blink')
    expect(blinkingEyes.length).toBe(2)
    expect(screen.getByText('Swathi')).toBeInTheDocument()
  })

  it('does not crash and stays at rest when the payload lacks viseme metadata', () => {
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => undefined)
    const audio = fakeAudio()

    render(
      <InteractiveAvatar audio={asAudioElement(audio)} visemeTimestamps={[]} />,
    )

    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-mouth-shape',
      'rest',
    )
    expect(errorSpy).not.toHaveBeenCalled()
  })

  it('syncs the mouth shape to the audio playback clock in millisecond steps', () => {
    const frames = installAnimationFrame()
    const audio = fakeAudio()

    render(
      <InteractiveAvatar
        audio={asAudioElement(audio)}
        visemeTimestamps={[
          { mouth_shape: 'rest', timestamp_ms: 0 },
          { mouth_shape: 'open', timestamp_ms: 50 },
          { mouth_shape: 'round', timestamp_ms: 120 },
        ]}
      />,
    )

    act(() => {
      frames.tick()
    })
    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-mouth-shape',
      'rest',
    )

    audio.currentTime = 0.06
    act(() => {
      frames.tick()
    })
    expect(
      screen.getByTestId('interactive-avatar-mouth').getAttribute('d'),
    ).not.toBe('')
    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-mouth-shape',
      'A',
    )
    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-speaking',
      'true',
    )

    audio.currentTime = 0.13
    act(() => {
      frames.tick()
    })
    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-mouth-shape',
      'O',
    )
  })

  it('returns to rest once playback ends', () => {
    const frames = installAnimationFrame()
    const audio = fakeAudio({ currentTime: 0.06 })

    render(
      <InteractiveAvatar
        audio={asAudioElement(audio)}
        visemeTimestamps={[{ mouth_shape: 'open', timestamp_ms: 0 }]}
      />,
    )

    act(() => {
      frames.tick()
    })
    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-mouth-shape',
      'A',
    )

    audio.ended = true
    act(() => {
      frames.tick()
    })
    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-mouth-shape',
      'rest',
    )
    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-speaking',
      'false',
    )
  })

  it('plays the matching gesture animation and then returns to idle', () => {
    vi.useFakeTimers()

    const { rerender } = render(<InteractiveAvatar gesture={null} />)
    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-gesture',
      'none',
    )

    rerender(<InteractiveAvatar gesture={AvatarGesture.WARM_SMILE} />)
    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-gesture',
      AvatarGesture.WARM_SMILE,
    )

    act(() => {
      vi.advanceTimersByTime(700)
    })
    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-gesture',
      'none',
    )

    vi.useRealTimers()
  })

  it('handles unfamiliar mouth-shape codes and null audio without throwing', () => {
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => undefined)
    const frames = installAnimationFrame()
    const audio = fakeAudio({ currentTime: 0.01 })

    render(
      <InteractiveAvatar
        audio={asAudioElement(audio)}
        visemeTimestamps={[{ mouth_shape: 'not-a-real-viseme', timestamp_ms: 0 }]}
      />,
    )

    act(() => {
      frames.tick()
    })

    expect(screen.getByTestId('interactive-avatar')).toHaveAttribute(
      'data-mouth-shape',
      'rest',
    )
    expect(errorSpy).not.toHaveBeenCalled()

    render(<InteractiveAvatar audio={null} visemeTimestamps={null} />)
    expect(errorSpy).not.toHaveBeenCalled()
  })

  it('renders mixed Kannada/Latin names with the configured caption overflow mode', () => {
    render(
      <InteractiveAvatar
        name="ಸ್ವಾತಿ"
        caption="ನಮಸ್ಕಾರ, ಇಂದು ಅಭ್ಯಾಸ ಮಾಡೋಣ! Let's practice speaking today across a very long line."
        captionOverflow="clamp-2"
        locale="kn-IN"
      />,
    )

    const name = screen.getByText('ಸ್ವಾತಿ')
    expect(name.closest('figcaption')).toHaveAttribute('lang', 'kn-IN')

    const caption = screen.getByText(
      /ನಮಸ್ಕಾರ, ಇಂದು ಅಭ್ಯಾಸ ಮಾಡೋಣ/,
    )
    expect(caption.className).toContain('line-clamp-2')
  })
})
