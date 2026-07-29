#!/usr/bin/env node

import {
  validateCanonicalCatalogues,
} from '../packages/localization/src/validation.ts'

const result = validateCanonicalCatalogues()

for (const validationIssue of result.issues) {
  const location = [validationIssue.locale, validationIssue.key]
    .filter(Boolean)
    .join(':')
  const suffix = location === '' ? '' : ` (${location})`

  const output = `${validationIssue.severity.toUpperCase()} ${validationIssue.code}${suffix}: ${validationIssue.message}`
  if (validationIssue.severity === 'error') {
    console.error(output)
  } else {
    console.warn(output)
  }
}

if (!result.valid) {
  console.error(
    `Locale validation failed with ${result.errors.length} error(s) and ${result.warnings.length} warning(s).`,
  )
  process.exitCode = 1
} else {
  console.log(
    `Locale validation passed for 2 UI catalogues with ${result.warnings.length} governance warning(s).`,
  )
}
