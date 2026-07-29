import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { ApplicationErrorBoundary } from './errors/ApplicationErrorBoundary.tsx'
import { createRootErrorOptions } from './errors/rootErrorReporting.ts'

createRoot(
  document.getElementById('root')!,
  createRootErrorOptions(),
).render(
  <ApplicationErrorBoundary>
    <StrictMode>
      <App />
    </StrictMode>
  </ApplicationErrorBoundary>,
)
