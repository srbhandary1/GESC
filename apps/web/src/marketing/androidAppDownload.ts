/**
 * Invite-only sideload until a Play Store listing exists. Change this
 * constant to the store URL when Play replaces the APK download.
 *
 * Do not add an HTML `download` attribute: Chrome on Android then fetches the
 * 40+ MB APK as a blob and cannot resume. Let the system download manager
 * follow the Caddy Content-Disposition filename instead.
 */
export const ANDROID_APP_DOWNLOAD_HREF =
  '/downloads/global-spoken-english.apk'
