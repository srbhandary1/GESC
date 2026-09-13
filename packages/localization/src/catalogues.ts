import enInSource from './catalogues/en-IN.json' with { type: 'json' }
import governanceSource from './catalogues/governance.json' with { type: 'json' }
import hiInSource from './catalogues/hi-IN.json' with { type: 'json' }
import knInSource from './catalogues/kn-IN.json' with { type: 'json' }
import mlInSource from './catalogues/ml-IN.json' with { type: 'json' }
import taInSource from './catalogues/ta-IN.json' with { type: 'json' }
import teInSource from './catalogues/te-IN.json' with { type: 'json' }

export const DEFAULT_LOCALE = 'kn-IN' as const
export const FALLBACK_LOCALE = 'en-IN' as const
export const SUPPORTED_LOCALES = [
  DEFAULT_LOCALE,
  'ml-IN',
  'ta-IN',
  'te-IN',
  'hi-IN',
  FALLBACK_LOCALE,
] as const

/** Learner-facing interface languages currently offered in the product UI. */
export const INTERFACE_LOCALES = [
  DEFAULT_LOCALE,
  FALLBACK_LOCALE,
] as const

export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number]
export type InterfaceLocale = (typeof INTERFACE_LOCALES)[number]
export type MessageKey = keyof typeof enInSource
export type ReviewStatus =
  | 'source'
  | 'pending-independent-review'
  | 'approved'
  | 'stale'
  | 'rejected'

export interface CatalogueGovernance {
  readonly locale: SupportedLocale
  readonly revision: string
  readonly catalogueHash: string
  readonly sourceLocale: typeof FALLBACK_LOCALE
  readonly sourceRevision: string
  readonly sourceHash: string
  readonly reviewStatus: ReviewStatus
  readonly translatedBy: string | null
  readonly reviewedBy: string | null
  readonly approvedAt: string | null
  readonly independentReview: boolean
}

export interface GovernanceManifest {
  readonly schemaVersion: 1
  readonly catalogKind: 'ui'
  readonly defaultLocale: typeof DEFAULT_LOCALE
  readonly fallbackLocale: typeof FALLBACK_LOCALE
  readonly supportedLocales: readonly SupportedLocale[]
  readonly hashAlgorithm: 'sha256-canonical-json-v1'
  readonly catalogues: Readonly<
    Partial<Record<SupportedLocale, CatalogueGovernance>>
  >
}

export type MessageCatalogue = Readonly<Record<MessageKey, string>>
export type PartialMessageCatalogue = Readonly<
  Partial<Record<MessageKey, string>>
>
export type CatalogueCollection = Readonly<
  Partial<Record<SupportedLocale, PartialMessageCatalogue>>
>

export const enINCatalogue = Object.freeze({
  ...enInSource,
}) as MessageCatalogue

export const knINCatalogue = Object.freeze({
  ...knInSource,
}) as MessageCatalogue

export const mlINCatalogue = Object.freeze({
  ...mlInSource,
}) as MessageCatalogue

export const taINCatalogue = Object.freeze({
  ...taInSource,
}) as MessageCatalogue

export const teINCatalogue = Object.freeze({
  ...teInSource,
}) as MessageCatalogue

export const hiINCatalogue = Object.freeze({
  ...hiInSource,
}) as MessageCatalogue

export const catalogues: Readonly<Record<SupportedLocale, MessageCatalogue>> =
  Object.freeze({
    [DEFAULT_LOCALE]: knINCatalogue,
    'ml-IN': mlINCatalogue,
    'ta-IN': taINCatalogue,
    'te-IN': teINCatalogue,
    'hi-IN': hiINCatalogue,
    [FALLBACK_LOCALE]: enINCatalogue,
  })

const frozenGovernanceEntries = Object.freeze(
  Object.fromEntries(
    Object.entries(governanceSource.catalogues).map(([locale, metadata]) => [
      locale,
      Object.freeze({ ...metadata }),
    ]),
  ),
)

export const catalogueGovernance = Object.freeze({
  ...governanceSource,
  supportedLocales: Object.freeze([...governanceSource.supportedLocales]),
  catalogues: frozenGovernanceEntries,
}) as unknown as GovernanceManifest

export const MESSAGE_KEYS = Object.freeze(
  Object.keys(enINCatalogue).sort(),
) as readonly MessageKey[]

export function isSupportedLocale(locale: string): locale is SupportedLocale {
  return (SUPPORTED_LOCALES as readonly string[]).includes(locale)
}

export function getCatalogue(locale: SupportedLocale): MessageCatalogue {
  return catalogues[locale]
}

