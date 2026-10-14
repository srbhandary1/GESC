import { DeviceConversationHistoryPanel } from './DeviceConversationHistoryPanel'
import { useEffect, useState } from 'react'
import type { MessageKey, ResolutionPolicy, SupportedLocale } from '@global-english/localization'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import type { ConversationJourneyApi, VoiceConsents } from './ConversationJourneyApi'
import { createClientId } from '../shared/createClientId'

/** Standing permission management belongs in Account > Privacy, not each turn. */
export function ConversationPrivacyPanel({ api, locale, localizationPolicy }: {
  readonly api: ConversationJourneyApi
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}) {
  const [listing, setListing] = useState<VoiceConsents | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)
  const text = (key: MessageKey) => resolveInterfaceMessage(key, locale, localizationPolicy).text
  useEffect(() => {
    let active = true
    void api.voiceConsents?.(locale).then(value => { if (active) setListing(value) }).catch(() => { if (active) setError(true) })
    return () => { active = false }
  }, [api, locale])
  const withdraw = async (purpose: 'voice_upload_and_transcription' | 'conversation_provider_processing' | 'transcript_history') => {
    if (busy || !api.withdrawVoiceConsent || !api.voiceConsents) return
    setBusy(true)
    setError(false)
    try {
      await api.withdrawVoiceConsent(purpose, { requested_locale: locale }, createClientId())
      setListing(await api.voiceConsents(locale))
      window.dispatchEvent(new Event('global-english:voice-consent-changed'))
    } catch { setError(true) } finally { setBusy(false) }
  }
  return <details className="conversation-journey__consent-details">
    <summary>{text('legal.privacy')}</summary>
    <p>{text('conversation.interactive.advisory')}</p>
    {listing ? <p lang={listing.notice.resolved_locale}>{listing.notice.notice}</p> : null}
    <p>{text('conversation.interactive.privacy')}</p>
    {listing?.consents.filter(consent => consent.state === 'granted').map(consent =>
      <button className="ge-button" disabled={busy} key={consent.purpose} onClick={() => void withdraw(consent.purpose)} type="button">
        {text(consent.purpose === 'voice_upload_and_transcription' ? 'conversation.voice.withdraw_upload' : consent.purpose === 'conversation_provider_processing' ? 'conversation.voice.withdraw_provider' : 'conversation.privacy.withdraw_history')}
      </button>)}
    {error ? <p role="alert">{text('conversation.interactive.provider_error')}</p> : null}
    <DeviceConversationHistoryPanel text={text} />
    <p><a href="/privacy.html">{text('legal.privacy')}</a></p>
  </details>
}
