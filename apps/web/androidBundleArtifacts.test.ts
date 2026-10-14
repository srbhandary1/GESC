import { spawnSync } from 'node:child_process'
import { copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, symlinkSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { afterEach, describe, expect, it } from 'vitest'

const roots: string[] = []
const packageNames = ['global-spoken-english-android.apk', 'global-english-android-4dd921d9.apk', 'global-english-android.apk', 'global-english-demo.apk', 'archive/older.APK', 'archive/release.aab', 'release.AAB']

afterEach(() => {
  for (const root of roots.splice(0)) rmSync(root, { recursive: true, force: true })
})

function fixture(output = 'dist') {
  const root = mkdtempSync(join(tmpdir(), 'ge-android-bundle-artifacts-'))
  roots.push(root)
  const script = join(root, 'scripts/enforce-tutor-avatar-license.mjs')
  mkdirSync(dirname(script), { recursive: true })
  copyFileSync(join(dirname(fileURLToPath(import.meta.url)), '../../scripts/enforce-tutor-avatar-license.mjs'), script)
  for (const name of [...packageNames, 'README.md', 'guide.pdf', 'almost.apk.txt']) {
    for (const tree of ['public', output]) {
      const path = join(root, 'apps/web', tree, 'downloads', name)
      mkdirSync(dirname(path), { recursive: true })
      writeFileSync(path, `synthetic fixture: ${name}`)
    }
  }
  return { root, script, output }
}

function run(f: ReturnType<typeof fixture>, env: Record<string, string | undefined> = {}, output = f.output) {
  return spawnSync(process.execPath, [f.script, output], {
    env: { ...process.env, GE_ANDROID_WEB_BUNDLE: '', CAPACITOR_APP: '', ...env },
    encoding: 'utf8',
  })
}

describe('generated Android download artifact filtering', () => {
  it.each([{ GE_ANDROID_WEB_BUNDLE: '1' }, { CAPACITOR_APP: 'production' }])('excludes all APK/AAB names only from Android output (%j)', (env) => {
    const f = fixture()
    const result = run(f, env)
    expect(result.status, result.stderr).toBe(0)
    for (const name of packageNames) {
      expect(existsSync(join(f.root, 'apps/web/dist/downloads', name))).toBe(false)
      expect(readFileSync(join(f.root, 'apps/web/public/downloads', name), 'utf8')).toBe(`synthetic fixture: ${name}`)
    }
    for (const name of ['README.md', 'guide.pdf', 'almost.apk.txt']) {
      expect(readFileSync(join(f.root, 'apps/web/dist/downloads', name), 'utf8')).toBe(`synthetic fixture: ${name}`)
    }
  })

  it.each(['dist', 'dist-demo'])('preserves every website download without Android flags (%s)', (output) => {
    const f = fixture(output)
    expect(run(f).status).toBe(0)
    for (const name of packageNames) {
      expect(existsSync(join(f.root, 'apps/web', output, 'downloads', name))).toBe(true)
      expect(existsSync(join(f.root, 'apps/web/public/downloads', name))).toBe(true)
    }
  })

  it.each(['public', '../public', '.', '/tmp', 'dist/../public'])('rejects a source or escaping output name (%s)', (output) => {
    const f = fixture()
    const result = run(f, { GE_ANDROID_WEB_BUNDLE: '1' }, output)
    expect(result.status).not.toBe(0)
    expect(result.stderr).toContain('never source assets')
    for (const name of packageNames) expect(existsSync(join(f.root, 'apps/web/public/downloads', name))).toBe(true)
  })

  it.each(['output', 'downloads', 'nested'])('refuses a symlink escape through %s and leaves source downloads intact', (location) => {
    const f = fixture()
    const source = join(f.root, 'apps/web/public')
    const link = join(f.root, 'apps/web/dist', location === 'output' ? '' : location === 'downloads' ? 'downloads' : 'downloads/linked')
    if (location !== 'nested') rmSync(link, { recursive: true, force: true })
    symlinkSync(location === 'output' ? source : join(source, 'downloads'), link, 'dir')
    const result = run(f, { GE_ANDROID_WEB_BUNDLE: '1' })
    expect(result.status).not.toBe(0)
    expect(result.stderr).toMatch(/symlink|symbolic links/u)
    for (const name of packageNames) expect(readFileSync(join(source, 'downloads', name), 'utf8')).toBe(`synthetic fixture: ${name}`)
  })
})
