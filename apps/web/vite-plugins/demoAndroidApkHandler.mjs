import { createReadStream, existsSync, statSync } from 'node:fs'
import path from 'node:path'

export const DEMO_ANDROID_APK_ROUTE = '/downloads/global-english-demo.apk'
export const DEMO_ANDROID_APK_FILENAME = 'global-english-demo.apk'

export function demoAndroidApkPath(webRoot = process.cwd()) {
  return path.resolve(
    webRoot,
    'android/app/build/outputs/apk/debug/app-debug.apk',
  )
}

export function handleDemoAndroidApkRequest(req, res, webRoot = process.cwd()) {
  const url = (req.url ?? '').split('?')[0]
  if (url !== DEMO_ANDROID_APK_ROUTE) {
    return false
  }

  const apkPath = demoAndroidApkPath(webRoot)
  if (!existsSync(apkPath) || !statSync(apkPath).isFile()) {
    res.statusCode = 404
    res.setHeader('cache-control', 'no-store')
    res.end()
    return true
  }

  const stat = statSync(apkPath)
  res.statusCode = 200
  res.setHeader('content-type', 'application/vnd.android.package-archive')
  res.setHeader(
    'content-disposition',
    `attachment; filename="${DEMO_ANDROID_APK_FILENAME}"`,
  )
  res.setHeader('content-length', String(stat.size))
  res.setHeader('cache-control', 'no-store')
  res.setHeader('x-content-type-options', 'nosniff')
  if ((req.method ?? 'GET') === 'HEAD') {
    res.end()
    return true
  }

  createReadStream(apkPath).pipe(res)
  return true
}

export function attachDemoAndroidApkMiddleware(middlewares, webRoot = process.cwd()) {
  middlewares.use((req, res, next) => {
    if (handleDemoAndroidApkRequest(req, res, webRoot)) {
      return
    }
    next()
  })
}
