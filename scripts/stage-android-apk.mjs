import { copyFileSync, mkdirSync, readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const release = JSON.parse(readFileSync(resolve(root, 'apps/web/src/appRelease.json'), 'utf8'))
if (!/^\d+\.\d+\.\d+$/u.test(release.versionName) || !Number.isSafeInteger(release.versionCode) || release.versionCode < 1) {
  throw new Error('Invalid Android release version')
}
const apk = resolve(root, 'apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk')
const downloads = resolve(root, 'apps/web/public/downloads')
mkdirSync(downloads, { recursive: true })
const filename = `global-spoken-english-${release.versionName}.apk`
copyFileSync(apk, resolve(downloads, filename))
// Keep previously shared links working; new links always use the versioned file.
copyFileSync(apk, resolve(downloads, 'global-spoken-english-android.apk'))
console.log(`Staged ${filename}`)
