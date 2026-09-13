#!/usr/bin/env node

import { readFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = dirname(dirname(fileURLToPath(import.meta.url)))
const readJson = (name) =>
  readFile(resolve(root, `docs/evidence/lpl-01/${name}`), 'utf8').then(JSON.parse)

const expected = [
  ['introduction', '01KZZB3DJFKAN8BQD30K6WJGV6', '2e8335f5998dace0a619acbd24945beafbb3b9a2e427bd71dd01f473b3b17e0b'],
  ['practice', '01KZZBHZXE93YYHPGNKFJZ8EBY', 'a185249064c4e153237e1a54f8375fe1e7e4fac2ce45525684a929ea174a6298'],
  ['conversation', '01KZZBPQSS5ECMMGEBGAA2W3M4', '830bba76c97bb19d3b132da8bd7a021cb5160be146592cfffcaaaa810bc48635'],
  ['interaction', '01KZZC03NX6QGCWGMD9WX1AH1D', '5db31dd35f5d9fe1f0d02fc116e2793f718bf6b4cff1dde00c25c93ec8ba2d37'],
]

const [source, blueprint] = await Promise.all([
  readJson('level1-source-snapshot-manifest.json'),
  readJson('level1-blueprint-draft.json'),
])
const failures = []
const require = (condition, message) => {
  if (!condition) failures.push(message)
}

require(source.schema === 'global-english.lpl01.source-snapshot-manifest.v1', 'source schema drift')
require(source.typed_body.status === 'pending_read_only_export', 'source body is overstated')
require(source.typed_body.fields.length === 0, 'source fields must remain empty until exact export')
require(source.typed_body.raw_markup_retained === false, 'raw markup retention changed')
require(source.typed_body.assignment_audio_included === false, 'Assignment audio boundary changed')
require(blueprint.schema === 'global-english.lesson-practice-loop.v1', 'blueprint schema drift')
require(blueprint.publication.eligible === false, 'blocked blueprint became publishable')
require(blueprint.ordered_parts.length === expected.length, 'ordered part count drift')
require(
  JSON.stringify(blueprint.ordered_parts.map((part) => part.part_id)) ===
    JSON.stringify(expected.map(([part]) => part)),
  'ordered part sequence drift',
)

for (const [index, [part, revision, hash]] of expected.entries()) {
  const binding = source.media_bindings[index]
  const draft = blueprint.ordered_parts[index]
  require(binding?.order === index + 1 && binding?.part_id === part, `${part} source order drift`)
  require(binding?.metadata_revision_id === revision, `${part} source revision drift`)
  require(binding?.metadata_content_hash === hash, `${part} source hash drift`)
  require(draft?.metadata_revision_id === revision, `${part} blueprint revision drift`)
  require(draft?.metadata_content_hash === hash, `${part} blueprint hash drift`)
}

const serialized = JSON.stringify({ source, blueprint })
require(!/<\/?script\b/i.test(serialized), 'executable markup present')
require(!/"assignment_audio_included"\s*:\s*true/i.test(serialized), 'excluded Assignment audio present')
require(source.blockers.length >= 3, 'source blockers were removed')
require(blueprint.publication.blockers.includes('typed_source_body'), 'typed-source blocker was removed')

if (failures.length > 0) {
  for (const failure of failures) console.error(`LPL-01 evidence validation failed: ${failure}`)
  process.exitCode = 1
} else {
  console.log('LPL-01 evidence is deterministic, hash-bound, ordered, and correctly blocked pending the exact source-body export.')
}
