import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useState } from 'react'
import { LocalizedMessage } from '../../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../../localization/interfaceLocalization'
import type { TutorAvatarOption } from './tutorAvatarCatalog'
import { TUTOR_AVATAR_PORTRAIT_ID } from './tutorAvatarCatalog'

export interface TutorAvatarControlProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly avatars: readonly TutorAvatarOption[]
  readonly selectedAvatarId: string
  readonly onAvatarChange: (avatarId: string) => void
}

export function TutorAvatarControl({
  locale,
  localizationPolicy,
  avatars,
  selectedAvatarId,
  onAvatarChange,
}: TutorAvatarControlProps) {
  const [announcedAvatarId, setAnnouncedAvatarId] = useState<string | null>(
    null,
  )
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  const labelFor = (avatar: TutorAvatarOption): string => {
    if (avatar.id === TUTOR_AVATAR_PORTRAIT_ID) {
      return message('tutor.avatar_option_portrait').text
    }

    return avatar.label
  }

  const announcedLabel =
    avatars.find((avatar) => avatar.id === announcedAvatarId) ?? null

  return (
    <div className="shell-theme-control shell-theme-control--compact">
      <label className="visually-hidden" htmlFor="tutor-avatar">
        <LocalizedMessage resolution={message('tutor.avatar_label')} />
      </label>
      <select
        className="shell-theme-control__select"
        id="tutor-avatar"
        value={selectedAvatarId}
        onChange={(event) => {
          const next = event.currentTarget.value
          onAvatarChange(next)
          setAnnouncedAvatarId(next)
        }}
      >
        {avatars.map((avatar) => (
          <option key={avatar.id} value={avatar.id}>
            {labelFor(avatar)}
          </option>
        ))}
      </select>
      <p className="visually-hidden" aria-atomic="true" aria-live="polite">
        {announcedLabel === null ? null : (
          <LocalizedMessage
            resolution={resolveInterfaceMessage(
              'tutor.avatar_changed',
              locale,
              localizationPolicy,
              {
                avatar: labelFor(announcedLabel),
              },
            )}
          />
        )}
      </p>
    </div>
  )
}
