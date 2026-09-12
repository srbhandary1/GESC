import type { ColorScheme } from './colorScheme'

export interface ThemeToggleProps {
  readonly darkLabel: string
  readonly label: string
  readonly lightLabel: string
  readonly onToggle: () => void
  readonly scheme: ColorScheme
}

export function ThemeToggle({
  darkLabel,
  label,
  lightLabel,
  onToggle,
  scheme,
}: ThemeToggleProps) {
  const nextLabel = scheme === 'dark' ? lightLabel : darkLabel

  return (
    <button
      aria-label={`${label}: ${nextLabel}`}
      aria-pressed={scheme === 'dark'}
      className="ge-theme-toggle"
      onClick={onToggle}
      type="button"
    >
      <span aria-hidden="true" className="ge-theme-toggle__mark">
        {scheme === 'dark' ? '☀' : '☾'}
      </span>
    </button>
  )
}
