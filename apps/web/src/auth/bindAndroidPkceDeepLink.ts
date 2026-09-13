import type {
  AndroidPkceAuthentication,
  AuthenticationSnapshot,
} from './AuthenticationAdapter'

export interface AndroidDeepLinkBridge {
  getLaunchUrl?: () => Promise<{ url?: string } | undefined>
  addListener: (
    eventName: 'appUrlOpen',
    listener: (event: { url: string }) => void,
  ) => Promise<{ remove: () => Promise<void> }> | { remove: () => Promise<void> }
}

const OAUTH_CALLBACK_PREFIX = 'com.globalenglish.app:/oauth/callback'

/**
 * Wires system-browser OAuth return URLs into the shared PKCE adapter.
 * Uses a project-owned bridge event when `@capacitor/app` is unavailable.
 */
export async function bindAndroidPkceDeepLink(
  authentication: AndroidPkceAuthentication,
  onAuthenticated: (snapshot: AuthenticationSnapshot) => void,
  onError?: (error: unknown) => void,
  bridge: AndroidDeepLinkBridge = createDefaultDeepLinkBridge(),
): Promise<() => void> {
  const handledCallbackStates = new Set<string>()

  const handleUrl = async (url: string): Promise<void> => {
    if (!isOauthCallbackUrl(url)) {
      return
    }

    const callbackState = callbackStateKey(url)

    // MainActivity intentionally redelivers a cold-start callback while the
    // WebView listener becomes ready. Consume each OAuth state only once so a
    // successful first delivery cannot be overwritten by a duplicate error.
    if (handledCallbackStates.has(callbackState)) {
      return
    }

    handledCallbackStates.add(callbackState)

    try {
      const snapshot = await authentication.completeAuthorization(url)
      onAuthenticated(snapshot)
    } catch (error) {
      onError?.(error)
    }
  }

  const listener = await bridge.addListener('appUrlOpen', (event) => {
    void handleUrl(event.url)
  })

  const launch = await bridge.getLaunchUrl?.()

  if (typeof launch?.url === 'string') {
    await handleUrl(launch.url)
  }

  return () => {
    void listener.remove()
  }
}

export function isOauthCallbackUrl(url: string): boolean {
  return (
    url === OAUTH_CALLBACK_PREFIX ||
    url.startsWith(`${OAUTH_CALLBACK_PREFIX}?`)
  )
}

function callbackStateKey(url: string): string {
  try {
    const callback = new URL(url)
    const states = callback.searchParams.getAll('state')

    return states.length === 1
      ? `state:${states[0]}`
      : 'malformed-state'
  } catch {
    return 'malformed-url'
  }
}

function createDefaultDeepLinkBridge(): AndroidDeepLinkBridge {
  return {
    getLaunchUrl: async () => {
      const callbackGlobal = globalThis as typeof globalThis & {
        __globalEnglishPendingOauthCallback?: unknown
      }
      const url = callbackGlobal.__globalEnglishPendingOauthCallback
      delete callbackGlobal.__globalEnglishPendingOauthCallback

      return typeof url === 'string' ? { url } : undefined
    },
    addListener: async (_eventName, listener) => {
      const handler = (event: Event): void => {
        const callbackGlobal = globalThis as typeof globalThis & {
          __globalEnglishPendingOauthCallback?: unknown
        }
        const detail = (event as CustomEvent<{ url?: string }>).detail
        const url =
          typeof detail === 'string'
            ? detail
            : typeof detail?.url === 'string'
              ? detail.url
              : null

        if (url !== null) {
          delete callbackGlobal.__globalEnglishPendingOauthCallback
          listener({ url })
        }
      }

      globalThis.addEventListener('ge:oauth-callback', handler)

      return {
        remove: async () => {
          globalThis.removeEventListener('ge:oauth-callback', handler)
        },
      }
    },
  }
}
