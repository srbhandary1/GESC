import { TUTOR_AVATAR_PORTRAIT_ID } from './tutorAvatarCatalog'

export const TUTOR_AVATAR_PREFERENCE_STORAGE_KEY =
  'ge-tutor-avatar-preference-v1'

export function readTutorAvatarPreference(): string {
  if (typeof window === 'undefined') {
    return TUTOR_AVATAR_PORTRAIT_ID
  }

  try {
    return window.localStorage.getItem(TUTOR_AVATAR_PREFERENCE_STORAGE_KEY)
      ?? TUTOR_AVATAR_PORTRAIT_ID
  } catch {
    return TUTOR_AVATAR_PORTRAIT_ID
  }
}

export const TUTOR_AVATAR_PREFERENCE_EVENT = 'ge-tutor-avatar-preference'

export function writeTutorAvatarPreference(avatarId: string): void {
  if (typeof window === 'undefined') {
    return
  }

  try {
    window.localStorage.setItem(TUTOR_AVATAR_PREFERENCE_STORAGE_KEY, avatarId)
    window.dispatchEvent(
      new CustomEvent(TUTOR_AVATAR_PREFERENCE_EVENT, { detail: avatarId }),
    )
  } catch {
    return undefined
  }
}

export function resolveStoredAvatarId(
  storedId: string,
  availableIds: readonly string[],
): string {
  if (availableIds.includes(storedId)) {
    return storedId
  }

  const firstGlb = availableIds.find((id) => id !== TUTOR_AVATAR_PORTRAIT_ID)
  return firstGlb ?? TUTOR_AVATAR_PORTRAIT_ID
}
