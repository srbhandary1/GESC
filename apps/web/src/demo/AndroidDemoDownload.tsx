import type {
  MessageKey,
  MessageParameters,
} from '@global-english/localization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'

export const DEMO_ANDROID_APK_HREF = '/downloads/global-english-demo.apk'
export const DEMO_ANDROID_APK_FILENAME = 'global-english-demo.apk'

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
      download={DEMO_ANDROID_APK_FILENAME}
      href={DEMO_ANDROID_APK_HREF}
    >
      <LocalizedMessage
        resolution={message('demo.phase1.home.android_download')}
      />
    </a>
  )
}
