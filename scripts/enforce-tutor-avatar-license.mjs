import { existsSync, readFileSync, rmSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const repositoryRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const webRoot = resolve(repositoryRoot, 'apps/web')
const outputName = process.argv[2] ?? 'dist'

if (!/^[a-z0-9-]+$/u.test(outputName)) {
  throw new Error('Avatar bundle output must be a simple directory name.')
}

const outputRoot = resolve(webRoot, outputName)
if (!outputRoot.startsWith(`${webRoot}/`)) {
  throw new Error('Avatar bundle output escaped the web workspace.')
}

const sourceLicense = resolve(
  webRoot,
  'public/tutor/LICENSE-tutor-avatars.txt',
)
const bundledTutorRoot = resolve(outputRoot, 'tutor')

// A production Android web bundle starts from the same public directory as the
// website. Never package the website's downloadable APK inside the APK being
// assembled. Website and soft-launch builds must keep the download artifact.
const androidWebBundle =
  process.env.GE_ANDROID_WEB_BUNDLE === '1' ||
  process.env.CAPACITOR_APP === 'production'
if (androidWebBundle) {
  remove(
    resolve(outputRoot, 'downloads/global-spoken-english-android.apk'),
  )
  process.stdout.write(
    'Android bundle: excluded the website APK download artifact.\n',
  )
}

// Incoming downloads are an operator staging area and are never web assets.
remove(resolve(bundledTutorRoot, 'avatars/incoming'))

const completedLicense =
  existsSync(sourceLicense) &&
  isCompletedTutorAvatarLicense(readFileSync(sourceLicense, 'utf8'))

if (!completedLicense) {
  remove(resolve(bundledTutorRoot, 'avatars'))
  remove(resolve(bundledTutorRoot, 'LICENSE-tutor-avatars.txt'))
  process.stdout.write(
    'Tutor avatar bundle: excluded models because no completed public license grant exists.\n',
  )
} else {
  process.stdout.write(
    'Tutor avatar bundle: completed grant found; licensed manifest assets retained.\n',
  )
}

function remove(path) {
  if (existsSync(path)) {
    rmSync(path, { force: true, recursive: true })
  }
}

function isCompletedTutorAvatarLicense(text) {
  if (text.includes('Fill this file after purchase')) {
    return false
  }

  const requiredFields = [
    'Vendor / store',
    'Product names / SKUs',
    'License type (commercial app / redistribution / modification)',
    'Licensed to',
  ]

  return requiredFields.every((field) => {
    const line = text
      .split(/\r?\n/u)
      .find((candidate) => candidate.trimStart().startsWith(`${field}:`))
    return line !== undefined && line.slice(line.indexOf(':') + 1).trim() !== ''
  })
}
