import type { RootOptions } from 'react-dom/client'

export type RootErrorEvent = Readonly<{
  category:
    | 'react_caught_error'
    | 'react_recoverable_error'
    | 'react_uncaught_error'
}>

export type RootErrorReporter = (event: RootErrorEvent) => void

const defaultReporter: RootErrorReporter = (event) => {
  console.error(event)
}

export function createRootErrorOptions(
  report: RootErrorReporter = defaultReporter,
): RootOptions {
  return {
    onCaughtError: () => {
      report({ category: 'react_caught_error' })
    },
    onRecoverableError: () => {
      report({ category: 'react_recoverable_error' })
    },
    onUncaughtError: () => {
      report({ category: 'react_uncaught_error' })
    },
  }
}
