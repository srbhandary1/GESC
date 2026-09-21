import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const webRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..')

describe('Play legal pages', () => {
  it('uses Variant C tokens instead of unstyled default cards', () => {
    const legalCss = readFileSync(resolve(webRoot, 'public/legal.css'), 'utf8')
    const privacy = readFileSync(resolve(webRoot, 'public/privacy.html'), 'utf8')
    const deletion = readFileSync(
      resolve(webRoot, 'public/account-deletion.html'),
      'utf8',
    )

    expect(legalCss).toContain('--ge-canvas: #fafbfa')
    expect(legalCss).toContain('--ge-action: #006b3f')
    expect(legalCss).toContain('--ge-ink: #1a1a1a')
    expect(legalCss).toContain('color: var(--ge-action-hover)')
    expect(privacy).toContain('href="/legal.css"')
    expect(deletion).toContain('href="/legal.css"')
    expect(privacy).not.toContain('#f4f6f8')
    expect(deletion).not.toContain('#f4f6f8')
  })
})
