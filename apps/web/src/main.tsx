import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import '@global-english/design-system/styles.css'
import './index.css'
import App from './App.tsx'
import { ApplicationErrorBoundary } from './errors/ApplicationErrorBoundary.tsx'
import { createRootErrorOptions } from './errors/rootErrorReporting.ts'
import {
  createLocalDemoRuntime,
  isLocalDemoEnabled,
} from './demo/localDemo.ts'
import { registerOfflineShell } from './offline/registerOfflineShell.ts'

const demoMode = isLocalDemoEnabled({
  development: import.meta.env.DEV,
  distribution: import.meta.env.MODE === 'demo',
  requested: import.meta.env.VITE_LOCAL_DEMO === 'true',
})
const demoRuntime = demoMode ? createLocalDemoRuntime() : null

if (!demoMode) {
  // The synthetic demo must stay storage-free: it registers no service
  // worker and caches nothing, so closing it leaves no learner trace.
  void registerOfflineShell()
}

createRoot(
  document.getElementById('root')!,
  createRootErrorOptions(),
).render(
  <ApplicationErrorBoundary>
    <StrictMode>
      <App
        authentication={demoRuntime?.authentication}
        conversationApi={demoRuntime?.conversationApi}
        demoMode={demoMode}
        learnerJourneyApi={demoRuntime?.learnerJourneyApi}
        levelSessionsApi={demoRuntime?.levelSessionsApi}
      />
    </StrictMode>
  </ApplicationErrorBoundary>,
)
