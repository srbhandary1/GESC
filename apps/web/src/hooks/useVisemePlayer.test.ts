import { act, renderHook } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { useVisemePlayer } from './useVisemePlayer'

afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

function installAnimationFrame(): { tick: () => void } {
  let callback: FrameRequestCallback | null = null

  vi.stubGlobal(
    'requestAnimationFrame',
    (next: FrameRequestCallback) => {
      callback = next
      return 1
    },
  )
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

describe('useVisemePlayer', () => {
  it('updates currentMouthShape when the playback clock crosses a viseme', () => {
    const frames = installAnimationFrame()
    const audio = {
      currentTime: 0,
      paused: false,
      ended: false,
    } as HTMLAudioElement

    const { result } = renderHook(() =>
      useVisemePlayer({
        audio,
        visemes: [
          { mouth_shape: 'rest', timestamp_ms: 0 },
          { mouth_shape: 'open', timestamp_ms: 50 },
        ],
      }),
    )

    act(() => {
      frames.tick()
    })
    expect(result.current.currentMouthShape).toBe('rest')

    audio.currentTime = 0.051
    act(() => {
      frames.tick()
    })
    expect(result.current.currentMouthShape).toBe('open')
  })

  it('returns rest when visemes are missing', () => {
    const { result } = renderHook(() =>
      useVisemePlayer({
        audio: { currentTime: 1, paused: false, ended: false } as HTMLAudioElement,
        visemes: [],
      }),
    )

    expect(result.current.currentMouthShape).toBe('rest')
    expect(result.current.isAnimating).toBe(false)
  })
})
