import type { ReactNode } from 'react'

export interface SkeletonStatusProps {
  readonly label: ReactNode
}

export function SkeletonStatus({ label }: SkeletonStatusProps) {
  return (
    <div className="ge-skeleton-status" role="status">
      <span className="ge-visually-hidden">{label}</span>
      <div className="ge-skeleton ge-skeleton--title" />
      <div className="ge-skeleton ge-skeleton--line" />
      <div className="ge-skeleton ge-skeleton--line ge-skeleton--short" />
      <div className="ge-skeleton ge-skeleton--cta" />
    </div>
  )
}

export interface AsyncErrorProps {
  readonly actionLabel: ReactNode
  readonly children: ReactNode
  readonly onRetry: () => void
  readonly title: ReactNode
}

export function AsyncError({
  actionLabel,
  children,
  onRetry,
  title,
}: AsyncErrorProps) {
  return (
    <section className="ge-async-error" aria-labelledby="ge-async-error-title">
      <h2 className="ge-async-error__title" id="ge-async-error-title">
        {title}
      </h2>
      <p className="ge-async-error__message" role="alert">
        {children}
      </p>
      <button className="ge-button ge-button--primary" onClick={onRetry} type="button">
        {actionLabel}
      </button>
    </section>
  )
}

export interface EmptyStateProps {
  readonly action?: ReactNode
  readonly children: ReactNode
  readonly title: ReactNode
}

export function EmptyState({ action, children, title }: EmptyStateProps) {
  return (
    <section className="ge-empty-state">
      <h2 className="ge-empty-state__title">{title}</h2>
      <p className="ge-empty-state__message">{children}</p>
      {action === undefined ? null : (
        <div className="ge-empty-state__action">{action}</div>
      )}
    </section>
  )
}

export interface SuccessStateProps {
  readonly action?: ReactNode
  readonly children: ReactNode
  readonly title: ReactNode
}

export function SuccessState({ action, children, title }: SuccessStateProps) {
  return (
    <section className="ge-success-state" data-state="success">
      <h2 className="ge-success-state__title">{title}</h2>
      <p className="ge-success-state__message">{children}</p>
      {action === undefined ? null : (
        <div className="ge-success-state__action">{action}</div>
      )}
    </section>
  )
}
