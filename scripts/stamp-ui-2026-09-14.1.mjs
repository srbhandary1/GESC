import { createHash } from 'node:crypto'
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const catalogueDir = join(
  dirname(fileURLToPath(import.meta.url)),
  '../packages/localization/src/catalogues',
)
const locales = ['en-IN', 'kn-IN', 'hi-IN', 'ml-IN', 'ta-IN', 'te-IN']
const date = '2026-09-14.1'
const hashes = {}

function canonicalizeCatalogue(catalogue) {
  const canonicalEntries = Object.entries(catalogue).sort(([left], [right]) =>
    left < right ? -1 : 1,
  )

  return JSON.stringify(Object.fromEntries(canonicalEntries))
}

function hashCatalogue(catalogue) {
  return `sha256:${createHash('sha256')
    .update(canonicalizeCatalogue(catalogue), 'utf8')
    .digest('hex')}`
}

for (const locale of locales) {
  const data = JSON.parse(
    readFileSync(join(catalogueDir, `${locale}.json`), 'utf8'),
  )
  hashes[locale] = hashCatalogue(data)
}

const governancePath = join(catalogueDir, 'governance.json')
const governance = JSON.parse(readFileSync(governancePath, 'utf8'))
const sourceHash = hashes['en-IN']

for (const locale of locales) {
  const entry = governance.catalogues[locale]
  entry.revision = `ui-${locale}-${date}`
  entry.catalogueHash = hashes[locale]
  entry.sourceRevision = `ui-en-IN-${date}`
  entry.sourceHash = sourceHash
}

writeFileSync(governancePath, `${JSON.stringify(governance, null, 2)}\n`)
console.log(hashes)
