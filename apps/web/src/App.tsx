import { useEffect, useState } from 'react'
import './App.css'
import type {
  AuthenticationAdapter,
  AuthenticationSnapshot,
} from './auth/AuthenticationAdapter'
import { defaultAuthentication } from './auth/defaultAuthentication'

type SessionState =
  | AuthenticationSnapshot
  | { status: 'checking' }
  | { status: 'unavailable' }

interface AppProps {
  authentication?: AuthenticationAdapter
}

function App({ authentication = defaultAuthentication }: AppProps) {
  const [session, setSession] = useState<SessionState>({
    status: 'checking',
  })

  useEffect(() => {
    let active = true

    authentication
      .current()
      .then((snapshot) => {
        if (active) {
          setSession(snapshot)
        }
      })
      .catch(() => {
        if (active) {
          setSession({ status: 'unavailable' })
        }
      })

    return () => {
      active = false
    }
  }, [authentication])

  return (
    <main className="app-shell">
      <p className="checkpoint">Secure identity foundation</p>
      <h1>Global English</h1>
      <p className="summary">
        Kannada-guided spoken-English platform foundation.
      </p>
      <p className="status" role="status">
        {sessionMessage(session)}
      </p>
    </main>
  )
}

function sessionMessage(session: SessionState): string {
  if (session.status === 'checking') {
    return 'Checking the secure session.'
  }

  if (session.status === 'anonymous') {
    return 'PR-003 tenancy and secure sign-in foundation is ready.'
  }

  if (session.status === 'unavailable') {
    return 'Secure sign-in is temporarily unavailable.'
  }

  const organization = session.identity.current_membership?.organization.name

  return organization === undefined
    ? 'Signed in. Select an organization membership to continue.'
    : `Signed in to ${organization}.`
}

export default App
