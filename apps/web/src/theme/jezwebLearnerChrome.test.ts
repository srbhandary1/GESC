import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import {
  APP_VERSION,
  ANDROID_APK_FILENAME,
  ANDROID_APK_HREF,
} from '../marketing/androidDownload'

const themeDir = dirname(fileURLToPath(import.meta.url))

describe('Jezweb Variant C learner chrome', () => {
  it('loads after Variant A chrome and keeps the five-destination flow', () => {
    const jezweb = readFileSync(resolve(themeDir, 'jezwebLearnerChrome.css'), 'utf8')
    const app = readFileSync(resolve(themeDir, '../App.tsx'), 'utf8')
    const appStyles = readFileSync(resolve(themeDir, '../App.css'), 'utf8')
    const androidDownload = new URL(ANDROID_APK_HREF, 'https://example.test')
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
    expect(jezweb).toContain('flex: 1 1 calc(25% - 6px)')
    expect(jezweb).toContain('min-width: max-content')
    expect(jezweb).toContain('word-break: keep-all')
    expect(jezweb).toContain('html.ge-native-enlarged-text')
    expect(jezweb).toContain('Mobile web: one toolbar row at 390 CSS px')
    expect(jezweb).toMatch(
      /\.ge-application-frame--jezweb:not\(\s*\.ge-application-frame--native-android\s*\)\s*\.shell-header-actions__notice/u,
    )
    expect(jezweb).toContain('grid-template-columns: repeat(2, minmax(0, 1fr))')
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
    expect(jezweb).toContain("[data-ge-theme-preference='dark']")
    expect(jezweb).toContain('color-scheme: dark')
    expect(jezweb).toContain('min-inline-size: 48px')
    expect(jezweb).toContain('min-block-size: 48px')
    expect(jezweb).toMatch(
      /\.ge-application-frame--jezweb \.ge-application-frame__header \{[^}]*backdrop-filter: none;/su,
    )
    expect(jezweb).not.toContain('backdrop-filter: blur(16px)')

    expect(app).toContain("import './theme/jezwebLearnerChrome.css'")
    expect(app).toContain('ge-application-frame--jezweb')
    expect(app).toContain('variant-c-jezweb')
    expect(app).toContain("import { GuestMiraHome } from './learner/GuestMiraHome'")
    expect(app).not.toContain("import('./learner/GuestMiraHome')")

    const chromeImport = app.indexOf("import './theme/miraLearnerChrome.css'")
    const jezwebImport = app.indexOf("import './theme/jezwebLearnerChrome.css'")
    expect(chromeImport).toBeGreaterThan(-1)
    expect(jezwebImport).toBeGreaterThan(chromeImport)

    expect(androidDownload.pathname).toBe(`/downloads/${ANDROID_APK_FILENAME}`)
    expect(ANDROID_APK_FILENAME).toBe(`global-spoken-english-${APP_VERSION}.apk`)
    expect(colors).toContain('#FAFBFA')
    expect(colors).toContain('#006B3F')
    expect(styles).toContain('windowLightStatusBar')
    expect(network).toContain('117.254.108.143')
  })
})
