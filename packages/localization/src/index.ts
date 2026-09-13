export {
  catalogueGovernance,
  catalogues,
  DEFAULT_LOCALE,
  enINCatalogue,
  FALLBACK_LOCALE,
  getCatalogue,
  hiINCatalogue,
  INTERFACE_LOCALES,
  isSupportedLocale,
  knINCatalogue,
  MESSAGE_KEYS,
  mlINCatalogue,
  SUPPORTED_LOCALES,
  taINCatalogue,
  teINCatalogue,
} from './catalogues.ts'
export type {
  CatalogueCollection,
  CatalogueGovernance,
  GovernanceManifest,
  InterfaceLocale,
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
