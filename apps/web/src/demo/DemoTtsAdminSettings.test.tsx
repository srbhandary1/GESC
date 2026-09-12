import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { DemoTtsAdminSettings } from './DemoTtsAdminSettings'

afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

describe('DemoTtsAdminSettings', () => {
  it('shows provider configuration flags and saves without storing the admin token', async () => {
    const user = userEvent.setup()
    const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input)
      if (url.includes('/demo/tts/status')) {
        return {
          ok: true,
          json: async () => ({
            configured: true,
            providers: { azure: true, sarvam: false, elevenlabs: false },
          }),
        }
      }
      if (url.includes('/demo/tts/settings') && init?.method === 'POST') {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            saved: true,
            settings: {
              azure: { endpoint: 'https://example.cognitiveservices.azure.com', key: '••••cdef' },
              sarvam: { api_key: '', speaker: '' },
              elevenlabs: { api_key: '', voice_id: '' },
              provider_order: 'azure,sarvam,elevenlabs',
            },
          }),
        }
      }
      return {
        ok: true,
        json: async () => ({
          azure: { endpoint: '', key: '' },
          sarvam: { api_key: '', speaker: '' },
          elevenlabs: { api_key: '', voice_id: '' },
          provider_order: '',
        }),
      }
    })
    vi.stubGlobal('fetch', fetchMock)

    render(
      <DemoTtsAdminSettings
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('heading', { name: 'Voice providers' }),
    ).toBeInTheDocument()
    expect(await screen.findByText('Configured')).toBeInTheDocument()
    expect(screen.getAllByText('Not configured')).toHaveLength(2)

    await user.type(
      screen.getByLabelText('Host admin token'),
      'typed-once-token',
    )
    await user.type(
      screen.getByLabelText('Azure Speech key'),
      'azure-rotated-key',
    )
    await user.selectOptions(
      screen.getByLabelText('Preferred voice provider'),
      'azure,sarvam,elevenlabs',
    )
    await user.click(screen.getByRole('button', { name: 'Save voice settings' }))

    expect(
      await screen.findByRole('status'),
    ).toHaveTextContent('Voice settings saved on this host.')
    const saveCall = fetchMock.mock.calls.find((call) => {
      const init = call[1] as RequestInit | undefined
      return init?.method === 'POST'
    })
    expect(saveCall?.[1]).toEqual(
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({
          'X-Demo-Tts-Admin': 'typed-once-token',
        }),
      }),
    )
    expect(window.localStorage.getItem('typed-once-token')).toBeNull()
    expect(window.localStorage.length).toBe(0)
  })

  it('shows a denied state when the host rejects the admin token', async () => {
    const user = userEvent.setup()
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        if (String(input).includes('/demo/tts/settings') && init?.method === 'POST') {
          return { ok: false, status: 401, json: async () => ({ error: 'admin_unauthorized' }) }
        }
        return {
          ok: true,
          json: async () => ({
            configured: false,
            providers: { azure: false, sarvam: false, elevenlabs: false },
          }),
        }
      }),
    )

    render(
      <DemoTtsAdminSettings
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )
    await user.type(screen.getByLabelText('Host admin token'), 'wrong-token')
    await user.click(screen.getByRole('button', { name: 'Save voice settings' }))
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Admin token was not accepted.',
    )
  })
})
