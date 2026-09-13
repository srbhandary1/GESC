import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const authDirectory = dirname(fileURLToPath(import.meta.url))
const androidMain = resolve(
  authDirectory,
  '../../android-app/app/src/main',
)

describe('production Android native authentication contract', () => {
  it('keeps the credential allowlist in encrypted native storage', () => {
    const source = readFileSync(
      resolve(
        androidMain,
        'java/com/globalenglish/app/GlobalEnglishSecureCredentialsPlugin.java',
      ),
      'utf8',
    )

    expect(source).toContain('EncryptedSharedPreferences.create(')
    expect(source).toContain('MasterKey.KeyScheme.AES256_GCM')
    expect(source).toContain('REFRESH_TOKEN_KEY = "refresh_token"')
    expect(source).toContain('installation')
    expect(source).not.toContain('android.preference')
  })

  it('delivers only the production callback with JSON-safe bridge encoding', () => {
    const activity = readFileSync(
      resolve(
        androidMain,
        'java/com/globalenglish/app/MainActivity.java',
      ),
      'utf8',
    )
    const manifest = readFileSync(
      resolve(androidMain, 'AndroidManifest.xml'),
      'utf8',
    )

    expect(activity).toContain('com.globalenglish.app:/oauth/callback')
    expect(manifest).toContain('android:scheme="com.globalenglish.app"')
    expect(manifest).not.toContain('com.globalenglish.demo:/oauth/callback')
  })

  it('opens only the configured HTTPS authorization path from the foreground activity', () => {
    const source = readFileSync(
      resolve(
        androidMain,
        'java/com/globalenglish/app/GlobalEnglishSystemBrowserPlugin.java',
      ),
      'utf8',
    )

    expect(source).toContain('customTabsIntent.launchUrl')
    expect(source).toContain('https')
    expect(source).toContain('/oauth/authorize')
  })
})
