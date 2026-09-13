#!/usr/bin/env node

import { readFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = dirname(dirname(fileURLToPath(import.meta.url)))
const blueprint = JSON.parse(await readFile(resolve(root, 'docs/evidence/lpl-01/level1-authored-blueprint-draft.json'), 'utf8'))
const failures = []
const require = (condition, message) => { if (!condition) failures.push(message) }
require(blueprint.authorship === 'new_content_not_legacy_reconciliation', 'authorship boundary drift')
require(blueprint.publication.eligible === false, 'draft became publishable')
require(blueprint.lesson.objective?.length > 0, 'objective missing')
require(blueprint.check.items.length >= 3 && blueprint.check.items.length <= 7, 'check item count out of bounds')
require(blueprint.check.items.reduce((sum, item) => sum + item.weight, 0) === 100, 'check weights must total 100')
require(blueprint.check.items.every((item) => item.correct_option >= 0 && item.correct_option < item.options.length), 'invalid answer index')
require(blueprint.apply.evidence_state === 'not_recorded', 'Apply evidence boundary drift')
require(blueprint.review.english_academic.status === 'approved', 'English approval missing')
require(blueprint.review.kannada.status === 'approved', 'Kannada approval missing')
require(!/<\/?script\b/i.test(JSON.stringify(blueprint)), 'executable markup present')
if (failures.length) { for (const failure of failures) console.error(`Authored blueprint validation failed: ${failure}`); process.exitCode = 1 }
else console.log('LPL-01 newly authored blueprint is deterministic, unpublished, and correctly separated from legacy source reconciliation.')
