import { existsSync, readFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const webRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..')
const requireFromWeb = createRequire(resolve(webRoot, 'package.json'))
const capacitorAndroidRoot = dirname(
  requireFromWeb.resolve('@capacitor/android/package.json'),
)
const capacitorBridgeActivity = resolve(
  capacitorAndroidRoot,
  'capacitor/src/main/java/com/getcapacitor/BridgeActivity.java',
)
const wrappers = [
  {
    activity: 'android-app/app/src/main/java/com/globalenglish/app/MainActivity.java',
    build: 'android-app/app/build.gradle',
    manifest: 'android-app/app/src/main/AndroidManifest.xml',
    shell: 'android-app/app/src/main/java/com/globalenglish/app/GlobalEnglishAndroidShell.java',
    smoke: 'android-app/app/src/androidTest/java/com/globalenglish/app/CriticalLearnerJourneyTest.java',
    staleUnit: 'android-app/app/src/test/java/com/getcapacitor/myapp/ExampleUnitTest.java',
    variables: 'android-app/variables.gradle',
  },
  {
    activity: 'android/app/src/main/java/com/globalenglish/demo/MainActivity.java',
    build: 'android/app/build.gradle',
    manifest: 'android/app/src/main/AndroidManifest.xml',
    shell: 'android/app/src/main/java/com/globalenglish/demo/GlobalEnglishAndroidShell.java',
    smoke: 'android/app/src/androidTest/java/com/globalenglish/demo/CriticalLearnerJourneyTest.java',
    staleUnit: 'android/app/src/test/java/com/getcapacitor/myapp/ExampleUnitTest.java',
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
      const build = source(wrapper.build)
      const manifest = source(wrapper.manifest)
      const shell = source(wrapper.shell)
      const variables = source(wrapper.variables)

      expect(variables).toContain('targetSdkVersion = 36')
      expect(activity).toContain('GlobalEnglishAndroidShell.install')
      expect(activity).toContain('attachShellIfReady')
      expect(activity).toContain('onStart')
      expect(shell).toContain('TAG_INSETS_INSTALLED')
      expect(shell).toContain('requestApplyInsets')
      expect(shell).toContain('--ge-native-font-scale')
      expect(shell).toContain("classList.toggle('ge-native-enlarged-text'")
      expect(shell).toContain('isEnlargedText(fontScale)')
      expect(build).toContain(
        'implementation "androidx.activity:activity:$androidxActivityVersion"',
      )
      expect(shell).toContain('OnBackPressedCallback')
      expect(shell).toContain('handleOnBackStarted')
      expect(shell).toContain('handleOnBackProgressed')
      expect(shell).toContain('__globalEnglishHandleAndroidBack')
      expect(shell).toContain('BACK_DISPATCH_TIMEOUT_MS = 1500L')
      expect(shell).toContain('webView.postDelayed(timeout, BACK_DISPATCH_TIMEOUT_MS)')
      expect(shell).toContain('fallThroughToDispatcher()')
      expect(shell).not.toMatch(/(?:public|protected)\s+void\s+onBackPressed\s*\(|KEYCODE_BACK/u)
      expect(manifest).not.toContain('enableOnBackInvokedCallback="false"')
      expect(manifest).toContain('android:windowSoftInputMode="adjustResize"')
      expect(existsSync(resolve(webRoot, wrapper.staleUnit))).toBe(false)
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
      if (wrapper.activity.includes('android-app/')) {
        expect(smoke).toContain('predictiveGestureBackWalksQuestionSessionAndLearnHistory')
        expect(smoke).toContain('__globalEnglishStartAndroidBackPreview')
        expect(smoke).toContain('microphonePermissionBranchesRecoverThroughAppSettings')
      }
    }
  })
})

function source(relativePath: string): string {
  return readFileSync(resolve(webRoot, relativePath), 'utf8')
}
