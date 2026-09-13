import type {
  MessageKey,
  MessageParameters,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useState, type FormEvent } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { demoTtsUrl } from './demoTts'

export interface DemoTtsAdminSettingsProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}

interface ProviderFlags {
  readonly azure: boolean
  readonly sarvam: boolean
  readonly elevenlabs: boolean
}

const emptyFlags: ProviderFlags = {
  azure: false,
  sarvam: false,
  elevenlabs: false,
}

export function DemoTtsAdminSettings({
  locale,
  localizationPolicy,
}: DemoTtsAdminSettingsProps) {
  const message = (key: MessageKey, parameters: MessageParameters = {}) =>
    resolveInterfaceMessage(key, locale, localizationPolicy, parameters)
  const [adminToken, setAdminToken] = useState('')
  const [azureEndpoint, setAzureEndpoint] = useState('')
  const [azureKey, setAzureKey] = useState('')
  const [sarvamKey, setSarvamKey] = useState('')
  const [sarvamSpeaker, setSarvamSpeaker] = useState('')
  const [elevenKey, setElevenKey] = useState('')
  const [elevenVoice, setElevenVoice] = useState('')
  const [providerOrder, setProviderOrder] = useState('')
  const [providers, setProviders] = useState<ProviderFlags>(emptyFlags)
  const [masked, setMasked] = useState<Record<string, string>>({})
  const [status, setStatus] = useState<'idle' | 'saved' | 'error' | 'denied'>(
    'idle',
  )
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false
    void fetch(demoTtsUrl('/demo/tts/status'), {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    })
      .then(async (response) => {
        if (!response.ok) {
          return
        }
        const body = (await response.json()) as {
          providers?: Partial<ProviderFlags>
        }
        if (!cancelled && body.providers !== undefined) {
          setProviders({
            azure: body.providers.azure === true,
            sarvam: body.providers.sarvam === true,
            elevenlabs: body.providers.elevenlabs === true,
          })
        }
      })
      .catch(() => undefined)

    return () => {
      cancelled = true
    }
  }, [])

  const loadMasked = async () => {
    const response = await fetch(demoTtsUrl('/demo/tts/settings'), {
      headers: {
        Accept: 'application/json',
        'X-Demo-Tts-Admin': adminToken,
      },
      cache: 'no-store',
    })
    if (!response.ok) {
      throw new Error(`settings_${response.status}`)
    }
    const body = (await response.json()) as {
      azure?: { endpoint?: string; key?: string }
      sarvam?: { speaker?: string; api_key?: string }
      elevenlabs?: { voice_id?: string; api_key?: string }
      provider_order?: string
    }
    setMasked({
      azure: body.azure?.key ?? '',
      sarvam: body.sarvam?.api_key ?? '',
      elevenlabs: body.elevenlabs?.api_key ?? '',
    })
    if ((body.azure?.endpoint ?? '') !== '') {
      setAzureEndpoint(body.azure?.endpoint ?? '')
    }
    if ((body.sarvam?.speaker ?? '') !== '') {
      setSarvamSpeaker(body.sarvam?.speaker ?? '')
    }
    if ((body.elevenlabs?.voice_id ?? '') !== '') {
      setElevenVoice(body.elevenlabs?.voice_id ?? '')
    }
    setProviderOrder(body.provider_order ?? '')
  }

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setBusy(true)
    setStatus('idle')
    try {
      const response = await fetch(demoTtsUrl('/demo/tts/settings'), {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Demo-Tts-Admin': adminToken,
        },
        body: JSON.stringify({
          azure_endpoint: azureEndpoint,
          azure_key: azureKey,
          sarvam_api_key: sarvamKey,
          sarvam_speaker: sarvamSpeaker,
          elevenlabs_api_key: elevenKey,
          elevenlabs_voice_id: elevenVoice,
          provider_order: providerOrder,
        }),
      })
      if (response.status === 401 || response.status === 503) {
        setStatus('denied')
        return
      }
      if (!response.ok) {
        setStatus('error')
        return
      }
      setAzureKey('')
      setSarvamKey('')
      setElevenKey('')
      await loadMasked()
      const statusResponse = await fetch(demoTtsUrl('/demo/tts/status'), {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      })
      if (statusResponse.ok) {
        const body = (await statusResponse.json()) as {
          providers?: Partial<ProviderFlags>
        }
        setProviders({
          azure: body.providers?.azure === true,
          sarvam: body.providers?.sarvam === true,
          elevenlabs: body.providers?.elevenlabs === true,
        })
      }
      setStatus('saved')
    } catch {
      setStatus('error')
    } finally {
      setBusy(false)
    }
  }

  return (
    <article className="demo-tts-admin">
      <h2>
        <LocalizedMessage resolution={message('demo.tts.admin.title')} />
      </h2>
      <p>
        <LocalizedMessage resolution={message('demo.tts.admin.lead')} />
      </p>
      <ul className="demo-tts-admin__status" aria-label={message('demo.tts.admin.status').text}>
        {(['sarvam', 'elevenlabs', 'azure'] as const).map((provider) => (
          <li key={provider} data-configured={providers[provider] ? 'true' : 'false'}>
            <strong>{provider}</strong>{' '}
            <LocalizedMessage
              resolution={message(
                providers[provider]
                  ? 'demo.tts.admin.configured'
                  : 'demo.tts.admin.missing',
              )}
            />
          </li>
        ))}
      </ul>
      <p className="phase1-muted">
        <LocalizedMessage resolution={message('demo.tts.admin.rotate')} />
      </p>
      <form className="demo-tts-admin__form" onSubmit={(event) => void submit(event)}>
        <label>
          <LocalizedMessage resolution={message('demo.tts.admin.token')} />
          <input
            autoComplete="off"
            onChange={(event) => setAdminToken(event.currentTarget.value)}
            required
            type="password"
            value={adminToken}
          />
        </label>
        <p className="phase1-muted">
          <LocalizedMessage resolution={message('demo.tts.admin.token_hint')} />
        </p>
        <label>
          <LocalizedMessage resolution={message('demo.tts.admin.azure_endpoint')} />
          <input
            autoComplete="off"
            onChange={(event) => setAzureEndpoint(event.currentTarget.value)}
            type="url"
            value={azureEndpoint}
          />
        </label>
        <label>
          <LocalizedMessage resolution={message('demo.tts.admin.azure_key')} />
          <input
            autoComplete="off"
            onChange={(event) => setAzureKey(event.currentTarget.value)}
            placeholder={masked.azure}
            type="password"
            value={azureKey}
          />
        </label>
        <label>
          <LocalizedMessage resolution={message('demo.tts.admin.sarvam_key')} />
          <input
            autoComplete="off"
            onChange={(event) => setSarvamKey(event.currentTarget.value)}
            placeholder={masked.sarvam}
            type="password"
            value={sarvamKey}
          />
        </label>
        <label>
          <LocalizedMessage resolution={message('demo.tts.admin.sarvam_speaker')} />
          <input
            autoComplete="off"
            onChange={(event) => setSarvamSpeaker(event.currentTarget.value)}
            type="text"
            value={sarvamSpeaker}
          />
        </label>
        <label>
          <LocalizedMessage resolution={message('demo.tts.admin.eleven_key')} />
          <input
            autoComplete="off"
            onChange={(event) => setElevenKey(event.currentTarget.value)}
            placeholder={masked.elevenlabs}
            type="password"
            value={elevenKey}
          />
        </label>
        <label>
          <LocalizedMessage resolution={message('demo.tts.admin.eleven_voice')} />
          <input
            autoComplete="off"
            onChange={(event) => setElevenVoice(event.currentTarget.value)}
            type="text"
            value={elevenVoice}
          />
        </label>
        <label>
          <LocalizedMessage resolution={message('demo.tts.admin.order')} />
          <select
            onChange={(event) => setProviderOrder(event.currentTarget.value)}
            value={providerOrder}
          >
            <option value="">
              {message('demo.tts.admin.order.auto').text}
            </option>
            <option value="sarvam,elevenlabs,azure">
              sarvam, elevenlabs, azure
            </option>
            <option value="elevenlabs,azure,sarvam">
              elevenlabs, azure, sarvam
            </option>
            <option value="azure,sarvam,elevenlabs">
              azure, sarvam, elevenlabs
            </option>
          </select>
        </label>
        <button className="ge-button ge-button--primary" disabled={busy} type="submit">
          <LocalizedMessage resolution={message('demo.tts.admin.save')} />
        </button>
        {status === 'saved' ? (
          <p role="status">
            <LocalizedMessage resolution={message('demo.tts.admin.saved')} />
          </p>
        ) : null}
        {status === 'error' ? (
          <p role="alert">
            <LocalizedMessage resolution={message('demo.tts.admin.error')} />
          </p>
        ) : null}
        {status === 'denied' ? (
          <p role="alert">
            <LocalizedMessage resolution={message('demo.tts.admin.denied')} />
          </p>
        ) : null}
      </form>
    </article>
  )
}
