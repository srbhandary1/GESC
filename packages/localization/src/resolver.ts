import {
  catalogueGovernance,
  catalogues,
  DEFAULT_LOCALE,
  FALLBACK_LOCALE,
  isSupportedLocale,
} from './catalogues.ts'
import type {
  CatalogueCollection,
  CatalogueGovernance,
  GovernanceManifest,
  MessageKey,
  ReviewStatus,
  SupportedLocale,
} from './catalogues.ts'
import { normalizeLocalizedText } from './normalization.ts'

export type ResolutionPolicy = 'approved-only' | 'development-preview'

export type ResolutionReason =
  | 'requested-locale'
  | 'development-preview'
  | 'unsupported-locale'
  | 'missing-catalogue'
  | 'missing-message'
  | 'missing-governance'
  | 'unapproved-translation'
  | 'stale-translation'

export type MessageParameters = Readonly<
  Record<string, string | number | boolean>
>

export interface FallbackEvent {
  readonly type: 'localization.fallback'
  readonly messageKey: MessageKey
  readonly requestedLocale: string
  readonly resolvedLocale: typeof FALLBACK_LOCALE
  readonly policy: ResolutionPolicy
  readonly reason: Exclude<
    ResolutionReason,
    'requested-locale' | 'development-preview'
  >
  readonly requestedRevision: string | null
}

export interface ResolvedMessage {
  readonly text: string
  readonly requestedLocale: string
  readonly resolvedLocale: SupportedLocale
  readonly usedFallback: boolean
  readonly reason: ResolutionReason
  readonly reviewStatus: ReviewStatus
  readonly revision: string
  readonly isPreview: boolean
}

export interface ResolveMessageOptions {
  readonly requestedLocale?: string
  readonly parameters?: MessageParameters
  readonly policy?: ResolutionPolicy
  readonly catalogues?: CatalogueCollection
  readonly governance?: GovernanceManifest
  readonly onFallback?: (event: FallbackEvent) => void
}

export class MissingFallbackMessageError extends Error {
  readonly messageKey: MessageKey

  constructor(messageKey: MessageKey) {
    super(`The fallback catalogue cannot resolve message key "${messageKey}".`)
    this.name = 'MissingFallbackMessageError'
    this.messageKey = messageKey
  }
}

export class MissingMessageParameterError extends Error {
  readonly messageKey: MessageKey
  readonly parameterName: string

  constructor(messageKey: MessageKey, parameterName: string) {
    super(
      `Message key "${messageKey}" requires parameter "${parameterName}".`,
    )
    this.name = 'MissingMessageParameterError'
    this.messageKey = messageKey
    this.parameterName = parameterName
  }
}

interface Availability {
  readonly available: boolean
  readonly isPreview: boolean
  readonly reason:
    | 'requested-locale'
    | 'development-preview'
    | 'missing-catalogue'
    | 'missing-message'
    | 'missing-governance'
    | 'unapproved-translation'
    | 'stale-translation'
}

function hasHonestSourceStatus(metadata: CatalogueGovernance): boolean {
  return (
    metadata.locale === FALLBACK_LOCALE &&
    metadata.sourceLocale === FALLBACK_LOCALE &&
    metadata.sourceRevision === metadata.revision &&
    metadata.sourceHash === metadata.catalogueHash &&
    metadata.translatedBy === null &&
    metadata.reviewedBy === null &&
    metadata.approvedAt === null &&
    !metadata.independentReview
  )
}

function hasHonestApproval(metadata: CatalogueGovernance): boolean {
  return (
    metadata.translatedBy !== null &&
    metadata.translatedBy.trim() !== '' &&
    metadata.reviewedBy !== null &&
    metadata.reviewedBy.trim() !== '' &&
    metadata.translatedBy !== metadata.reviewedBy &&
    metadata.approvedAt !== null &&
    Number.isFinite(Date.parse(metadata.approvedAt)) &&
    metadata.independentReview
  )
}

function hasHonestPendingReview(metadata: CatalogueGovernance): boolean {
  return (
    metadata.translatedBy !== null &&
    metadata.translatedBy.trim() !== '' &&
    metadata.reviewedBy === null &&
    metadata.approvedAt === null &&
    !metadata.independentReview
  )
}

function translationAvailability(
  locale: SupportedLocale,
  key: MessageKey,
  policy: ResolutionPolicy,
  availableCatalogues: CatalogueCollection,
  governance: GovernanceManifest,
): Availability {
  const catalogue = availableCatalogues[locale]
  if (catalogue === undefined) {
    return {
      available: false,
      isPreview: false,
      reason: 'missing-catalogue',
    }
  }

  if (typeof catalogue[key] !== 'string') {
    return {
      available: false,
      isPreview: false,
      reason: 'missing-message',
    }
  }

  const metadata = governance.catalogues[locale]
  if (metadata === undefined) {
    return {
      available: false,
      isPreview: false,
      reason: 'missing-governance',
    }
  }

  if (locale === FALLBACK_LOCALE) {
    return metadata.reviewStatus === 'source' &&
      hasHonestSourceStatus(metadata)
      ? { available: true, isPreview: false, reason: 'requested-locale' }
      : { available: false, isPreview: false, reason: 'unapproved-translation' }
  }

  const sourceMetadata = governance.catalogues[FALLBACK_LOCALE]
  if (
    metadata.reviewStatus === 'stale' ||
    sourceMetadata === undefined ||
    metadata.sourceRevision !== sourceMetadata.revision ||
    metadata.sourceHash !== sourceMetadata.catalogueHash
  ) {
    return {
      available: false,
      isPreview: false,
      reason: 'stale-translation',
    }
  }

  if (
    metadata.reviewStatus === 'approved' &&
    hasHonestApproval(metadata)
  ) {
    return { available: true, isPreview: false, reason: 'requested-locale' }
  }

  if (
    metadata.reviewStatus === 'pending-independent-review' &&
    policy === 'development-preview' &&
    hasHonestPendingReview(metadata)
  ) {
    return {
      available: true,
      isPreview: true,
      reason: 'development-preview',
    }
  }

  return {
    available: false,
    isPreview: false,
    reason: 'unapproved-translation',
  }
}

function interpolateMessage(
  key: MessageKey,
  template: string,
  parameters: MessageParameters,
): string {
  return template.replaceAll(
    /\{([A-Za-z][A-Za-z0-9_]*)\}/g,
    (_placeholder, parameterName: string) => {
      const parameterValue = parameters[parameterName]
      if (parameterValue === undefined) {
        throw new MissingMessageParameterError(key, parameterName)
      }

      return normalizeLocalizedText(String(parameterValue))
    },
  )
}

function getResolvedMetadata(
  locale: SupportedLocale,
  governance: GovernanceManifest,
): CatalogueGovernance {
  const metadata = governance.catalogues[locale]
  if (metadata === undefined) {
    throw new Error(`Missing governance metadata for locale "${locale}".`)
  }

  return metadata
}

export function resolveMessage(
  key: MessageKey,
  options: ResolveMessageOptions = {},
): ResolvedMessage {
  const requestedLocale = options.requestedLocale ?? DEFAULT_LOCALE
  const policy = options.policy ?? 'approved-only'
  const availableCatalogues = options.catalogues ?? catalogues
  const governance = options.governance ?? catalogueGovernance

  let resolutionReason: ResolutionReason = 'unsupported-locale'
  let resolvedLocale: SupportedLocale = FALLBACK_LOCALE
  let isPreview = false

  if (isSupportedLocale(requestedLocale)) {
    const availability = translationAvailability(
      requestedLocale,
      key,
      policy,
      availableCatalogues,
      governance,
    )

    if (availability.available) {
      resolvedLocale = requestedLocale
      resolutionReason = availability.reason
      isPreview = availability.isPreview
    } else {
      resolutionReason = availability.reason
    }
  }

  const fallbackAvailability = translationAvailability(
    FALLBACK_LOCALE,
    key,
    'approved-only',
    availableCatalogues,
    governance,
  )
  const template = availableCatalogues[resolvedLocale]?.[key]

  if (
    typeof template !== 'string' ||
    (!isSupportedLocale(requestedLocale) || resolvedLocale === FALLBACK_LOCALE) &&
      !fallbackAvailability.available
  ) {
    throw new MissingFallbackMessageError(key)
  }

  const metadata = getResolvedMetadata(resolvedLocale, governance)
  const usedFallback = resolvedLocale !== requestedLocale

  if (usedFallback) {
    const requestedMetadata = isSupportedLocale(requestedLocale)
      ? governance.catalogues[requestedLocale]
      : undefined

    options.onFallback?.(
      Object.freeze({
        type: 'localization.fallback',
        messageKey: key,
        requestedLocale,
        resolvedLocale: FALLBACK_LOCALE,
        policy,
        reason: resolutionReason as FallbackEvent['reason'],
        requestedRevision: requestedMetadata?.revision ?? null,
      }),
    )
  }

  return Object.freeze({
    text: interpolateMessage(
      key,
      normalizeLocalizedText(template),
      options.parameters ?? {},
    ),
    requestedLocale,
    resolvedLocale,
    usedFallback,
    reason: resolutionReason,
    reviewStatus: metadata.reviewStatus,
    revision: metadata.revision,
    isPreview,
  })
}
