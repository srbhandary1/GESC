import { useEffect, useRef } from 'react'
import { SPEAKING_TURN_COMPLETED_EVENT } from './speakingTurnCompleted'

const STORAGE_PREFIX = 'global-english:pwa-install:v1:'

interface BeforeInstallPromptEvent extends Event {
  readonly prompt: () => Promise<void>
}

export interface WebInstallPromptProps {
  readonly profileKey: string
}

export function WebInstallPrompt({ profileKey }: WebInstallPromptProps) {
  const storageKey = `${STORAGE_PREFIX}${profileKey}`
  const speakingCompleted = useRef(false)
  const installEvent = useRef<BeforeInstallPromptEvent | null>(null)
  const asked = useRef(false)

  useEffect(() => {
    try {
      if (globalThis.localStorage.getItem(storageKey) !== null) {
        asked.current = true
      }
    } catch {
      asked.current = true
    }

    const askOnce = () => {
      if (
        asked.current ||
        !speakingCompleted.current ||
        installEvent.current === null
      ) {
        return
      }
      asked.current = true
      try {
        globalThis.localStorage.setItem(storageKey, 'prompted')
      } catch {
        // Best-effort once-per-profile marker; never credentials.
      }
      void installEvent.current.prompt()
    }

    const onSpeaking = () => {
      speakingCompleted.current = true
      askOnce()
    }
    const onPrompt = (event: Event) => {
      event.preventDefault()
      installEvent.current = event as BeforeInstallPromptEvent
      askOnce()
    }

    globalThis.addEventListener(SPEAKING_TURN_COMPLETED_EVENT, onSpeaking)
    globalThis.addEventListener('beforeinstallprompt', onPrompt)
    return () => {
      globalThis.removeEventListener(SPEAKING_TURN_COMPLETED_EVENT, onSpeaking)
      globalThis.removeEventListener('beforeinstallprompt', onPrompt)
    }
  }, [storageKey])

  return null
}
