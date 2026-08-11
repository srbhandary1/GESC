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
    expect(source).toContain('"installation_ulid"')
    expect(source).toContain('"refresh_token"')
    expect(source).toContain('"pending_authorization"')
    expect(source).toContain('public synchronized void take(')
    expect(source).toContain('.commit()')
    expect(source).not.toContain('.apply()')
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

    expect(activity).toContain(
      '"com.globalenglish.app".equals(data.getScheme())',
    )
    expect(activity).toContain(
      '"/oauth/callback".equals(data.getPath())',
    )
    expect(activity).toContain('JSONObject.quote(url)')
    expect(activity).toContain(
      'window.__globalEnglishPendingOauthCallback=',
    )
    expect(manifest).toContain('android:scheme="com.globalenglish.app"')
    expect(manifest).not.toContain('com.globalenglish.demo:/oauth/callback')
  })
})
