import type {
  MessageKey,
  MessageParameters,
} from '@global-english/localization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import {
  ANDROID_APK_FILENAME,
  ANDROID_APK_HREF,
} from '../marketing/androidDownload'

export const DEMO_ANDROID_APK_HREF = ANDROID_APK_HREF
export const DEMO_ANDROID_APK_FILENAME = ANDROID_APK_FILENAME

interface AndroidDemoDownloadProps {
  readonly message: (
    key: MessageKey,
    parameters?: MessageParameters,
  ) => InterfaceMessageResolution
  readonly variant?: 'card' | 'inline'
}

export function AndroidDemoDownload({
  message,
  variant = 'card',
}: AndroidDemoDownloadProps) {
  return (
    <a
      className={
        variant === 'inline'
          ? 'phase1-home__android phase1-home__android--inline'
          : 'ge-button ge-button--primary phase1-home__android'
      }
      download={ANDROID_APK_FILENAME}
      href={ANDROID_APK_HREF}
    >
      <LocalizedMessage
        resolution={message('demo.phase1.home.android_download')}
      />
    </a>
  )
}
