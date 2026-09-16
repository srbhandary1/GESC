import {
  defaultAuthenticationRuntime,
  type ApiCsrfProtection,
} from '../auth/defaultAuthentication'
import type { SessionPartId } from '../learner/LevelSessionsApi'

export interface LearnerEntitlement {
  readonly applies: boolean
  readonly entitlement_id: string | null
  readonly plan_code: string | null
  readonly plan_name: string | null
  readonly status: string
  readonly allows_roleplay: boolean
  readonly allows_free_speech: boolean
  readonly allowed_session_parts: readonly SessionPartId[]
  readonly max_level_number: number | null
  readonly is_demo: boolean
  readonly expires_at: string | null
  readonly live_ai_seconds_remaining: number | null
  readonly live_ai_cap_seconds: number | null
  readonly gstin_is_placeholder: boolean
}

export interface SubscriptionPlanOffer {
  readonly id: string
  readonly code: string
  readonly name: string
  readonly amount_ex_gst_paise: number
  readonly gst_paise: number
  readonly total_paise: number
  readonly billing_period: string
  readonly allows_roleplay: boolean
  readonly max_level_number: number | null
  readonly live_ai_cap_seconds: number | null
  readonly is_demo: boolean
  readonly is_talk_pack: boolean
  readonly checkout_required: boolean
  readonly recommended: boolean
}

export interface LearnerSubscriptionApi {
  entitlement(): Promise<LearnerEntitlement>
  plans(): Promise<{
    gstin_is_placeholder: boolean
    plans: readonly SubscriptionPlanOffer[]
  }>
  checkout(planId: string, idempotencyKey: string): Promise<{ status: string }>
}

export interface LearnerSubscriptionApiOptions {
  readonly baseUrl?: string
  readonly csrfProtection?: ApiCsrfProtection
  readonly fetch?: typeof fetch
  readonly readCookie?: () => string
}

export class LearnerSubscriptionHttpError extends Error {
  readonly definitiveFailure: boolean
  readonly status: number

  constructor(status: number) {
    super('subscription_unavailable')
    this.name = 'LearnerSubscriptionHttpError'
    this.status = status
    this.definitiveFailure = status >= 400 && status < 500 && status !== 408
  }
}

function joinUrl(baseUrl: string, path: string): string {
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path
  }

  const normalizedBase = baseUrl.endsWith('/')
    ? baseUrl.slice(0, -1)
    : baseUrl
  return `${normalizedBase}${path.startsWith('/') ? path : `/${path}`}`
}

function csrfTokenFromCookie(readCookie: () => string): string {
  const match = /XSRF-TOKEN=([^;]+)/.exec(readCookie())
  return match?.[1] ? decodeURIComponent(match[1]) : ''
}

async function parseJson(response: Response): Promise<unknown> {
  if (!response.ok) {
    throw new LearnerSubscriptionHttpError(response.status)
  }

  return response.json()
}

export function createLearnerSubscriptionApi(
  options: LearnerSubscriptionApiOptions = {},
): LearnerSubscriptionApi {
  const baseUrl = options.baseUrl ?? defaultAuthenticationRuntime.apiBaseUrl
  const csrfProtection =
    options.csrfProtection ?? defaultAuthenticationRuntime.csrfProtection
  const fetch = options.fetch ?? defaultAuthenticationRuntime.apiFetch
  const readCookie =
    options.readCookie ??
    (() => (typeof document === 'undefined' ? '' : document.cookie))

  async function jsonRequest<T>(
    path: string,
    init: RequestInit = {},
  ): Promise<T> {
    const headers = new Headers(init.headers)
    headers.set('Accept', 'application/json')

    if (init.body !== undefined && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json')
    }

    if (
      csrfProtection !== 'none' &&
      (init.method === 'POST' ||
        init.method === 'PUT' ||
        init.method === 'PATCH' ||
        init.method === 'DELETE')
    ) {
      const token = csrfTokenFromCookie(readCookie)
      if (token !== '') {
        headers.set('X-XSRF-TOKEN', token)
      }
    }

    const response = await fetch(joinUrl(baseUrl, path), {
      ...init,
      credentials: 'include',
      headers,
    })

    return (await parseJson(response)) as T
  }

  return {
    async entitlement() {
      return jsonRequest<LearnerEntitlement>('/me/entitlement')
    },
    async plans() {
      return jsonRequest<{
        gstin_is_placeholder: boolean
        plans: readonly SubscriptionPlanOffer[]
      }>('/me/subscription-plans')
    },
    async checkout(planId, idempotencyKey) {
      return jsonRequest<{ status: string }>('/me/subscription-checkouts', {
        method: 'POST',
        body: JSON.stringify({ plan_id: planId }),
        headers: {
          'Idempotency-Key': idempotencyKey,
        },
      })
    },
  }
}

export const defaultLearnerSubscriptionApi = createLearnerSubscriptionApi()
