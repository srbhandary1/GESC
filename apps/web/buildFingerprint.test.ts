import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { afterEach, describe, expect, it } from 'vitest'

import { calculateWebBuildFingerprint } from './buildFingerprint.ts'

const temporaryRoots: string[] = []

afterEach(() => {
  for (const root of temporaryRoots.splice(0)) {
    rmSync(root, { force: true, recursive: true })
  }
})

function fixtureRoot(): string {
  const root = mkdtempSync(join(tmpdir(), 'ge-web-build-fingerprint-'))
  temporaryRoots.push(root)
  mkdirSync(join(root, 'apps/web/src'), { recursive: true })
  mkdirSync(join(root, 'apps/web/public'), { recursive: true })
  writeFileSync(join(root, 'package.json'), '{"private":true}\n')
  writeFileSync(join(root, 'apps/web/src/App.tsx'), 'export const app = 1\n')
  writeFileSync(join(root, 'apps/web/public/service-worker.js'), 'version one\n')

  return root
}

describe('web build fingerprint', () => {
  it('is stable until a served source or public asset changes', () => {
    const root = fixtureRoot()
    const first = calculateWebBuildFingerprint(root)

    expect(first).toMatch(/^[a-f0-9]{64}$/)
    expect(calculateWebBuildFingerprint(root)).toBe(first)

    writeFileSync(join(root, 'apps/web/src/App.tsx'), 'export const app = 2\n')
    expect(calculateWebBuildFingerprint(root)).not.toBe(first)

    const sourceChanged = calculateWebBuildFingerprint(root)
    writeFileSync(join(root, 'apps/web/public/service-worker.js'), 'version two\n')
    expect(calculateWebBuildFingerprint(root)).not.toBe(sourceChanged)
  })
})
