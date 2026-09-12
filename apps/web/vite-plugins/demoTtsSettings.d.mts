export type DemoTtsProviderId = 'azure' | 'sarvam' | 'elevenlabs'

export interface DemoTtsFileSettings {
  azure_speech_endpoint?: string
  azure_speech_key?: string
  sarvam_api_key?: string
  sarvam_base_url?: string
  sarvam_speaker?: string
  sarvam_model?: string
  elevenlabs_api_key?: string
  elevenlabs_voice_id?: string
  elevenlabs_model_id?: string
  elevenlabs_base_url?: string
  tts_provider_order?: string
}

export interface DemoTtsAzureCredentials {
  readonly endpoint: string
  readonly key: string
}

export interface DemoTtsSarvamCredentials {
  readonly key: string
  readonly baseUrl: string
  readonly speaker: string
  readonly model: string
}

export interface DemoTtsElevenLabsCredentials {
  readonly key: string
  readonly voiceId: string
  readonly modelId: string
  readonly baseUrl: string
}

export interface DemoTtsBundle {
  readonly azure: DemoTtsAzureCredentials | null
  readonly sarvam: DemoTtsSarvamCredentials | null
  readonly elevenlabs: DemoTtsElevenLabsCredentials | null
  readonly providerOrder: string
}

export interface DemoTtsMaskedSettings {
  readonly azure: {
    readonly endpoint: string
    readonly key: string
    readonly configured: boolean
    readonly from_env: boolean
  }
  readonly sarvam: {
    readonly base_url: string
    readonly speaker: string
    readonly model: string
    readonly api_key: string
    readonly configured: boolean
  }
  readonly elevenlabs: {
    readonly voice_id: string
    readonly model_id: string
    readonly api_key: string
    readonly configured: boolean
  }
  readonly provider_order: string
  readonly stored_fields: string[]
  readonly settings_fingerprint: string
}

export declare const DEMO_TTS_PROVIDERS: readonly DemoTtsProviderId[]
export declare const DEMO_TTS_SETTINGS_PATH: '/demo/tts/settings'
export declare const CLEAR_SECRET: '__clear__'

export declare function demoTtsSettingsFilePath(): string
export declare function readSecretFile(filePath: string | undefined): string
export declare function readDemoTtsSettingsFile(): DemoTtsFileSettings
export declare function writeDemoTtsSettingsFile(next: DemoTtsFileSettings): void
export declare function maskSecret(value: string): string
export declare function parseProviderOrder(value: string | undefined): DemoTtsProviderId[]
export declare function defaultProviderOrder(locale: string): DemoTtsProviderId[]
export declare function resolveProviderOrder(
  locale: string,
  explicitOrder: string | undefined,
): DemoTtsProviderId[]
export declare function resolveDemoTtsBundle(): DemoTtsBundle
export declare function neuralProvidersConfigured(
  bundle?: DemoTtsBundle,
): { azure: boolean; sarvam: boolean; elevenlabs: boolean }
export declare function anyNeuralConfigured(bundle?: DemoTtsBundle): boolean
export declare function resolveAdminToken(): string
export declare function adminTokenMatches(provided: string | undefined): boolean
export declare function readAdminTokenFromRequest(
  req: { headers?: { [key: string]: string | string[] | undefined } },
  payload?: { admin_token?: string },
): string
export declare function mergeSettingsUpdate(
  current: DemoTtsFileSettings,
  payload: Record<string, unknown>,
): DemoTtsFileSettings
export declare function maskedSettingsView(): DemoTtsMaskedSettings
