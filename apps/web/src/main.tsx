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

void registerOfflineShell()

const demoMode = isLocalDemoEnabled({
  development: import.meta.env.DEV,
  requested: import.meta.env.VITE_LOCAL_DEMO === 'true',
})
const demoRuntime = demoMode ? createLocalDemoRuntime() : null

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
      />
    </StrictMode>
  </ApplicationErrorBoundary>,
)
