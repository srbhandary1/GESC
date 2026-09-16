import { Capacitor, registerPlugin } from '@capacitor/core'

export type AndroidMicrophonePermissionStatus =
  | 'granted'
  | 'denied_once'
  | 'denied_permanently'

export interface GlobalEnglishMicrophonePermissionPlugin {
  requestAfterSpeakingAction(): Promise<{
    status: AndroidMicrophonePermissionStatus
  }>
}

export const globalEnglishMicrophonePermission =
  registerPlugin<GlobalEnglishMicrophonePermissionPlugin>(
    'GlobalEnglishMicrophonePermission',
  )

export function isNativeAndroid(): boolean {
  return Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android'
}
