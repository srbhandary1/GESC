import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const html = readFileSync(resolve(root, 'index.html'), 'utf8')
const css = readFileSync(resolve(root, 'src/styles.css'), 'utf8')
const script = readFileSync(resolve(root, 'src/main.ts'), 'utf8')

describe('marketing homepage source', () => {
  it('does not ship Superdesign preview runtime', () => {
    expect(html).not.toMatch(/petite-vue|__SUPERDESIGN|sd-ready|sd-component|tailwindcss.com|iconify-icon|maximum-scale/i)
    expect(html).not.toMatch(/parent\.postMessage/)
  })

  it('uses an accessible viewport and skip link', () => {
    expect(html).toContain('width=device-width, initial-scale=1')
    expect(html).toContain('href="#main"')
    expect(html).toContain('Skip to main content')
    expect(html).toMatch(/<header[\s>]/)
    expect(html).toMatch(/<nav[\s>]/)
    expect(html).toMatch(/<main[\s>]/)
    expect(html).toMatch(/<footer[\s>]/)
  })

  it('preserves the Superdesign structure and CTA wording', () => {
    expect(html).toContain('Speak English with confidence in 12 weeks')
    expect(html).toContain('id="levels"')
    expect(html).toContain('id="how-it-works"')
    expect(html).toContain('id="pricing"')
    expect(html).toContain('id="about"')
    expect(html).toContain('id="demo"')
    expect(html).toContain('Try free demo')
    expect(html).toContain('Start free demo')
    expect(html).toContain('₹0')
    expect(html).toContain('₹1,499')
    expect(html).toContain('₹3,999')
    expect(html).toContain('Order coffee like a local')
    expect(html).toContain('Place your level')
    expect(html).toContain('Product')
    expect(html).toContain('Institute')
    expect(html).not.toMatch(/Lets's|Lesssons|definately/i)
  })

  it('keeps 48px targets and visible focus styles', () => {
    expect(css).toContain('--target: 3rem')
    expect(css).toContain('min-height: var(--target)')
    expect(css).toContain(':focus-visible')
    expect(script).toContain('data-nav-toggle')
    expect(script).toContain('aria-expanded')
  })
})
