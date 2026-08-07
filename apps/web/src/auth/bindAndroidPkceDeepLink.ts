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
  const handleUrl = async (url: string): Promise<void> => {
    if (!isOauthCallbackUrl(url)) {
      return
    }

    try {
      const snapshot = await authentication.completeAuthorization(url)
      onAuthenticated(snapshot)
    } catch (error) {
      onError?.(error)
    }
  }

  const launch = await bridge.getLaunchUrl?.()

  if (typeof launch?.url === 'string') {
    await handleUrl(launch.url)
  }

  const listener = await bridge.addListener('appUrlOpen', (event) => {
    void handleUrl(event.url)
  })

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

function createDefaultDeepLinkBridge(): AndroidDeepLinkBridge {
  return {
    addListener: async (_eventName, listener) => {
      const handler = (event: Event): void => {
        const detail = (event as CustomEvent<{ url?: string }>).detail
        const url =
          typeof detail === 'string'
            ? detail
            : typeof detail?.url === 'string'
              ? detail.url
              : null

        if (url !== null) {
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
