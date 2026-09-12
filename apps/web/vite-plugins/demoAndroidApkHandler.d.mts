export declare const DEMO_ANDROID_APK_ROUTE: '/downloads/global-english-demo.apk'
export declare const DEMO_ANDROID_APK_FILENAME: 'global-english-demo.apk'

export declare function demoAndroidApkPath(webRoot?: string): string
export declare function handleDemoAndroidApkRequest(
  req: { method?: string; url?: string },
  res: {
    statusCode: number
    setHeader(name: string, value: string): void
    end(body?: string): void
  },
  webRoot?: string,
): boolean
export declare function attachDemoAndroidApkMiddleware(
  middlewares: {
    use(handler: (req: unknown, res: unknown, next: () => void) => void): void
  },
  webRoot?: string,
): void
