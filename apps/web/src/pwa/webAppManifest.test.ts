import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const webRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..')

describe('web app manifest', () => {
  it('links the public-shell manifest from index.html without API or audio URLs', () => {
    const index = readFileSync(resolve(webRoot, 'index.html'), 'utf8')
    const manifest = JSON.parse(
      readFileSync(resolve(webRoot, 'public/manifest.webmanifest'), 'utf8'),
    ) as {
      display: string
      start_url: string
      icons: readonly { src: string }[]
    }

    expect(index).toContain('href="/manifest.webmanifest"')
    expect(manifest.display).toBe('standalone')
    expect(manifest.start_url).toBe('/#home')
    expect(manifest.icons.map((icon) => icon.src)).toEqual([
      '/brand/icon-192.png',
      '/brand/icon-512.png',
    ])
    expect(JSON.stringify(manifest)).not.toContain('/api/')
  })
})
