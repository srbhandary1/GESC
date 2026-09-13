import { useEffect, useState } from 'react'
import { listAvailableTutorAvatars } from './tutorAvatarCatalog'

export function useLicensedAvatarAvailable(): boolean {
  const [available, setAvailable] = useState(false)

  useEffect(() => {
    let cancelled = false

    void listAvailableTutorAvatars().then((options) => {
      if (!cancelled) {
        setAvailable(options.some((option) => option.path !== null))
      }
    })

    return () => {
      cancelled = true
    }
  }, [])

  return available
}
