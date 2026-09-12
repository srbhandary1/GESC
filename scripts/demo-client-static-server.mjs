import { createReadStream, existsSync, statSync } from 'node:fs'
import http from 'node:http'
import path from 'node:path'
import { handleDemoTtsRequest } from '../apps/web/vite-plugins/demoAzureTtsHandler.mjs'

const [listenHost, listenPortInput, publicHost, rootDirInput, apkPathInput] =
  process.argv.slice(2)
const listenPort = Number(listenPortInput)
const rootDir = path.resolve(rootDirInput ?? '')
const apkPath =
  typeof apkPathInput === 'string' && apkPathInput.length > 0
    ? path.resolve(apkPathInput)
    : null
const apkName = apkPath === null ? null : path.basename(apkPath)
const apkRoute = '/downloads/global-english-demo.apk'

if (
  typeof listenHost !== 'string' ||
  listenHost.length === 0 ||
  typeof publicHost !== 'string' ||
  publicHost.length === 0 ||
  !Number.isInteger(listenPort) ||
  listenPort < 1 ||
  listenPort > 65535 ||
  !existsSync(rootDir) ||
  !statSync(rootDir).isDirectory()
) {
  throw new Error(
    'Usage: node scripts/demo-client-static-server.mjs <listen-host> <listen-port> <public-host> <dist-demo-dir> [apk-path]',
  )
}

if (
  apkPath !== null &&
  (path.extname(apkName).toLowerCase() !== '.apk' ||
    !existsSync(apkPath) ||
    !statSync(apkPath).isFile() ||
    statSync(apkPath).size <= 0)
) {
  throw new Error('The optional Android artifact must be a non-empty APK file.')
}

const apkStat = apkPath === null ? null : statSync(apkPath)
const mimeTypes = new Map([
  ['.css', 'text/css; charset=utf-8'],
  ['.html', 'text/html; charset=utf-8'],
  ['.ico', 'image/x-icon'],
  ['.js', 'text/javascript; charset=utf-8'],
  ['.json', 'application/json; charset=utf-8'],
  ['.map', 'application/json; charset=utf-8'],
  ['.png', 'image/png'],
  ['.svg', 'image/svg+xml'],
  ['.txt', 'text/plain; charset=utf-8'],
  ['.webmanifest', 'application/manifest+json'],
  ['.woff', 'font/woff'],
  ['.woff2', 'font/woff2'],
])

function contentTypeFor(filePath) {
  return mimeTypes.get(path.extname(filePath).toLowerCase()) ?? 'application/octet-stream'
}

function safeResolve(urlPathname) {
  const decoded = decodeURIComponent(urlPathname.split('?')[0] ?? '/')
  const relative = decoded === '/' ? 'index.html' : decoded.replace(/^\/+/, '')
  const resolved = path.resolve(rootDir, relative)
  const rootWithSep = rootDir.endsWith(path.sep) ? rootDir : `${rootDir}${path.sep}`

  if (resolved !== rootDir && !resolved.startsWith(rootWithSep)) {
    return null
  }

  return resolved
}

async function sendFile(response, filePath, method) {
  const stat = statSync(filePath)
  const headers = {
    'cache-control': 'no-store',
    'content-length': String(stat.size),
    'content-type': contentTypeFor(filePath),
    'x-content-type-options': 'nosniff',
  }

  response.writeHead(200, headers)
  if (method === 'HEAD') {
    response.end()
    return
  }

  createReadStream(filePath).pipe(response)
}

const server = http.createServer(async (request, response) => {
  try {
    if (await handleDemoTtsRequest(request, response)) {
      return
    }

    const method = request.method ?? 'GET'
    if (method !== 'GET' && method !== 'HEAD') {
      response.writeHead(405, { allow: 'GET, HEAD', 'content-length': '0' })
      response.end()
      return
    }

    const requestUrl = new URL(
      request.url ?? '/',
      `http://${publicHost}:${listenPort}`,
    )

    if (requestUrl.pathname === apkRoute) {
      if (apkPath === null || apkStat === null || apkName === null) {
        response.writeHead(404, { 'content-length': '0' })
        response.end()
        return
      }

      response.writeHead(200, {
        'cache-control': 'no-store',
        'content-disposition': `attachment; filename="${apkName.replaceAll('"', '')}"`,
        'content-length': String(apkStat.size),
        'content-type': 'application/vnd.android.package-archive',
        'x-content-type-options': 'nosniff',
      })
      if (method === 'HEAD') {
        response.end()
        return
      }

      createReadStream(apkPath).pipe(response)
      return
    }

    if (requestUrl.pathname === '/healthz') {
      const body = 'ok\n'
      response.writeHead(200, {
        'cache-control': 'no-store',
        'content-length': String(Buffer.byteLength(body)),
        'content-type': 'text/plain; charset=utf-8',
      })
      response.end(method === 'HEAD' ? undefined : body)
      return
    }

    let filePath = safeResolve(requestUrl.pathname)
    if (filePath === null) {
      response.writeHead(400, { 'content-length': '0' })
      response.end()
      return
    }

    if (existsSync(filePath) && statSync(filePath).isDirectory()) {
      filePath = path.join(filePath, 'index.html')
    }

    if (!existsSync(filePath) || !statSync(filePath).isFile()) {
      // SPA fallback for client-side routes.
      filePath = path.join(rootDir, 'index.html')
      if (!existsSync(filePath)) {
        response.writeHead(404, { 'content-length': '0' })
        response.end()
        return
      }
    }

    // Tiny HTML rewrite is unnecessary: demo bundle uses relative asset paths.
    await sendFile(response, filePath, method)
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error)
    process.stderr.write(`demo-client-static-server error: ${message}\n`)
    if (!response.headersSent) {
      response.writeHead(500, { 'content-length': '0' })
    }
    response.end()
  }
})

server.listen(listenPort, listenHost, () => {
  process.stdout.write(
    `Serving ${rootDir} on http://${listenHost}:${listenPort} (public http://${publicHost}:${listenPort})\n`,
  )
  if (apkPath !== null) {
    process.stdout.write(`APK download: http://${publicHost}:${listenPort}${apkRoute}\n`)
  }
  process.stdout.write(
    'Demo TTS: GET /demo/tts/status and POST /demo/tts (Azure neural when AZURE_SPEECH_* is set)\n',
  )
})
