export {
  catalogueGovernance,
  catalogues,
  DEFAULT_LOCALE,
  enINCatalogue,
  FALLBACK_LOCALE,
  getCatalogue,
  isSupportedLocale,
  knINCatalogue,
  MESSAGE_KEYS,
  SUPPORTED_LOCALES,
} from './catalogues.ts'
export type {
  CatalogueCollection,
  CatalogueGovernance,
  GovernanceManifest,
  MessageCatalogue,
  MessageKey,
  PartialMessageCatalogue,
  ReviewStatus,
  SupportedLocale,
} from './catalogues.ts'
export {
  isNfcNormalized,
  normalizeLocalizedText,
  ZERO_WIDTH_JOINER,
  ZERO_WIDTH_NON_JOINER,
} from './normalization.ts'
export {
  MissingFallbackMessageError,
  MissingMessageParameterError,
  resolveMessage,
} from './resolver.ts'
export type {
  FallbackEvent,
  MessageParameters,
  ResolvedMessage,
  ResolutionPolicy,
  ResolutionReason,
  ResolveMessageOptions,
} from './resolver.ts'
