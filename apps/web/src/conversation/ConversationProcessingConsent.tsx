import type { MessageKey, ResolutionPolicy, SupportedLocale } from '@global-english/localization'
import { useEffect, useRef, useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { isAbortError } from '../shared/isAbortError'
import type { ConversationJourneyApi, VoiceConsents } from './ConversationJourneyApi'

const PURPOSE = 'conversation_provider_processing' as const

/** A provider grant is independent of both recovery agreement and voice upload. */
export function ConversationProcessingConsent({ api, createClientId, locale, localizationPolicy, onGrantedChange, disabled = false }: {
  readonly api: ConversationJourneyApi
  readonly createClientId: () => string
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly onGrantedChange: (granted: boolean) => void
  readonly disabled?: boolean
}) {
  const [listing, setListing] = useState<VoiceConsents | null>(null)
  const [busy, setBusy] = useState(true)
  const [error, setError] = useState(false)
  const [refresh, setRefresh] = useState(0)
  const request = useRef<AbortController | null>(null)
  const command = useRef<{ action: 'grant' | 'withdraw'; locale: SupportedLocale; id: string } | null>(null)
  const lock = useRef(false)
  const message = (key: MessageKey) => resolveInterfaceMessage(key, locale, localizationPolicy)
  const granted = listing?.consents.some((consent) => consent.purpose === PURPOSE && consent.state === 'granted') === true

  useEffect(() => {
    const controller = new AbortController()
    request.current = controller
    onGrantedChange(false)
    void (async () => {
      await Promise.resolve()
      if (controller.signal.aborted) return
      setBusy(true)
      setError(false)
      setListing(null)
      try {
        if (!api.voiceConsents) throw new Error('consent_api_unavailable')
        const current = await api.voiceConsents(locale, controller.signal)
        if (controller.signal.aborted) return
        setListing(current)
        onGrantedChange(current.consents.some((consent) => consent.purpose === PURPOSE && consent.state === 'granted'))
      } catch (failure) {
        if (!isAbortError(failure) && !controller.signal.aborted) setError(true)
      } finally {
        if (!controller.signal.aborted) setBusy(false)
      }
    })()
    return () => { controller.abort(); request.current?.abort() }
  }, [api, locale, onGrantedChange, refresh])

  useEffect(() => {
    const changed = () => setRefresh(value => value + 1)
    window.addEventListener('global-english:voice-consent-changed', changed)
    return () => window.removeEventListener('global-english:voice-consent-changed', changed)
  }, [])

  const changeConsent = async (action: 'grant' | 'withdraw') => {
    if (lock.current || busy || disabled) return
    lock.current = true
    const controller = new AbortController()
    request.current = controller
    if (command.current?.action !== action || command.current.locale !== locale) {
      command.current = { action, locale, id: createClientId() }
    }
    onGrantedChange(false)
    setBusy(true)
    setError(false)
    try {
      if (!api.voiceConsents) throw new Error('consent_api_unavailable')
      if (action === 'grant') {
        if (!api.grantVoiceConsent) throw new Error('consent_api_unavailable')
        await api.grantVoiceConsent({ purpose: PURPOSE, requested_locale: locale }, command.current.id, controller.signal)
      } else {
        if (!api.withdrawVoiceConsent) throw new Error('consent_api_unavailable')
        await api.withdrawVoiceConsent(PURPOSE, { requested_locale: locale }, command.current.id, controller.signal)
      }
      if (controller.signal.aborted) return
      const current = await api.voiceConsents(locale, controller.signal)
      if (controller.signal.aborted) return
      setListing(current)
      onGrantedChange(current.consents.some((consent) => consent.purpose === PURPOSE && consent.state === 'granted'))
      command.current = null
    } catch (failure) {
      if (!isAbortError(failure) && !controller.signal.aborted) setError(true)
    } finally {
      lock.current = false
      if (!controller.signal.aborted) setBusy(false)
    }
  }

  if (granted && !busy && !error) return null

  return <section className="conversation-processing-consent" aria-label={message('conversation.voice.consent_provider').text} aria-busy={busy}>
    {listing ? <aside className="conversation-journey__voice-notice" lang={listing.notice.resolved_locale}>
      <p>{listing.notice.notice}</p>
    </aside> : null}
    {busy ? <p role="status"><LocalizedMessage resolution={message('conversation.interactive.provider_loading')} /></p> : null}
    {error ? <p role="alert"><LocalizedMessage resolution={message('conversation.interactive.provider_error')} /></p> : null}
    {listing ? <>
      {granted && !error ? <p role="status"><LocalizedMessage resolution={message('conversation.interactive.provider_granted')} /></p> : null}
      <button className="ge-button ge-button--secondary" disabled={busy || disabled} onClick={() => void changeConsent(granted ? 'withdraw' : 'grant')} type="button">
        <LocalizedMessage resolution={message(granted ? 'conversation.voice.withdraw_provider' : 'conversation.voice.consent_provider')} />
      </button>
    </> : !busy ? <button className="ge-button ge-button--secondary" disabled={disabled} onClick={() => setRefresh((value) => value + 1)} type="button"><LocalizedMessage resolution={message('learner.onboarding.retry')} /></button> : null}
  </section>
}
