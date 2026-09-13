import { useEffect, useState } from 'react'
import {
  listAvailableTutorAvatars,
  TUTOR_AVATAR_PORTRAIT_ID,
  type TutorAvatarOption,
} from './tutorAvatarCatalog'
import {
  preloadTutorAvatarCanvas,
  preloadTutorAvatarModels,
} from './preloadTutorAvatars'
import {
  readTutorAvatarPreference,
  resolveStoredAvatarId,
  TUTOR_AVATAR_PREFERENCE_EVENT,
  TUTOR_AVATAR_PREFERENCE_STORAGE_KEY,
  writeTutorAvatarPreference,
} from './tutorAvatarPreference'

export function useTutorAvatarSelection(): {
  readonly avatars: readonly TutorAvatarOption[]
  readonly selectedAvatarId: string
  readonly selectedAvatar: TutorAvatarOption
  readonly selectedGlbPath: string | null
  readonly setSelectedAvatarId: (avatarId: string) => void
  readonly glbOptionsAvailable: boolean
} {
  const [avatars, setAvatars] = useState<readonly TutorAvatarOption[]>([
    { id: TUTOR_AVATAR_PORTRAIT_ID, label: 'Portrait', path: null },
  ])
  const [selectedAvatarId, setSelectedAvatarIdState] = useState(
    readTutorAvatarPreference(),
  )

  useEffect(() => {
    let cancelled = false

    void listAvailableTutorAvatars().then((options) => {
      if (cancelled) {
        return
      }

      setAvatars(options)
      preloadTutorAvatarModels(options)
      preloadTutorAvatarCanvas()
      setSelectedAvatarIdState((current) =>
        resolveStoredAvatarId(
          current,
          options.map((option) => option.id),
        ),
      )
    })

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    const syncPreference = (avatarId: string) => {
      setSelectedAvatarIdState((current) =>
        resolveStoredAvatarId(
          avatarId || current,
          avatars.map((option) => option.id),
        ),
      )
    }

    const onStorage = (event: StorageEvent) => {
      if (event.key === TUTOR_AVATAR_PREFERENCE_STORAGE_KEY && event.newValue) {
        syncPreference(event.newValue)
      }
    }

    const onPreference = (event: Event) => {
      syncPreference((event as CustomEvent<string>).detail)
    }

    window.addEventListener('storage', onStorage)
    window.addEventListener(TUTOR_AVATAR_PREFERENCE_EVENT, onPreference)

    return () => {
      window.removeEventListener('storage', onStorage)
      window.removeEventListener(TUTOR_AVATAR_PREFERENCE_EVENT, onPreference)
    }
  }, [avatars])

  const selected =
    avatars.find((avatar) => avatar.id === selectedAvatarId) ??
    avatars[0] ??
    { id: TUTOR_AVATAR_PORTRAIT_ID, label: 'Portrait', path: null }

  const setSelectedAvatarId = (avatarId: string) => {
    const resolved = resolveStoredAvatarId(
      avatarId,
      avatars.map((option) => option.id),
    )
    writeTutorAvatarPreference(resolved)
    setSelectedAvatarIdState(resolved)
  }

  return {
    avatars,
    selectedAvatar: selected,
    selectedAvatarId: selected.id,
    selectedGlbPath: selected.path,
    setSelectedAvatarId,
    glbOptionsAvailable: avatars.some(
      (avatar) => avatar.id !== TUTOR_AVATAR_PORTRAIT_ID,
    ),
  }
}
