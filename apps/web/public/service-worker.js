const CACHE_PREFIX = 'global-english-public-shell-'
const CACHE_NAME = `${CACHE_PREFIX}v5`
const FORBIDDEN_PATH_PREFIXES = [
  '/api/',
  '/sanctum/',
  '/oauth/',
  '/auth/mobile/',
  '/password-establishment.html',
  '/password-recovery.html',
]

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => cache.add(new Request('/', { cache: 'reload' })))
      .then(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((names) =>
        Promise.all(
          names
            .filter(
              (name) =>
                name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME,
            )
            .map((name) => caches.delete(name)),
        ),
      )
      .then(() => self.clients.claim()),
  )
})

self.addEventListener('message', (event) => {
  if (event.data?.type === 'GET_OFFLINE_SHELL_VERSION') {
    event.source?.postMessage({
      type: 'OFFLINE_SHELL_VERSION',
      version: 'v5',
    })
    return
  }

  if (
    event.data?.type !== 'CACHE_PUBLIC_SHELL' ||
    !Array.isArray(event.data.urls)
  ) {
    return
  }

  const urls = event.data.urls.filter(isCacheablePath)
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) =>
        Promise.all(
          urls.map((url) =>
            cache.add(new Request(url, { cache: 'reload' })),
          ),
        ),
      ),
  )
})

self.addEventListener('fetch', (event) => {
  const request = event.request
  const url = new URL(request.url)

  if (
    request.method !== 'GET' ||
    url.origin !== self.location.origin ||
    !isCacheablePath(url.pathname)
  ) {
    return
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/')),
    )
    return
  }

  if (
    !['script', 'style', 'font', 'image'].includes(
      request.destination,
    )
  ) {
    return
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached !== undefined) {
        return cached
      }

      return fetch(request).then((response) => {
        if (response.ok && response.type === 'basic') {
          const copy = response.clone()
          void caches.open(CACHE_NAME).then((cache) => {
            void cache.put(request, copy)
          })
        }

        return response
      })
    }),
  )
})

function isCacheablePath(value) {
  let pathname

  try {
    pathname = new URL(value, self.location.origin).pathname
  } catch {
    return false
  }

  return !FORBIDDEN_PATH_PREFIXES.some((prefix) =>
    pathname.startsWith(prefix),
  )
}
