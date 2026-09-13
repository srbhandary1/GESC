import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { BRAND_COLORS } from './brandIdentity'

describe('Studio appearance themes', () => {
  const themeDirectory = dirname(fileURLToPath(import.meta.url))
  const studioExperience = readFileSync(
    resolve(themeDirectory, './studioExperience.css'),
    'utf8',
  )
  const miraGescGreenPin = readFileSync(
    resolve(themeDirectory, './miraGescGreenPin.css'),
    'utf8',
  )
  const appStyles = readFileSync(
    resolve(themeDirectory, '../App.css'),
    'utf8',
  )
  const learnerJourney = readFileSync(
    resolve(themeDirectory, '../learner/LearnerJourney.css'),
    'utf8',
  )
  const source = `${studioExperience}\n${miraGescGreenPin}`

  it('keeps a distinct high-contrast light Studio shell', () => {
    expect(source).toContain("[data-ge-theme='light']")
    expect(source).toContain('.ge-application-frame--studio')
    expect(source).toContain('.shell-studio-brand')
    expect(BRAND_COLORS.lightBg).toBe('#F8FFFE')
    expect(source).toContain('--ge-mira-green: #006b3f')
    expect(source).toContain('--ge-studio-cyan: var(--ge-mira-mint)')
    expect(appStyles).toContain('.shell-content--gate')
    expect(studioExperience).toContain('width: 100%')
  })

  it('keeps Android controls compact without reducing touch targets', () => {
    expect(studioExperience).toContain('.ge-application-frame--studio-learner')
    expect(studioExperience).toContain('.learner-home__action')
    expect(miraGescGreenPin).toContain(
      '.ge-application-frame--studio-learner .learner-home__action',
    )
    expect(learnerJourney).toContain('min-block-size: var(--ge-target-minimum)')
  })
})
