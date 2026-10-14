import { existsSync, lstatSync, readdirSync, readFileSync, rmSync } from 'node:fs'
import { relative, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const repositoryRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const webRoot = resolve(repositoryRoot, 'apps/web')
const outputName = process.argv[2] ?? 'dist'

if (!['dist', 'dist-demo'].includes(outputName)) {
  throw new Error('Avatar bundle output must be dist or dist-demo, never source assets.')
}

const outputRoot = resolve(webRoot, outputName)
if (!outputRoot.startsWith(`${webRoot}/`)) {
  throw new Error('Avatar bundle output escaped the web workspace.')
}
if (existsSync(outputRoot) && (lstatSync(outputRoot).isSymbolicLink() || !lstatSync(outputRoot).isDirectory())) {
  throw new Error('Generated bundle output must be a real directory, not a symlink.')
}

const sourceLicense = resolve(
  webRoot,
  'public/tutor/LICENSE-tutor-avatars.txt',
)
const bundledTutorRoot = resolve(outputRoot, 'tutor')

// A production Android web bundle starts from the same public directory as the
// website. Exclude every downloadable Android package, including historical
// filenames, from the generated Android bundle only. Source downloads and
// website/soft-launch builds retain their artifacts.
const androidWebBundle =
  process.env.GE_ANDROID_WEB_BUNDLE === '1' ||
  process.env.CAPACITOR_APP === 'production'
if (androidWebBundle) {
  removeAndroidDownloads(resolve(outputRoot, 'downloads'))
  process.stdout.write(
    'Android bundle: excluded website APK/AAB download artifacts.\n',
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
  assertGeneratedPath(path)
  if (existsSync(path)) {
    rmSync(path, { force: true, recursive: true })
  }
}

function assertGeneratedPath(path) {
  const child = relative(outputRoot, path)
  if (child === '' || child === '..' || child.startsWith(`..${sep}`)) {
    throw new Error('Bundle filtering may only touch descendants of generated output.')
  }
  let current = outputRoot
  for (const part of child.split(sep)) {
    current = resolve(current, part)
    // Never follow a generated path back into public/ or another directory.
    // A broken symlink also fails closed instead of being mistaken for absence.
    try {
      if (lstatSync(current).isSymbolicLink()) {
        throw new Error('Bundle filtering refuses symbolic links in generated paths.')
      }
    } catch (error) {
      if (error.code === 'ENOENT') return
      throw error
    }
  }
}

function removeAndroidDownloads(directory) {
  assertGeneratedPath(directory)
  if (!existsSync(directory)) return
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const path = resolve(directory, entry.name)
    assertGeneratedPath(path)
    if (entry.isDirectory()) {
      removeAndroidDownloads(path)
    } else if (entry.isFile() && /\.(?:apk|aab)$/iu.test(entry.name)) {
      rmSync(path)
    }
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
