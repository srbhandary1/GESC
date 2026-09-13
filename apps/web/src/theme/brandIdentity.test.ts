import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { BRAND_ASSETS, BRAND_COLORS } from './brandIdentity'

const webRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..')

describe('Global Spoken English brand kit', () => {
  it('pins kit colours and committed public assets', () => {
    expect(BRAND_COLORS.greenPrimary).toBe('#006B3F')
    expect(BRAND_COLORS.greenAccent).toBe('#00A86B')
    expect(BRAND_COLORS.red).toBe('#E31C23')
    expect(BRAND_COLORS.navy).toBe('#1A1A2E')
    expect(BRAND_COLORS.lightBg).toBe('#F8FFFE')

    const publicBrand = resolve(webRoot, 'public/brand')
    const guidelines = readFileSync(
      resolve(publicBrand, 'brand-guidelines.txt'),
      'utf8',
    )

    expect(guidelines).toContain('#006B3F')
    expect(guidelines).toContain('#E31C23')
    expect(readFileSync(resolve(webRoot, 'public' + BRAND_ASSETS.logoDark), 'utf8')).toContain(
      'GLOBAL',
    )
    expect(readFileSync(resolve(webRoot, 'public' + BRAND_ASSETS.favicon), 'utf8')).toContain(
      'svg',
    )
  })
})
