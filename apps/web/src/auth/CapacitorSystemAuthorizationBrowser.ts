import type { SystemAuthorizationBrowser } from './AndroidPkceAuthenticationAdapter'
import {
  globalEnglishSystemBrowser,
  type GlobalEnglishSystemBrowserPlugin,
} from './systemAuthorizationBrowserPlugin'

export class CapacitorSystemAuthorizationBrowser
  implements SystemAuthorizationBrowser
{
  readonly #plugin: GlobalEnglishSystemBrowserPlugin

  constructor(plugin: GlobalEnglishSystemBrowserPlugin = globalEnglishSystemBrowser) {
    this.#plugin = plugin
  }

  async open(url: string): Promise<void> {
    await this.#plugin.open({ url })
  }
}
