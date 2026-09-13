import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const webRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..')

describe('Android microphone permission', () => {
  it('declares RECORD_AUDIO and requests it at launch in both wrappers', () => {
    const demoManifest = readFileSync(
      resolve(webRoot, 'android/app/src/main/AndroidManifest.xml'),
      'utf8',
    )
    const productionManifest = readFileSync(
      resolve(webRoot, 'android-app/app/src/main/AndroidManifest.xml'),
      'utf8',
    )
    const demoActivity = readFileSync(
      resolve(
        webRoot,
        'android/app/src/main/java/com/globalenglish/demo/MainActivity.java',
      ),
      'utf8',
    )
    const productionActivity = readFileSync(
      resolve(
        webRoot,
        'android-app/app/src/main/java/com/globalenglish/app/MainActivity.java',
      ),
      'utf8',
    )

    for (const manifest of [demoManifest, productionManifest]) {
      expect(manifest).toContain('android.permission.RECORD_AUDIO')
      expect(manifest).toContain('android.hardware.microphone')
    }

    for (const activity of [demoActivity, productionActivity]) {
      expect(activity).toContain('Manifest.permission.RECORD_AUDIO')
      expect(activity).toContain('requestPermissions')
      expect(activity).toContain('requestMicrophonePermission')
    }
  })
})
