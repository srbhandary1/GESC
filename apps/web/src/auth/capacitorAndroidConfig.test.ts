import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

describe('Capacitor Android dual-tree configuration', () => {
  it('keeps demo and production package identities in separate trees', () => {
    const source = readFileSync(
      resolve(
        dirname(fileURLToPath(import.meta.url)),
        '../../capacitor.config.ts',
      ),
      'utf8',
    )

    expect(source).toContain("appId: 'com.globalenglish.demo'")
    expect(source).toContain("appId: 'com.globalenglish.app'")
    expect(source).toContain("path: 'android'")
    expect(source).toContain("path: 'android-app'")
    expect(source).toContain("webDir: 'dist-demo'")
    expect(source).toContain("webDir: 'dist'")
    expect(source).toContain("CAPACITOR_APP === 'production'")
    expect(source).toMatch(
      /const demoConfig[\s\S]*appId:\s*'com\.globalenglish\.demo'[\s\S]*webDir:\s*'dist-demo'/u,
    )
    expect(source).toMatch(
      /const productionConfig[\s\S]*appId:\s*'com\.globalenglish\.app'[\s\S]*webDir:\s*'dist'/u,
    )
  })

  it('permits cleartext to the invite-only static IPv4 origins', () => {
    const xml = readFileSync(
      resolve(
        dirname(fileURLToPath(import.meta.url)),
        '../../android-app/app/src/main/res/xml/network_security_config.xml',
      ),
      'utf8',
    )

    expect(xml).toContain('117.254.108.143')
    expect(xml).toContain('192.168.1.4')
    expect(xml).toContain('cleartextTrafficPermitted="true"')
  })
})
