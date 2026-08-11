const SERVICE_WORKER_PATH = '/service-worker.js'
const PUBLIC_SHELL_CACHE_PREFIX = 'global-english-public-shell-'

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

    const registration =
      await navigator.serviceWorker.register(SERVICE_WORKER_PATH, {
        scope: '/',
      })
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
        !url.pathname.startsWith('/api/') &&
        !url.pathname.startsWith('/sanctum/')
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

async function disableDevelopmentOfflineShell(): Promise<void> {
  const registrations = await navigator.serviceWorker.getRegistrations()
  await Promise.all(
    registrations
      .filter((registration) => {
        const worker =
          registration.active ??
          registration.waiting ??
          registration.installing

        return (
          worker !== null &&
          new URL(worker.scriptURL).pathname === SERVICE_WORKER_PATH
        )
      })
      .map((registration) => registration.unregister()),
  )

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
