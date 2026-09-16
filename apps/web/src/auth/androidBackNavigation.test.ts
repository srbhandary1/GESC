import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const webRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..')
const repositoryRoot = resolve(webRoot, '../..')
const capacitorBridgeActivity = resolve(
  repositoryRoot,
  'node_modules/.pnpm/@capacitor+android@8.5.0_@capacitor+core@8.5.0/node_modules/@capacitor/android/capacitor/src/main/java/com/getcapacitor/BridgeActivity.java',
)
const wrappers = [
  {
    activity: 'android-app/app/src/main/java/com/globalenglish/app/MainActivity.java',
    manifest: 'android-app/app/src/main/AndroidManifest.xml',
    shell: 'android-app/app/src/main/java/com/globalenglish/app/GlobalEnglishAndroidShell.java',
    smoke: 'android-app/app/src/androidTest/java/com/globalenglish/app/CriticalLearnerJourneyTest.java',
    variables: 'android-app/variables.gradle',
  },
  {
    activity: 'android/app/src/main/java/com/globalenglish/demo/MainActivity.java',
    manifest: 'android/app/src/main/AndroidManifest.xml',
    shell: 'android/app/src/main/java/com/globalenglish/demo/GlobalEnglishAndroidShell.java',
    smoke: 'android/app/src/androidTest/java/com/globalenglish/demo/CriticalLearnerJourneyTest.java',
    variables: 'android/variables.gradle',
  },
] as const

describe('Android 16 Back and window integration', () => {
  it('records that Capacitor 8.5 BridgeActivity has no Back dispatcher policy', () => {
    const bridgeActivity = readFileSync(capacitorBridgeActivity, 'utf8')

    expect(bridgeActivity).toContain('public class BridgeActivity extends AppCompatActivity')
    expect(bridgeActivity).not.toMatch(
      /OnBackPressedCallback|OnBackInvokedCallback|onBackPressed|KEYCODE_BACK|canGoBack|goBack/u,
    )
  })

  it('uses the supported dispatcher in both target-36 wrappers without opting out', () => {
    for (const wrapper of wrappers) {
      const activity = source(wrapper.activity)
      const manifest = source(wrapper.manifest)
      const shell = source(wrapper.shell)
      const variables = source(wrapper.variables)

      expect(variables).toContain('targetSdkVersion = 36')
      expect(activity).toContain('GlobalEnglishAndroidShell.install')
      expect(shell).toContain('OnBackPressedCallback')
      expect(shell).toContain('handleOnBackStarted')
      expect(shell).toContain('handleOnBackProgressed')
      expect(shell).toContain('__globalEnglishHandleAndroidBack')
      expect(shell).not.toMatch(/(?:public|protected)\s+void\s+onBackPressed\s*\(|KEYCODE_BACK/u)
      expect(manifest).not.toContain('enableOnBackInvokedCallback="false"')
      expect(manifest).toContain('android:windowSoftInputMode="adjustResize"')
    }
  })

  it('ships six named Espresso/UiAutomator journey contracts per package', () => {
    for (const wrapper of wrappers) {
      const smoke = source(wrapper.smoke)
      expect(smoke).toContain('onView(isAssignableFrom(WebView.class))')
      expect(smoke).toContain('UiDevice')
      expect(smoke).toContain('firstUseJourney')
      expect(smoke).toContain('lessonAndSystemBack')
      expect(smoke).toContain('quizSelectionSurvivesBackgroundAndResume')
      expect(smoke).toContain('speakPermissionDenial')
      expect(smoke).toMatch(/contextualPlans|plansSurface/u)
      expect(smoke).toContain('backFromSecondQuizQuestion')
    }
  })
})

function source(relativePath: string): string {
  return readFileSync(resolve(webRoot, relativePath), 'utf8')
}
