import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import App from './App'

describe('application shell', () => {
  it('renders the Global English bootstrap state', () => {
    render(<App />)

    expect(
      screen.getByRole('heading', { name: 'Global English' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent(
      'PR-002 contracts and health foundation is ready.',
    )
  })
})
