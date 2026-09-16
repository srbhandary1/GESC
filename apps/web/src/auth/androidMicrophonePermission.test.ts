import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const webRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..')

const wrappers = [
  {
    activity: 'android-app/app/src/main/java/com/globalenglish/app/MainActivity.java',
    applicationId: 'com.globalenglish.app',
    chromeClient:
      'android-app/app/src/main/java/com/globalenglish/app/GlobalEnglishWebChromeClient.java',
    manifest: 'android-app/app/src/main/AndroidManifest.xml',
    plugin:
      'android-app/app/src/main/java/com/globalenglish/app/GlobalEnglishMicrophonePermissionPlugin.java',
  },
  {
    activity: 'android/app/src/main/java/com/globalenglish/demo/MainActivity.java',
    applicationId: 'com.globalenglish.demo',
    chromeClient:
      'android/app/src/main/java/com/globalenglish/demo/GlobalEnglishWebChromeClient.java',
    manifest: 'android/app/src/main/AndroidManifest.xml',
    plugin:
      'android/app/src/main/java/com/globalenglish/demo/GlobalEnglishMicrophonePermissionPlugin.java',
  },
] as const

describe('Android contextual microphone permission', () => {
  it('keeps the manifest declaration without requesting permission in onCreate', () => {
    for (const wrapper of wrappers) {
      const manifest = source(wrapper.manifest)
      const activity = source(wrapper.activity)
      const onCreate = activity.slice(
        activity.indexOf('public void onCreate'),
        activity.indexOf('\n    }', activity.indexOf('public void onCreate')),
      )

      expect(manifest).toContain('android.permission.RECORD_AUDIO')
      expect(manifest).toContain('android.hardware.microphone')
      expect(activity).toContain(`package ${wrapper.applicationId};`)
      expect(activity).toContain(
        'registerPlugin(GlobalEnglishMicrophonePermissionPlugin.class)',
      )
      expect(onCreate).not.toContain('requestPermissions')
      expect(onCreate).not.toContain('requestPermissionForAlias')
      expect(onCreate).not.toContain('RECORD_AUDIO')
    }
  })

  it('owns the contextual request and grants WebView audio only after runtime permission', () => {
    for (const wrapper of wrappers) {
      const plugin = source(wrapper.plugin)
      const chromeClient = source(wrapper.chromeClient)

      expect(plugin).toContain('requestAfterSpeakingAction')
      expect(plugin).toContain('requestPermissionForAlias')
      expect(plugin).toContain('shouldShowRequestPermissionRationale')
      expect(plugin).toContain('"granted"')
      expect(plugin).toContain('"denied_once"')
      expect(plugin).toContain('"denied_permanently"')
      expect(chromeClient).toContain('RESOURCE_AUDIO_CAPTURE')
      expect(chromeClient).toContain('Manifest.permission.RECORD_AUDIO')
      expect(chromeClient).toContain('PackageManager.PERMISSION_GRANTED')
      expect(chromeClient).toContain('"https".equalsIgnoreCase')
      expect(chromeClient).toContain('"localhost".equalsIgnoreCase')
      expect(chromeClient).toContain('request.deny()')
    }
  })
})

function source(relativePath: string): string {
  return readFileSync(resolve(webRoot, relativePath), 'utf8')
}
