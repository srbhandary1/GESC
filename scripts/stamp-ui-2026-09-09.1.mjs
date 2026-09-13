import { createHash } from 'node:crypto'
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const catalogueDir = join(
  dirname(fileURLToPath(import.meta.url)),
  '../packages/localization/src/catalogues',
)
const locales = ['en-IN', 'kn-IN', 'hi-IN', 'ml-IN', 'ta-IN', 'te-IN']
const date = '2026-09-09.1'

const added = {
  'operator.speech_economics.allocated_cost': 'Allocated cost',
  'operator.speech_economics.apply_filters': 'Apply filters',
  'operator.speech_economics.attempts': 'Requests',
  'operator.speech_economics.column.allocated': 'Allocated INR',
  'operator.speech_economics.column.attempts': 'Requests',
  'operator.speech_economics.column.cost_per_turn': 'Cost / successful turn',
  'operator.speech_economics.column.date': 'Date',
  'operator.speech_economics.column.p95': 'First audible p95 (ms)',
  'operator.speech_economics.column.provider': 'Provider',
  'operator.speech_economics.column.stage': 'Stage',
  'operator.speech_economics.column.successes': 'Successes',
  'operator.speech_economics.commercial.empty':
    'No approved rate cards or statements are recorded yet.',
  'operator.speech_economics.commercial.lead':
    'Platform commercial terms only. Organization operators never see this section.',
  'operator.speech_economics.commercial.title': 'Commercial terms',
  'operator.speech_economics.cost_per_turn': 'Cost per successful turn',
  'operator.speech_economics.empty':
    'No Speech Economics aggregates are available for this organization yet.',
  'operator.speech_economics.export': 'Export CSV',
  'operator.speech_economics.export_error':
    'The Speech Economics export could not be created.',
  'operator.speech_economics.exporting': 'Exporting…',
  'operator.speech_economics.from': 'From',
  'operator.speech_economics.lead':
    'Active-organization allocated usage and cost only. No audio, transcripts, learner identifiers or vendor commercial terms.',
  'operator.speech_economics.partial':
    'Some rows are hidden because their sample is too small to display safely.',
  'operator.speech_economics.rows': 'Daily allocated usage',
  'operator.speech_economics.stage': 'Stage',
  'operator.speech_economics.stage_all': 'All stages',
  'operator.speech_economics.stale_rate':
    'At least one row used a missing, stale or ambiguous rate card.',
  'operator.speech_economics.successes': 'Successes',
  'operator.speech_economics.title': 'Speech Economics',
  'operator.speech_economics.to': 'To',
  'operator.speech_economics.unreconciled':
    'Allocated cost is not yet reconciled to a provider statement.',
}

function hashCatalogue(catalogue) {
  const canonicalEntries = Object.entries(catalogue)
    .sort(([left], [right]) => (left < right ? -1 : left > right ? 1 : 0))
    .map(([key, value]) => [key, value])

  return `sha256:${createHash('sha256')
    .update(JSON.stringify(Object.fromEntries(canonicalEntries)), 'utf8')
    .digest('hex')}`
}

const hashes = {}

for (const locale of locales) {
  const path = join(catalogueDir, `${locale}.json`)
  const data = JSON.parse(readFileSync(path, 'utf8'))
  const merged = {}

  for (const [key, value] of Object.entries({ ...data, ...added })) {
    merged[key] = String(value).normalize('NFC')
  }

  const sorted = Object.fromEntries(
    Object.entries(merged).sort(([left], [right]) =>
      left < right ? -1 : left > right ? 1 : 0,
    ),
  )
  writeFileSync(path, `${JSON.stringify(sorted, null, 2)}\n`)
  hashes[locale] = hashCatalogue(sorted)
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
