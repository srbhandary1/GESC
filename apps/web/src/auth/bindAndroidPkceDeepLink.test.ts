import { describe, expect, it, vi } from 'vitest'
import {
  bindAndroidPkceDeepLink,
  isOauthCallbackUrl,
} from './bindAndroidPkceDeepLink'
import type { AndroidPkceAuthentication } from './AuthenticationAdapter'

describe('bindAndroidPkceDeepLink', () => {
  it('accepts only the registered production redirect target', () => {
    expect(
      isOauthCallbackUrl(
        'com.globalenglish.app:/oauth/callback?code=a&state=b',
      ),
    ).toBe(true)
    expect(
      isOauthCallbackUrl('com.globalenglish.demo:/oauth/callback?code=a'),
    ).toBe(false)
  })

  it('completes authorization from a deep-link bridge event', async () => {
    const completeAuthorization = vi.fn(async () => ({
      status: 'authenticated' as const,
      identity: {
        user: { id: '01JUSER0000000000000000001' },
        current_membership: null,
        memberships: [],
      },
    }))
    const authentication = {
      transport: 'passport-pkce',
      beginAuthorization: vi.fn(),
      completeAuthorization,
      current: vi.fn(),
      logout: vi.fn(),
      refresh: vi.fn(),
    } satisfies AndroidPkceAuthentication

    let listener: ((event: { url: string }) => void) | undefined
    const onAuthenticated = vi.fn()

    const dispose = await bindAndroidPkceDeepLink(
      authentication,
      onAuthenticated,
      undefined,
      {
        addListener: async (_name, next) => {
          listener = next

          return {
            remove: async () => {
              listener = undefined
            },
          }
        },
      },
    )

    listener?.({
      url: 'com.globalenglish.app:/oauth/callback?code=auth-code&state=xyz',
    })

    await vi.waitFor(() => {
      expect(completeAuthorization).toHaveBeenCalledWith(
        'com.globalenglish.app:/oauth/callback?code=auth-code&state=xyz',
      )
      expect(onAuthenticated).toHaveBeenCalled()
    })

    dispose()
  })
})
