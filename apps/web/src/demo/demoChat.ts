/**
 * Demo Friend chat helper: OpenRouter free model via local `/demo/chat`,
 * with deterministic pattern fallback when unconfigured or failing.
 */

export interface DemoChatResult {
  readonly reply: string
  readonly source: 'openrouter' | 'pattern'
  readonly model?: string
}

let chatConfiguredCache: boolean | null = null

export function resetDemoChatStatusCache(): void {
  chatConfiguredCache = null
}

export async function demoOpenRouterConfigured(): Promise<boolean> {
  if (chatConfiguredCache === true) {
    return true
  }

  try {
    const response = await fetch('/demo/chat/status', {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    })
    if (!response.ok) {
      chatConfiguredCache = false
      return false
    }
    const body = (await response.json()) as { configured?: unknown }
    chatConfiguredCache = body.configured === true
    return chatConfiguredCache
  } catch {
    chatConfiguredCache = false
    return false
  }
}

export interface DemoChatChapter {
  readonly title?: string
  readonly modelLine?: string
  readonly patterns?: readonly string[]
  readonly scenario?: string | null
}

export async function requestDemoChatReply(
  message: string,
  options: {
    readonly locale?: string
    readonly chapter?: DemoChatChapter
  } = {},
): Promise<DemoChatResult | null> {
  const trimmed = message.trim()
  if (trimmed === '') {
    return null
  }

  const configured = await demoOpenRouterConfigured()
  if (!configured) {
    return null
  }

  try {
    const response = await fetch('/demo/chat', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        message: trimmed,
        locale: options.locale ?? 'en-IN',
        chapter: options.chapter,
      }),
    })
    if (!response.ok) {
      return null
    }
    const body = (await response.json()) as {
      reply?: unknown
      model?: unknown
    }
    if (typeof body.reply !== 'string' || body.reply.trim() === '') {
      return null
    }
    return {
      reply: body.reply.trim(),
      source: 'openrouter',
      model: typeof body.model === 'string' ? body.model : undefined,
    }
  } catch {
    return null
  }
}
