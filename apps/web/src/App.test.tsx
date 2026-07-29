import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import App from './App'
import type {
  AuthenticationAdapter,
  AuthenticationSnapshot,
} from './auth/AuthenticationAdapter'

describe('application shell', () => {
  it('renders the anonymous secure identity foundation', async () => {
    const authentication: AuthenticationAdapter = {
      transport: 'sanctum-cookie',
      current: vi.fn(
        async (): Promise<AuthenticationSnapshot> => ({
          status: 'anonymous',
        }),
      ),
      logout: vi.fn(async () => undefined),
    }

    render(<App authentication={authentication} />)

    expect(
      screen.getByRole('heading', { name: 'Global English' }),
    ).toBeInTheDocument()
    expect(
      await screen.findByText(
        'PR-003 tenancy and secure sign-in foundation is ready.',
      ),
    ).toHaveAttribute(
      'role',
      'status',
    )
    expect(authentication.current).toHaveBeenCalledOnce()
  })
})
