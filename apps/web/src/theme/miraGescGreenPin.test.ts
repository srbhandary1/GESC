import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const themeDir = dirname(fileURLToPath(import.meta.url))

describe('Mira GESC-green theme pin', () => {
  it('pins Superdesign Studio tokens to brand green without lesson files', () => {
    const pin = readFileSync(resolve(themeDir, 'miraGescGreenPin.css'), 'utf8')
    const studio = readFileSync(resolve(themeDir, 'studioExperience.css'), 'utf8')
    const app = readFileSync(resolve(themeDir, '../App.tsx'), 'utf8')

    expect(pin).toContain('#006b3f')
    expect(pin).toContain('#0b1210')
    expect(pin).toContain('#3ddc97')
    expect(pin).toContain('--ge-studio-cyan: var(--ge-mira-mint)')
    expect(pin).toContain('lesson/curriculum')
    expect(pin).toContain('files stay untouched')

    expect(studio).toContain('--ge-studio-cyan-rgb:')
    expect(studio).toContain('var(--ge-studio-cyan)')
    expect(studio).toContain('rgb(var(--ge-studio-cyan-rgb)')

    const studioImport = app.indexOf("import './theme/studioExperience.css'")
    const pinImport = app.indexOf("import './theme/miraGescGreenPin.css'")
    const chromeImport = app.indexOf("import './theme/miraLearnerChrome.css'")
    const jezwebImport = app.indexOf("import './theme/jezwebLearnerChrome.css'")
    expect(studioImport).toBeGreaterThan(-1)
    expect(pinImport).toBeGreaterThan(studioImport)
    expect(chromeImport).toBeGreaterThan(pinImport)
    expect(jezwebImport).toBeGreaterThan(chromeImport)
  })
})
