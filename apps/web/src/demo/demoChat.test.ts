import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  demoOpenRouterConfigured,
  requestDemoChatReply,
  resetDemoChatStatusCache,
} from './demoChat'

describe('demo OpenRouter chat helper', () => {
  afterEach(() => {
    resetDemoChatStatusCache()
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('reports configured when /demo/chat/status says so', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ configured: true, model: 'openrouter/free' }),
      }),
    )

    await expect(demoOpenRouterConfigured()).resolves.toBe(true)
  })

  it('returns an OpenRouter reply when /demo/chat succeeds', async () => {
    const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/demo/chat/status')) {
        return {
          ok: true,
          json: async () => ({ configured: true }),
        }
      }
      return {
        ok: true,
        json: async () => ({
          reply: 'That sounds great. What happened next?',
          model: 'openrouter/free',
        }),
      }
    })
    vi.stubGlobal('fetch', fetchMock)

    const result = await requestDemoChatReply('I went to the market.')

    expect(result).toEqual({
      reply: 'That sounds great. What happened next?',
      source: 'openrouter',
      model: 'openrouter/free',
    })
  })

  it('returns null when OpenRouter is unconfigured', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ configured: false }),
      }),
    )

    await expect(requestDemoChatReply('Hello')).resolves.toBeNull()
  })
})
