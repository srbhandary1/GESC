const SERVICE_WORKER_PATH = '/service-worker.js'
const PUBLIC_SHELL_CACHE_PREFIX = 'global-english-public-shell-'
const PUBLIC_SHELL_RELEASE = import.meta.env.VITE_PUBLIC_SHELL_RELEASE
const AUTHENTICATED_PATH_PREFIXES = [
  '/api/',
  '/sanctum/',
  '/oauth/',
  '/auth/mobile/',
] as const

export async function registerOfflineShell(): Promise<void> {
  if (
    !('serviceWorker' in navigator) ||
    import.meta.env.MODE === 'test'
  ) {
    return
  }

  try {
    if (import.meta.env.DEV) {
      await disableDevelopmentOfflineShell()

      return
    }

    await retireLegacyOfflineShellWorkers()

    const registration =
      await navigator.serviceWorker.register(serviceWorkerPath(), {
        scope: '/',
        updateViaCache: 'none',
      })
    await registration.update()
    const worker =
      registration.active ??
      registration.waiting ??
      registration.installing

    if (worker === null) {
      return
    }

    const urls = new Set<string>(['/'])

    for (const entry of performance.getEntriesByType('resource')) {
      const url = new URL(entry.name)

      if (
        url.origin === location.origin &&
        !AUTHENTICATED_PATH_PREFIXES.some((prefix) =>
          url.pathname.startsWith(prefix),
        )
      ) {
        urls.add(`${url.pathname}${url.search}`)
      }
    }

    worker.postMessage({
      type: 'CACHE_PUBLIC_SHELL',
      urls: [...urls],
    })
  } catch {
    // Offline availability is progressive enhancement. The visible learner
    // state reports unavailability without logging browser or identity data.
  }
}

export function serviceWorkerPath(
  release: string = PUBLIC_SHELL_RELEASE,
): string {
  if (!/^[a-f0-9]{64}$/.test(release)) {
    return SERVICE_WORKER_PATH
  }

  // A new build gets a new script URL, so the browser cannot keep an older
  // public shell worker merely because the static source filename is stable.
  return `${SERVICE_WORKER_PATH}?release=${release}`
}

async function disableDevelopmentOfflineShell(): Promise<void> {
  await unregisterOfflineShellWorkers(() => true)
}

async function retireLegacyOfflineShellWorkers(): Promise<void> {
  await unregisterOfflineShellWorkers((scriptUrl) => {
    const release = scriptUrl.searchParams.get('release')

    return release === null || !/^[a-f0-9]{64}$/.test(release)
  })
}

async function unregisterOfflineShellWorkers(
  shouldUnregister: (scriptUrl: URL) => boolean,
): Promise<void> {
  const registrations = await navigator.serviceWorker.getRegistrations()
  const retired = registrations.filter((registration) => {
    const worker =
      registration.active ?? registration.waiting ?? registration.installing

    if (worker === null) {
      return false
    }

    const scriptUrl = new URL(worker.scriptURL)

    return (
      scriptUrl.pathname === SERVICE_WORKER_PATH &&
      shouldUnregister(scriptUrl)
    )
  })

  if (retired.length === 0) {
    return
  }

  await Promise.all(retired.map((registration) => registration.unregister()))

  if (!('caches' in globalThis)) {
    return
  }

  const cacheNames = await caches.keys()
  await Promise.all(
    cacheNames
      .filter((name) => name.startsWith(PUBLIC_SHELL_CACHE_PREFIX))
      .map((name) => caches.delete(name)),
  )
}
