import { afterEach, describe, expect, it, vi } from 'vitest'
import { canRenderAvatar3d } from './canRenderAvatar3d'

afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

describe('canRenderAvatar3d', () => {
  it('is false in jsdom without WebGL', () => {
    expect(canRenderAvatar3d()).toBe(false)
  })

  it('is true when ResizeObserver, matchMedia and WebGL exist', () => {
    vi.stubGlobal(
      'ResizeObserver',
      class {
        observe(): void {
          return undefined
        }
        unobserve(): void {
          return undefined
        }
        disconnect(): void {
          return undefined
        }
      },
    )
    vi.stubGlobal(
      'matchMedia',
      () => ({
        matches: false,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
      }),
    )
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(
      {} as WebGLRenderingContext,
    )

    expect(canRenderAvatar3d()).toBe(true)
  })
})
