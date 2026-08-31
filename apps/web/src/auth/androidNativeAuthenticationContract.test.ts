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

  it('opens only the configured authorization path from the foreground activity', () => {
    const source = readFileSync(
      resolve(
        androidMain,
        'java/com/globalenglish/app/GlobalEnglishSystemBrowserPlugin.java',
      ),
      'utf8',
    )

    expect(source).toContain('BuildConfig.MOBILE_API_ORIGIN')
    expect(source).toContain('Activity activity = getActivity()')
    expect(source).toContain('isAllowedAuthorizationUrl')
    expect(source).toContain('isAllowedMobileApiOrigin')
    expect(source).toContain('"/oauth/authorize".equals(uri.getPath())')
    expect(source).toContain('port == 8080')
    expect(source).toContain('customTabsIntent.launchUrl(activity, uri)')
    expect(source).not.toContain('!"https".equals(uri.getScheme())')
    expect(source).not.toContain('Intent.FLAG_ACTIVITY_NEW_TASK')
    expect(source).not.toContain('launchUrl(getContext(), uri)')
  })

  it('fails APK packaging closed when the native API origin is missing', () => {
    const gradle = readFileSync(
      resolve(androidMain, '../../build.gradle'),
      'utf8',
    )
    const publish = readFileSync(
      resolve(
        authDirectory,
        '../../../../scripts/publish-soft-launch-android-apk.sh',
      ),
      'utf8',
    )

    expect(gradle).toContain('packagingRequested')
    expect(gradle).toContain('isAllowedMobileApiOrigin(mobileApiOrigin)')
    expect(gradle).toContain('empty origin makes every Sign in tap fail closed')
    expect(publish).toContain('export VITE_MOBILE_API_ORIGIN="${ORIGIN}"')
    expect(publish).toContain('export VITE_MOBILE_OAUTH_CLIENT_ID="${OAUTH_CLIENT_ID}"')
  })

  it('declares Custom Tabs package visibility for Android 11+', () => {
    const manifest = readFileSync(
      resolve(androidMain, 'AndroidManifest.xml'),
      'utf8',
    )

    expect(manifest).toContain('android.support.customtabs.action.CustomTabsService')
    expect(manifest).toContain('android.intent.category.BROWSABLE')
  })
})
