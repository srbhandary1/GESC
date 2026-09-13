/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_LOCAL_DEMO?: string
  readonly VITE_API_BASE_URL?: string
  readonly VITE_UI_TRANSLATION_PREVIEW?: string
  readonly VITE_MOBILE_API_ORIGIN?: string
  readonly VITE_MOBILE_OAUTH_AUTHORIZE_URL?: string
  readonly VITE_MOBILE_OAUTH_TOKEN_URL?: string
  readonly VITE_MOBILE_OAUTH_CLIENT_ID?: string
  readonly VITE_MOBILE_OAUTH_REDIRECT_URI?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
