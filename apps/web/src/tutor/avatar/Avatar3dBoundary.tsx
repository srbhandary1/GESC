import { Component, type ReactNode } from 'react'

interface Avatar3dBoundaryProps {
  readonly children: ReactNode
  readonly fallback: ReactNode
}

interface Avatar3dBoundaryState {
  readonly failed: boolean
}

export class Avatar3dBoundary extends Component<
  Avatar3dBoundaryProps,
  Avatar3dBoundaryState
> {
  public state: Avatar3dBoundaryState = { failed: false }

  public static getDerivedStateFromError(): Avatar3dBoundaryState {
    return { failed: true }
  }

  public componentDidCatch(): void {
    this.setState({ failed: true })
  }

  public render(): ReactNode {
    if (this.state.failed) {
      return this.props.fallback
    }

    return this.props.children
  }
}
