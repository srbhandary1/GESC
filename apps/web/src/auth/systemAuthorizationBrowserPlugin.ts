import { registerPlugin } from '@capacitor/core'

/**
 * Project-owned system-browser opener (Chrome Custom Tabs / VIEW intent).
 * Must not use an embedded WebView credential form (ADR-0002).
 */
export interface GlobalEnglishSystemBrowserPlugin {
  open(options: { url: string }): Promise<void>
}

export const globalEnglishSystemBrowser =
  registerPlugin<GlobalEnglishSystemBrowserPlugin>(
    'GlobalEnglishSystemBrowser',
  )
