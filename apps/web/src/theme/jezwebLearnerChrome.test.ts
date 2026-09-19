import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const themeDir = dirname(fileURLToPath(import.meta.url))

describe('Jezweb Variant C learner chrome', () => {
  it('loads after Variant A chrome and keeps the five-destination flow', () => {
    const jezweb = readFileSync(resolve(themeDir, 'jezwebLearnerChrome.css'), 'utf8')
    const app = readFileSync(resolve(themeDir, '../App.tsx'), 'utf8')
    const appStyles = readFileSync(resolve(themeDir, '../App.css'), 'utf8')
    const androidDownload = readFileSync(
      resolve(themeDir, '../marketing/androidDownload.ts'),
      'utf8',
    )
    const colors = readFileSync(
      resolve(
        themeDir,
        '../../android-app/app/src/main/res/values/colors.xml',
      ),
      'utf8',
    )
    const styles = readFileSync(
      resolve(
        themeDir,
        '../../android-app/app/src/main/res/values/styles.xml',
      ),
      'utf8',
    )
    const network = readFileSync(
      resolve(
        themeDir,
        '../../android-app/app/src/main/res/xml/network_security_config.xml',
      ),
      'utf8',
    )

    expect(jezweb).toContain('Variant C jezweb-ux')
    expect(jezweb).toContain('LessonPractice / audio curriculum files stay untouched')
    expect(jezweb).toContain('grid-template-columns: repeat(5, minmax(0, 1fr))')
    expect(jezweb).toContain('No Speak FAB')
    expect(jezweb).toContain('Speak / roleplay surfaces must stay readable on Android dark-mode WebViews.')
    expect(jezweb).toContain('color-scheme: light')
    expect(jezweb).toContain('.conversation-journey__notice')
    expect(jezweb).toContain("button[aria-selected='true']")
    expect(jezweb).toContain('var(--ge-color-action-text, #ffffff)')
    expect(jezweb).toContain('ge-application-frame--native-android')
    expect(jezweb).toContain('24px')
    expect(jezweb).toContain('lift bottom tabs above gesture/home indicators')
    expect(jezweb).toContain('text-wrap: balance')
    expect(jezweb).toContain('repeat(auto-fit, minmax(3.75rem, 1fr))')
    expect(jezweb).toContain('border-radius: 0.75rem')
    expect(jezweb).toContain('ge-primary-navigation__label')
    expect(jezweb).toContain('background: #ffffff')
    expect(jezweb).toContain('color: #1a1a1a')
    expect(jezweb).toContain('max-height: 32rem')
    expect(jezweb).toContain('text-wrap: nowrap')
    expect(appStyles).toMatch(
      /input\[type='checkbox'\]\s*\{[^}]*inline-size:\s*24px;[^}]*block-size:\s*24px;/su,
    )
    expect(appStyles).toMatch(
      /label:has\(input\[type='checkbox'\]\)\s*\{[^}]*min-block-size:\s*var\(--ge-target-minimum\);/su,
    )
    expect(jezweb).toMatch(
      /\.learner-home__title:focus-visible\s*\{\s*outline: none;/u,
    )
    expect(jezweb).not.toContain('level-path--variant-a')

    expect(app).toContain("import './theme/jezwebLearnerChrome.css'")
    expect(app).toContain('ge-application-frame--jezweb')
    expect(app).toContain('variant-c-jezweb')

    const chromeImport = app.indexOf("import './theme/miraLearnerChrome.css'")
    const jezwebImport = app.indexOf("import './theme/jezwebLearnerChrome.css'")
    expect(chromeImport).toBeGreaterThan(-1)
    expect(jezwebImport).toBeGreaterThan(chromeImport)

    expect(androidDownload).toMatch(/\?v=jezweb-c\d+'/u)
    expect(colors).toContain('#FAFBFA')
    expect(colors).toContain('#006B3F')
    expect(styles).toContain('windowLightStatusBar')
    expect(network).toContain('117.254.108.143')
  })
})
