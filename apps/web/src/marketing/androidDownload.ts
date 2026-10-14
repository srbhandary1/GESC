import release from '../appRelease.json'

/** Frozen into each installed APK; shared with Gradle to avoid version drift. */
export const APP_VERSION = release.versionName
export const ANDROID_APK_FILENAME = `global-spoken-english-${APP_VERSION}.apk`
export const ANDROID_APK_HREF = `/downloads/${ANDROID_APK_FILENAME}`
