import { useCallback, useEffect, useState } from 'react'
import {
  defaultLearnerSubscriptionApi,
  type LearnerEntitlement,
  type LearnerSubscriptionApi,
} from './LearnerSubscriptionApi'

export type LearnerEntitlementStatus =
  | 'loading'
  | 'ready'
  | 'unavailable'
  | 'absent'
  | 'exhausted'

interface EntitlementResource {
  readonly api: LearnerSubscriptionApi
  readonly entitlement: LearnerEntitlement | null
  readonly scope: string | null
  readonly status: LearnerEntitlementStatus
}

function entitlementStatus(
  entitlement: LearnerEntitlement,
): Exclude<LearnerEntitlementStatus, 'loading' | 'unavailable'> {
  if (!entitlement.applies) {
    return 'absent'
  }

  if (
    entitlement.live_ai_seconds_remaining !== null &&
    entitlement.live_ai_seconds_remaining <= 0
  ) {
    return 'exhausted'
  }

  return 'ready'
}

export function useLearnerEntitlement(
  scope: string | null,
  api: LearnerSubscriptionApi = defaultLearnerSubscriptionApi,
): {
  applyEntitlement: (current: LearnerEntitlement) => void
  entitlement: LearnerEntitlement | null
  refreshEntitlement: () => Promise<void>
  status: LearnerEntitlementStatus
} {
  const [resource, setResource] = useState<EntitlementResource>({
    api,
    entitlement: null,
    scope,
    status: scope === null ? 'absent' : 'loading',
  })

  const currentResource: EntitlementResource =
    scope === null
      ? { api, entitlement: null, scope, status: 'absent' }
      : resource.api === api && resource.scope === scope
        ? resource
        : { api, entitlement: null, scope, status: 'loading' }

  const applyEntitlement = useCallback(
    (current: LearnerEntitlement) => {
      if (scope === null) {
        return
      }

      setResource({
        api,
        entitlement: current,
        scope,
        status: entitlementStatus(current),
      })
    },
    [api, scope],
  )

  const markUnavailable = useCallback(() => {
    if (scope === null) {
      return
    }

    setResource((previous) => ({
      api,
      entitlement:
        previous.api === api && previous.scope === scope
          ? previous.entitlement
          : null,
      scope,
      status: 'unavailable',
    }))
  }, [api, scope])

  const refreshEntitlement = useCallback(async () => {
    if (scope === null) {
      return
    }

    try {
      applyEntitlement(await api.entitlement())
    } catch {
      markUnavailable()
    }
  }, [api, applyEntitlement, markUnavailable, scope])

  useEffect(() => {
    if (scope === null) {
      return undefined
    }

    const controller = new AbortController()
    void api
      .entitlement()
      .then((current) => {
        if (!controller.signal.aborted) {
          applyEntitlement(current)
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          markUnavailable()
        }
      })

    return () => {
      controller.abort()
    }
  }, [api, applyEntitlement, markUnavailable, scope])

  useEffect(() => {
    if (scope === null) return
    const changed = () => { void refreshEntitlement() }
    window.addEventListener('global-english:ai-usage-changed', changed)
    window.addEventListener('focus', changed)
    return () => {
      window.removeEventListener('global-english:ai-usage-changed', changed)
      window.removeEventListener('focus', changed)
    }
  }, [refreshEntitlement, scope])

  return {
    applyEntitlement,
    entitlement: currentResource.entitlement,
    refreshEntitlement,
    status: currentResource.status,
  }
}
