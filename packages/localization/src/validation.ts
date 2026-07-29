import { createHash } from 'node:crypto'

import {
  catalogueGovernance,
  catalogues,
  DEFAULT_LOCALE,
  FALLBACK_LOCALE,
  MESSAGE_KEYS,
  SUPPORTED_LOCALES,
} from './catalogues.ts'
import type {
  GovernanceManifest,
  MessageKey,
  ReviewStatus,
  SupportedLocale,
} from './catalogues.ts'
import { isNfcNormalized } from './normalization.ts'

export type ValidationSeverity = 'error' | 'warning'

export interface ValidationIssue {
  readonly code: string
  readonly severity: ValidationSeverity
  readonly message: string
  readonly locale?: string
  readonly key?: string
}

export interface ValidationResult {
  readonly valid: boolean
  readonly issues: readonly ValidationIssue[]
  readonly errors: readonly ValidationIssue[]
  readonly warnings: readonly ValidationIssue[]
}

export interface LocaleValidationInput {
  readonly catalogues: Readonly<
    Record<string, Readonly<Record<string, unknown>>>
  >
  readonly governance: unknown
}

const REVIEW_STATUSES: readonly ReviewStatus[] = [
  'source',
  'pending-independent-review',
  'approved',
  'stale',
  'rejected',
]

const REVISION_PATTERN = /^ui-[a-z]{2}-[A-Z]{2}-\d{4}-\d{2}-\d{2}\.\d+$/
const HASH_PATTERN = /^sha256:[a-f0-9]{64}$/
const PLACEHOLDER_PATTERN = /\{([A-Za-z][A-Za-z0-9_]*)\}/g

function issue(
  code: string,
  severity: ValidationSeverity,
  message: string,
  context: Pick<ValidationIssue, 'locale' | 'key'> = {},
): ValidationIssue {
  return { code, severity, message, ...context }
}

function asRecord(value: unknown): Record<string, unknown> | null {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return null
  }

  return value as Record<string, unknown>
}

function asNullableString(value: unknown): string | null | undefined {
  return value === null || typeof value === 'string' ? value : undefined
}

function placeholders(value: string): {
  readonly names: readonly string[]
  readonly malformed: boolean
} {
  const names = Array.from(value.matchAll(PLACEHOLDER_PATTERN), (match) => {
    return match[1] ?? ''
  }).sort()
  const remainder = value.replaceAll(PLACEHOLDER_PATTERN, '')

  return {
    names,
    malformed: remainder.includes('{') || remainder.includes('}'),
  }
}

function sameStrings(
  left: readonly string[],
  right: readonly string[],
): boolean {
  return (
    left.length === right.length &&
    left.every((value, index) => value === right[index])
  )
}

export function canonicalizeCatalogue(
  catalogue: Readonly<Record<string, unknown>>,
): string {
  const canonicalEntries = Object.entries(catalogue)
    .sort(([left], [right]) => {
      if (left < right) {
        return -1
      }

      return left > right ? 1 : 0
    })
    .map(([key, value]) => [key, value])

  return JSON.stringify(Object.fromEntries(canonicalEntries))
}

export function hashCatalogue(
  catalogue: Readonly<Record<string, unknown>>,
): string {
  return `sha256:${createHash('sha256')
    .update(canonicalizeCatalogue(catalogue), 'utf8')
    .digest('hex')}`
}

function validateManifestHeader(
  manifest: Record<string, unknown>,
  issues: ValidationIssue[],
): void {
  if (manifest.schemaVersion !== 1) {
    issues.push(
      issue(
        'invalid-schema-version',
        'error',
        'Governance schemaVersion must be 1.',
      ),
    )
  }

  if (manifest.catalogKind !== 'ui') {
    issues.push(
      issue(
        'invalid-catalog-kind',
        'error',
        'The localization package accepts UI catalogues only.',
      ),
    )
  }

  if (manifest.defaultLocale !== DEFAULT_LOCALE) {
    issues.push(
      issue(
        'invalid-default-locale',
        'error',
        `The default locale must be ${DEFAULT_LOCALE}.`,
      ),
    )
  }

  if (manifest.fallbackLocale !== FALLBACK_LOCALE) {
    issues.push(
      issue(
        'invalid-fallback-locale',
        'error',
        `The fallback locale must be ${FALLBACK_LOCALE}.`,
      ),
    )
  }

  if (manifest.hashAlgorithm !== 'sha256-canonical-json-v1') {
    issues.push(
      issue(
        'invalid-hash-algorithm',
        'error',
        'The governance hash algorithm must be sha256-canonical-json-v1.',
      ),
    )
  }

  const supportedLocales = manifest.supportedLocales
  if (
    !Array.isArray(supportedLocales) ||
    !sameStrings(
      [...supportedLocales].sort().map(String),
      [...SUPPORTED_LOCALES].sort(),
    )
  ) {
    issues.push(
      issue(
        'invalid-supported-locales',
        'error',
        `Supported locales must be exactly ${SUPPORTED_LOCALES.join(', ')}.`,
      ),
    )
  }
}

function validateCatalogueKeysAndMessages(
  availableCatalogues: Readonly<
    Record<string, Readonly<Record<string, unknown>>>
  >,
  issues: ValidationIssue[],
): void {
  const supportedSet = new Set<string>(SUPPORTED_LOCALES)
  const expectedKeySet = new Set<string>(MESSAGE_KEYS)
  const sourceCatalogue = availableCatalogues[FALLBACK_LOCALE]

  for (const locale of Object.keys(availableCatalogues)) {
    if (!supportedSet.has(locale)) {
      issues.push(
        issue(
          'unsupported-locale',
          'error',
          `Catalogue locale "${locale}" is not supported.`,
          { locale },
        ),
      )
    }
  }

  for (const locale of SUPPORTED_LOCALES) {
    const catalogue = availableCatalogues[locale]
    if (catalogue === undefined) {
      issues.push(
        issue(
          'missing-locale',
          'error',
          `Catalogue locale "${locale}" is required.`,
          { locale },
        ),
      )
      continue
    }

    for (const key of MESSAGE_KEYS) {
      if (!(key in catalogue)) {
        issues.push(
          issue('missing-key', 'error', `Message key "${key}" is missing.`, {
            locale,
            key,
          }),
        )
      }
    }

    for (const [key, message] of Object.entries(catalogue)) {
      if (!expectedKeySet.has(key)) {
        issues.push(
          issue(
            'unknown-key',
            'error',
            `Message key "${key}" is not present in the ${FALLBACK_LOCALE} source catalogue.`,
            { locale, key },
          ),
        )
        continue
      }

      if (typeof message !== 'string') {
        issues.push(
          issue(
            'non-string-message',
            'error',
            `Message key "${key}" must contain a string.`,
            { locale, key },
          ),
        )
        continue
      }

      if (message.trim() === '') {
        issues.push(
          issue(
            'blank-message',
            'error',
            `Message key "${key}" must not be empty or whitespace-only.`,
            { locale, key },
          ),
        )
      }

      if (!isNfcNormalized(message)) {
        issues.push(
          issue(
            'not-nfc',
            'error',
            `Message key "${key}" must be NFC-normalized.`,
            { locale, key },
          ),
        )
      }

      const localizedPlaceholders = placeholders(message)
      if (localizedPlaceholders.malformed) {
        issues.push(
          issue(
            'malformed-placeholder',
            'error',
            `Message key "${key}" contains a malformed placeholder.`,
            { locale, key },
          ),
        )
      }

      const sourceMessage = sourceCatalogue?.[key]
      if (typeof sourceMessage === 'string') {
        const sourcePlaceholders = placeholders(sourceMessage)
        if (
          !sameStrings(
            localizedPlaceholders.names,
            sourcePlaceholders.names,
          )
        ) {
          issues.push(
            issue(
              'placeholder-mismatch',
              'error',
              `Message key "${key}" must use the same placeholders as ${FALLBACK_LOCALE}.`,
              { locale, key },
            ),
          )
        }
      }
    }
  }
}

function validateReviewClaims(
  locale: SupportedLocale,
  metadata: Record<string, unknown>,
  issues: ValidationIssue[],
): void {
  const reviewStatus = metadata.reviewStatus
  if (
    typeof reviewStatus !== 'string' ||
    !(REVIEW_STATUSES as readonly string[]).includes(reviewStatus)
  ) {
    issues.push(
      issue(
        'invalid-review-status',
        'error',
        `Locale "${locale}" has an unsupported review status.`,
        { locale },
      ),
    )
    return
  }

  const translatedBy = asNullableString(metadata.translatedBy)
  const reviewedBy = asNullableString(metadata.reviewedBy)
  const approvedAt = asNullableString(metadata.approvedAt)
  const independentReview = metadata.independentReview

  if (
    translatedBy === undefined ||
    reviewedBy === undefined ||
    approvedAt === undefined ||
    typeof independentReview !== 'boolean'
  ) {
    issues.push(
      issue(
        'invalid-review-metadata',
        'error',
        `Locale "${locale}" has malformed review metadata.`,
        { locale },
      ),
    )
    return
  }

  if (locale === FALLBACK_LOCALE && reviewStatus !== 'source') {
    issues.push(
      issue(
        'invalid-fallback-review-status',
        'error',
        `The ${FALLBACK_LOCALE} fallback catalogue must retain source status.`,
        { locale },
      ),
    )
    return
  }

  if (reviewStatus === 'source') {
    if (
      locale !== FALLBACK_LOCALE ||
      translatedBy !== null ||
      reviewedBy !== null ||
      approvedAt !== null ||
      independentReview
    ) {
      issues.push(
        issue(
          'dishonest-review-status',
          'error',
          'Source status is reserved for the untranslated fallback catalogue and cannot claim translation review.',
          { locale },
        ),
      )
    }
    return
  }

  if (reviewStatus === 'approved') {
    const approvedTimestamp =
      approvedAt === null ? Number.NaN : Date.parse(approvedAt)
    if (
      translatedBy === null ||
      translatedBy.trim() === '' ||
      reviewedBy === null ||
      reviewedBy.trim() === '' ||
      translatedBy === reviewedBy ||
      !Number.isFinite(approvedTimestamp) ||
      !independentReview
    ) {
      issues.push(
        issue(
          'dishonest-review-status',
          'error',
          'Approved translations require distinct translator and reviewer identities, a valid approval time, and independentReview=true.',
          { locale },
        ),
      )
    }
    return
  }

  if (
    reviewStatus === 'pending-independent-review' &&
    (translatedBy === null ||
      translatedBy.trim() === '' ||
      reviewedBy !== null ||
      approvedAt !== null ||
      independentReview)
  ) {
    issues.push(
      issue(
        'dishonest-review-status',
        'error',
        'Pending translations require attribution and must not claim a reviewer, approval time, or independent review.',
        { locale },
      ),
    )
    return
  }

  if (
    (reviewStatus === 'stale' || reviewStatus === 'rejected') &&
    independentReview
  ) {
    issues.push(
      issue(
        'dishonest-review-status',
        'error',
        `${reviewStatus} translations cannot claim a current independent review.`,
        { locale },
      ),
    )
    return
  }

  if (reviewStatus === 'pending-independent-review') {
    issues.push(
      issue(
        'pending-independent-review',
        'warning',
        `Locale "${locale}" is an implementation preview pending independent human review.`,
        { locale },
      ),
    )
  } else if (reviewStatus === 'stale') {
    issues.push(
      issue(
        'stale-translation',
        'warning',
        `Locale "${locale}" is explicitly marked stale.`,
        { locale },
      ),
    )
  }
}

function validateGovernanceEntries(
  availableCatalogues: Readonly<
    Record<string, Readonly<Record<string, unknown>>>
  >,
  manifest: Record<string, unknown>,
  issues: ValidationIssue[],
): void {
  const metadataCollection = asRecord(manifest.catalogues)
  if (metadataCollection === null) {
    issues.push(
      issue(
        'missing-governance',
        'error',
        'Governance metadata must contain a catalogues object.',
      ),
    )
    return
  }

  const supportedSet = new Set<string>(SUPPORTED_LOCALES)
  for (const locale of Object.keys(metadataCollection)) {
    if (!supportedSet.has(locale)) {
      issues.push(
        issue(
          'unsupported-governance-locale',
          'error',
          `Governance locale "${locale}" is not supported.`,
          { locale },
        ),
      )
    }
  }

  const fallbackMetadata = asRecord(metadataCollection[FALLBACK_LOCALE])

  for (const locale of SUPPORTED_LOCALES) {
    const metadata = asRecord(metadataCollection[locale])
    const catalogue = availableCatalogues[locale]
    if (metadata === null) {
      issues.push(
        issue(
          'missing-governance',
          'error',
          `Governance metadata for "${locale}" is required.`,
          { locale },
        ),
      )
      continue
    }

    if (metadata.locale !== locale) {
      issues.push(
        issue(
          'governance-locale-mismatch',
          'error',
          `Governance locale must equal "${locale}".`,
          { locale },
        ),
      )
    }

    if (
      typeof metadata.revision !== 'string' ||
      !REVISION_PATTERN.test(metadata.revision)
    ) {
      issues.push(
        issue(
          'invalid-revision',
          'error',
          `Locale "${locale}" requires a stable UI catalogue revision.`,
          { locale },
        ),
      )
    }

    if (
      typeof metadata.catalogueHash !== 'string' ||
      !HASH_PATTERN.test(metadata.catalogueHash)
    ) {
      issues.push(
        issue(
          'invalid-catalogue-hash',
          'error',
          `Locale "${locale}" requires a SHA-256 catalogue hash.`,
          { locale },
        ),
      )
    } else if (
      catalogue !== undefined &&
      metadata.catalogueHash !== hashCatalogue(catalogue)
    ) {
      issues.push(
        issue(
          'catalogue-hash-mismatch',
          'error',
          `Locale "${locale}" catalogue content does not match its governance hash.`,
          { locale },
        ),
      )
    }

    if (metadata.sourceLocale !== FALLBACK_LOCALE) {
      issues.push(
        issue(
          'source-locale-mismatch',
          'error',
          `Locale "${locale}" must link to the ${FALLBACK_LOCALE} source.`,
          { locale },
        ),
      )
    }

    if (
      typeof metadata.sourceRevision !== 'string' ||
      !REVISION_PATTERN.test(metadata.sourceRevision)
    ) {
      issues.push(
        issue(
          'invalid-source-revision',
          'error',
          `Locale "${locale}" requires a valid source revision.`,
          { locale },
        ),
      )
    }

    if (
      typeof metadata.sourceHash !== 'string' ||
      !HASH_PATTERN.test(metadata.sourceHash)
    ) {
      issues.push(
        issue(
          'invalid-source-hash',
          'error',
          `Locale "${locale}" requires a valid source hash.`,
          { locale },
        ),
      )
    }

    if (fallbackMetadata !== null) {
      if (metadata.sourceRevision !== fallbackMetadata.revision) {
        issues.push(
          issue(
            'source-revision-mismatch',
            'error',
            `Locale "${locale}" does not reference the current source revision.`,
            { locale },
          ),
        )
      }

      if (metadata.sourceHash !== fallbackMetadata.catalogueHash) {
        issues.push(
          issue(
            'source-hash-mismatch',
            'error',
            `Locale "${locale}" does not reference the current source hash.`,
            { locale },
          ),
        )
      }
    }

    validateReviewClaims(locale, metadata, issues)
  }
}

export function validateCatalogues(
  input: LocaleValidationInput,
): ValidationResult {
  const issues: ValidationIssue[] = []
  const manifest = asRecord(input.governance)

  validateCatalogueKeysAndMessages(input.catalogues, issues)

  if (manifest === null) {
    issues.push(
      issue(
        'invalid-governance',
        'error',
        'Governance metadata must be an object.',
      ),
    )
  } else {
    validateManifestHeader(manifest, issues)
    validateGovernanceEntries(input.catalogues, manifest, issues)
  }

  const errors = issues.filter(({ severity }) => severity === 'error')
  const warnings = issues.filter(({ severity }) => severity === 'warning')

  return Object.freeze({
    valid: errors.length === 0,
    issues: Object.freeze(issues),
    errors: Object.freeze(errors),
    warnings: Object.freeze(warnings),
  })
}

export function validateCanonicalCatalogues(): ValidationResult {
  return validateCatalogues({
    catalogues: catalogues as Readonly<
      Record<string, Readonly<Record<string, unknown>>>
    >,
    governance: catalogueGovernance as GovernanceManifest,
  })
}

export function cloneCanonicalValidationInput(): LocaleValidationInput {
  return {
    catalogues: Object.fromEntries(
      Object.entries(catalogues).map(([locale, catalogue]) => [
        locale,
        { ...catalogue },
      ]),
    ),
    governance: structuredClone(catalogueGovernance),
  }
}

export type { MessageKey }
