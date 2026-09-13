import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const themeDir = dirname(fileURLToPath(import.meta.url))

describe('Mira learner chrome', () => {
  it('loads after the GESC-green pin and does not rewrite lesson files', () => {
    const chrome = readFileSync(resolve(themeDir, 'miraLearnerChrome.css'), 'utf8')
    const app = readFileSync(resolve(themeDir, '../App.tsx'), 'utf8')
    const nav = readFileSync(
      resolve(
        themeDir,
        '../../../../packages/design-system/src/navigation/PrimaryNavigation.tsx',
      ),
      'utf8',
    )

    expect(chrome).toContain('#006b3f')
    expect(chrome).toContain('ge-primary-navigation__link--speak')
    expect(chrome).toContain('mira-home-hero')
    expect(chrome).toContain('LessonPractice / audio curriculum files stay untouched')
    expect(chrome).toContain('max(3rem, 48px)')
    expect(chrome).toContain('--ge-color-soft-brand: #e6f2ec')
    expect(chrome).not.toContain('level-path--variant-a')
    expect(chrome).toContain('#1a1a1a')
    expect(chrome).toContain('#fafbfa')

    expect(nav).toContain("'speak'")
    expect(nav).toContain("'tuition'")
    expect(nav).toContain('ge-primary-navigation__link--speak')

    const pinImport = app.indexOf("import './theme/miraGescGreenPin.css'")
    const chromeImport = app.indexOf("import './theme/miraLearnerChrome.css'")
    const jezwebImport = app.indexOf("import './theme/jezwebLearnerChrome.css'")
    expect(pinImport).toBeGreaterThan(-1)
    expect(chromeImport).toBeGreaterThan(pinImport)
    expect(jezwebImport).toBeGreaterThan(chromeImport)
    expect(app).toContain('ge-application-frame--mira-learner')
    expect(app).toContain('ge-application-frame--jezweb')
    expect(app).toContain('shell-locale-note')
    expect(app).not.toContain('className="shell-preview"')

    const skipStyles = readFileSync(
      resolve(
        themeDir,
        '../../../../packages/design-system/src/styles/components.css',
      ),
      'utf8',
    )
    expect(skipStyles).toContain('clip-path: inset(50%)')
    expect(skipStyles).toContain('.ge-skip-link:focus-visible')
  })
})
