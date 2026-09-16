import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const themeDir = dirname(fileURLToPath(import.meta.url))

describe('Jezweb Variant C learner chrome', () => {
  it('loads after Variant A chrome and keeps the five-destination flow', () => {
    const jezweb = readFileSync(resolve(themeDir, 'jezwebLearnerChrome.css'), 'utf8')
    const app = readFileSync(resolve(themeDir, '../App.tsx'), 'utf8')
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
    expect(jezweb).toContain('.learner-home__title:focus-visible')
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
