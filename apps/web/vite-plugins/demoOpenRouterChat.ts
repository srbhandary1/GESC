import { readFileSync } from 'node:fs'
import type { ServerResponse } from 'node:http'
import type { Connect, Plugin } from 'vite'

/**
 * Demo-only OpenRouter free-model chat for local presentation (`make demo`).
 * API keys stay in process env / secret files — never exposed to the browser.
 * Does not amend ordinary Laravel ConversationModelProvider bindings.
 */

const DEMO_CHAT_PATH = '/demo/chat'
const DEMO_CHAT_STATUS_PATH = '/demo/chat/status'
const OPENROUTER_URL = 'https://openrouter.ai/api/v1/chat/completions'
const MAXIMUM_MESSAGE_CHARS = 500
const MAXIMUM_REPLY_CHARS = 600
const DEFAULT_MODEL = 'openrouter/free'

const FRIEND_SYSTEM_PROMPT = `You are "Patient Friend", a spoken-English practice partner for adult learners in India.
Rules:
- Reply in clear, simple English suitable for speaking practice.
- Keep replies short (2-4 sentences). Ask one gentle follow-up question.
- At most one light correction tip, framed kindly; never shame the learner.
- You are a demo assistant, not a human. Do not claim to be human.
- Do not invent live facts (exact time, live weather, news). If asked, say you do not have live data and continue the English practice.
- Do not discuss politics, medical advice, or legal advice.
- Do not output markdown tables, code fences, or JSON — plain conversational text only.`

function readBody(req: Connect.IncomingMessage): Promise<string> {
  return new Promise((resolve, reject) => {
    const chunks: Buffer[] = []
    req.on('data', (chunk: Buffer) => {
      chunks.push(chunk)
      const total = chunks.reduce((sum, part) => sum + part.length, 0)
      if (total > 16_384) {
        reject(new Error('request_too_large'))
        req.destroy()
      }
    })
    req.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')))
    req.on('error', reject)
  })
}

function resolveApiKey(): string | null {
  const inline = (process.env.OPENROUTER_API_KEY ?? '').trim()
  if (inline !== '') {
    return inline
  }

  const secretFile = (process.env.OPENROUTER_API_KEY_FILE ?? '').trim()
  if (secretFile === '') {
    return null
  }

  try {
    const key = readFileSync(secretFile, 'utf8').trim()
    return key === '' ? null : key
  } catch {
    return null
  }
}

function resolveModel(): string {
  const configured = (process.env.OPENROUTER_MODEL ?? '').trim()
  return configured === '' ? DEFAULT_MODEL : configured
}

function sendJson(
  res: ServerResponse,
  status: number,
  body: Record<string, unknown>,
): void {
  res.statusCode = status
  res.setHeader('Content-Type', 'application/json; charset=utf-8')
  res.setHeader('Cache-Control', 'no-store')
  res.end(JSON.stringify(body))
}

function truncateReply(text: string): string {
  const trimmed = text.trim()
  if ([...trimmed].length <= MAXIMUM_REPLY_CHARS) {
    return trimmed
  }
  return `${[...trimmed].slice(0, MAXIMUM_REPLY_CHARS - 1).join('')}…`
}

function chapterSystemExtra(chapter: {
  title?: unknown
  modelLine?: unknown
  patterns?: unknown
  scenario?: unknown
}): string {
  const title = typeof chapter.title === 'string' ? chapter.title.trim() : ''
  const modelLine =
    typeof chapter.modelLine === 'string' ? chapter.modelLine.trim() : ''
  const scenario =
    typeof chapter.scenario === 'string' ? chapter.scenario.trim() : ''
  const patterns = Array.isArray(chapter.patterns)
    ? chapter.patterns
        .filter((item): item is string => typeof item === 'string')
        .slice(0, 6)
        .join('; ')
    : ''
  if (title === '' && modelLine === '') {
    return ''
  }

  return `

This turn is the "${title || 'current'}" listen-learn chapter.
Stay on this grammar/topic. Model line: ${modelLine || 'use the chapter pattern'}.
${scenario === '' ? '' : `Scenario: ${scenario}`}
${patterns === '' ? '' : `Target patterns: ${patterns}`}
Reply about this chapter. Ask one follow-up that uses the same pattern.`
}

async function callOpenRouter(
  apiKey: string,
  model: string,
  message: string,
  chapterExtra = '',
): Promise<string> {
  const response = await fetch(OPENROUTER_URL, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json',
      'HTTP-Referer': 'http://127.0.0.1:5173/',
      'X-Title': 'Global English Speaking Course Demo',
    },
    body: JSON.stringify({
      model,
      temperature: 0.6,
      max_tokens: 220,
      messages: [
        { role: 'system', content: `${FRIEND_SYSTEM_PROMPT}${chapterExtra}` },
        { role: 'user', content: message },
      ],
    }),
  })

  if (!response.ok) {
    throw new Error(`openrouter_http_${response.status}`)
  }

  const payload = (await response.json()) as {
    choices?: Array<{ message?: { content?: unknown } }>
  }
  const content = payload.choices?.[0]?.message?.content
  if (typeof content !== 'string' || content.trim() === '') {
    throw new Error('openrouter_empty_reply')
  }

  return truncateReply(content)
}

function attachDemoChatMiddleware(middlewares: Connect.Server): void {
  middlewares.use(async (req, res, next) => {
    const url = req.url?.split('?')[0] ?? ''

    if (url === DEMO_CHAT_STATUS_PATH && req.method === 'GET') {
      const key = resolveApiKey()
      sendJson(res, 200, {
        configured: key !== null,
        model: resolveModel(),
        provider: 'openrouter',
        tier: 'free-demo',
      })
      return
    }

    if (url !== DEMO_CHAT_PATH || req.method !== 'POST') {
      next()
      return
    }

    const apiKey = resolveApiKey()
    if (apiKey === null) {
      sendJson(res, 503, {
        error: 'openrouter_unconfigured',
        message:
          'Set OPENROUTER_API_KEY or OPENROUTER_API_KEY_FILE for demo chat. See docs/DEMO_OPENROUTER.md.',
      })
      return
    }

    let payload: { message?: unknown; locale?: unknown; chapter?: unknown }
    try {
      payload = JSON.parse(await readBody(req)) as {
        message?: unknown
        locale?: unknown
        chapter?: unknown
      }
    } catch {
      sendJson(res, 400, { error: 'invalid_json' })
      return
    }

    const message =
      typeof payload.message === 'string' ? payload.message.trim() : ''
    if (
      message === '' ||
      [...message].length > MAXIMUM_MESSAGE_CHARS ||
      /[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/u.test(message)
    ) {
      sendJson(res, 400, { error: 'invalid_message' })
      return
    }

    const model = resolveModel()
    const chapterExtra =
      payload.chapter !== null &&
      payload.chapter !== undefined &&
      typeof payload.chapter === 'object'
        ? chapterSystemExtra(payload.chapter as Record<string, unknown>)
        : ''

    try {
      const reply = await callOpenRouter(apiKey, model, message, chapterExtra)
      // eslint-disable-next-line no-console -- intentional demo telemetry
      console.info(
        `[demo-chat] provider=openrouter model=${model} chars_in=${[...message].length} chars_out=${[...reply].length}`,
      )
      sendJson(res, 200, {
        reply,
        model,
        provider: 'openrouter',
      })
    } catch (error) {
      const detail = error instanceof Error ? error.message : 'openrouter_failed'
      // eslint-disable-next-line no-console -- intentional demo diagnostics
      console.warn(`[demo-chat] failed: ${detail}`)
      sendJson(res, 502, { error: 'openrouter_failed', detail })
    }
  })
}

export function demoOpenRouterChatPlugin(): Plugin {
  return {
    name: 'demo-openrouter-chat',
    configureServer(server) {
      attachDemoChatMiddleware(server.middlewares)
    },
    configurePreviewServer(server) {
      attachDemoChatMiddleware(server.middlewares)
    },
  }
}
