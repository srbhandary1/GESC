import { Capacitor } from '@capacitor/core'

/**
 * Android WebView only exposes getUserMedia on a secure origin
 * (`https://localhost` for the bundled Capacitor APK). A live-load
 * `http://` server.url is not a secure context, so mediaDevices is missing
 * and the UI must not pre-block before the user taps Speak.
 */
export function canAttemptMicrophoneCapture(): boolean {
  if (typeof window === 'undefined') {
    return false
  }

  return (
    window.isSecureContext === true ||
    (Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android')
  )
}
