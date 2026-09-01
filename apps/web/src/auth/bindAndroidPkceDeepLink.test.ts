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
      authenticatedFetch: vi.fn(),
      authenticationMode: vi.fn(async () => 'password_only' as const),
      beginAuthorization: vi.fn(),
      completeAuthorization,
      current: vi.fn(),
      enrollWithInvitation: vi.fn(),
      federatedProviders: vi.fn(async () => ({
        google: false,
        facebook: false,
      })),
      beginFederatedSignIn: vi.fn(async () => undefined),
      federatedLinks: vi.fn(async () => ({ google: false, facebook: false })),
      beginFederatedLink: vi.fn(async () => undefined),
      unlinkFederated: vi.fn(async () => undefined),
      logout: vi.fn(),
      onAuthenticationInvalidated: vi.fn(() => () => undefined),
      refresh: vi.fn(),
      requestPasswordRecovery: vi.fn(),
      signIn: vi.fn(),
    } satisfies AndroidPkceAuthentication

    let listener: ((event: { url: string }) => void) | undefined
    const onAuthenticated = vi.fn()
    const onError = vi.fn()

    const dispose = await bindAndroidPkceDeepLink(
      authentication,
      onAuthenticated,
      onError,
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

    const event = {
      url: 'com.globalenglish.app:/oauth/callback?code=auth-code&state=xyz',
    }
    listener?.(event)
    listener?.(event)

    await vi.waitFor(() => {
      expect(completeAuthorization).toHaveBeenCalledWith(
        'com.globalenglish.app:/oauth/callback?code=auth-code&state=xyz',
      )
      expect(onAuthenticated).toHaveBeenCalled()
    })
    expect(completeAuthorization).toHaveBeenCalledTimes(1)
    expect(onAuthenticated).toHaveBeenCalledTimes(1)
    expect(onError).not.toHaveBeenCalled()

    dispose()
  })

  it('registers the listener before consuming a cold-start callback', async () => {
    const events: string[] = []
    const completeAuthorization = vi.fn(async () => ({
      status: 'anonymous' as const,
    }))
    const authentication = {
      transport: 'passport-pkce',
      authenticatedFetch: vi.fn(),
      authenticationMode: vi.fn(async () => 'password_only' as const),
      beginAuthorization: vi.fn(),
      completeAuthorization,
      current: vi.fn(),
      enrollWithInvitation: vi.fn(),
      federatedProviders: vi.fn(async () => ({
        google: false,
        facebook: false,
      })),
      beginFederatedSignIn: vi.fn(async () => undefined),
      federatedLinks: vi.fn(async () => ({ google: false, facebook: false })),
      beginFederatedLink: vi.fn(async () => undefined),
      unlinkFederated: vi.fn(async () => undefined),
      logout: vi.fn(),
      onAuthenticationInvalidated: vi.fn(() => () => undefined),
      refresh: vi.fn(),
      requestPasswordRecovery: vi.fn(),
      signIn: vi.fn(),
    } satisfies AndroidPkceAuthentication

    const dispose = await bindAndroidPkceDeepLink(
      authentication,
      vi.fn(),
      undefined,
      {
        addListener: async () => {
          events.push('listener')

          return { remove: async () => undefined }
        },
        getLaunchUrl: async () => {
          events.push('launch')

          return {
            url: 'com.globalenglish.app:/oauth/callback?code=cold&state=cold-state',
          }
        },
      },
    )

    expect(events).toEqual(['listener', 'launch'])
    expect(completeAuthorization).toHaveBeenCalledTimes(1)
    dispose()
  })
})
