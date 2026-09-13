export type DemoTtsProviderId = 'azure' | 'sarvam' | 'elevenlabs'

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

export interface DemoTtsSuccess {
  readonly audio: ArrayBuffer | Buffer
  readonly contentType: string
  readonly voice: string
  readonly provider: DemoTtsProviderId
}

export declare const DEMO_TTS_PATH: '/demo/tts'
export declare const DEMO_TTS_STATUS_PATH: '/demo/tts/status'
export declare const DEMO_AZURE_VOICES: Readonly<Record<string, string>>

export declare function requestPath(url: string | undefined): string
export declare function isDemoTtsPath(pathname: string): boolean
export declare function resolveDemoTtsCredentials(): DemoTtsAzureCredentials | null
export declare function applyDemoTtsCors(
  res: { setHeader(name: string, value: string): void },
  req: { headers?: { origin?: string } },
): void
export declare function synthesizeWithFailover(
  locale: string,
  text: string,
  bundle: DemoTtsBundle,
): Promise<DemoTtsSuccess>
export declare function handleDemoTtsRequest(
  req: {
    method?: string
    url?: string
    headers?: { [key: string]: string | string[] | undefined }
    on?(event: string, listener: (...args: unknown[]) => void): void
  },
  res: {
    statusCode: number
    setHeader(name: string, value: string): void
    end(body?: string | Buffer): void
  },
): Promise<boolean>
export declare function attachDemoTtsMiddleware(middlewares: {
  use(handler: (req: unknown, res: unknown, next: () => void) => void): void
}): void
