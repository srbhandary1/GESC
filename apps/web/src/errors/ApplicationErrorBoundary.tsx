import {
  Component,
  useEffect,
  useRef,
  type ReactNode,
} from 'react'
import './ApplicationErrorBoundary.css'

export type ApplicationErrorEvent = Readonly<{
  category: 'react_render_failure'
}>

type ApplicationErrorBoundaryProps = {
  children: ReactNode
  onCaught?: (event: ApplicationErrorEvent) => void
  onReload?: () => void
}

type ApplicationErrorBoundaryState = {
  hasError: boolean
}

type ApplicationErrorFallbackProps = {
  onReload: () => void
}

const defaultReload = () => {
  window.location.reload()
}

function ApplicationErrorFallback({
  onReload,
}: ApplicationErrorFallbackProps) {
  const titleRef = useRef<HTMLHeadingElement>(null)

  useEffect(() => {
    titleRef.current?.focus()
  }, [])

  return (
    <main
      className="application-error"
      aria-labelledby="application-error-title"
    >
      <h1
        className="application-error__title"
        id="application-error-title"
        ref={titleRef}
        tabIndex={-1}
      >
        Global English could not load
      </h1>
      <p className="application-error__message" role="alert">
        Something unexpected happened. Reload the application to try again.
      </p>
      <button
        className="application-error__reload"
        type="button"
        onClick={onReload}
      >
        Reload application
      </button>
    </main>
  )
}

export class ApplicationErrorBoundary extends Component<
  ApplicationErrorBoundaryProps,
  ApplicationErrorBoundaryState
> {
  state: ApplicationErrorBoundaryState = {
    hasError: false,
  }

  static getDerivedStateFromError(): ApplicationErrorBoundaryState {
    return { hasError: true }
  }

  componentDidCatch(): void {
    this.props.onCaught?.({ category: 'react_render_failure' })
  }

  render(): ReactNode {
    if (this.state.hasError) {
      return (
        <ApplicationErrorFallback
          onReload={this.props.onReload ?? defaultReload}
        />
      )
    }

    return this.props.children
  }
}
