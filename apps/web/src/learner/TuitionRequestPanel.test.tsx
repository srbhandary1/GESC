import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { TuitionRequestPanel } from './TuitionRequestPanel'
import type { TuitionRequestsApi } from './TuitionRequestsApi'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('tuition request panel', () => {
  it('submits an org-scoped waitlist request and lists it', async () => {
    const create = vi.fn(async () => ({
      created_at: '2026-08-14T12:00:00+00:00',
      id: '01KYS97G7MX374P303XJHDXT99',
      notes: null,
      preferred_window: 'Weekday evenings',
      status: 'submitted' as const,
      topic: 'Level 4 speaking',
    }))
    const api: TuitionRequestsApi = {
      create,
      list: vi.fn(async () => ({
        requests: [],
        institute_contact: {
          email: 'direct@global-english.test',
          phone: '+919876543210',
          whatsapp: '919876543210',
        },
      })),
    }
    const user = userEvent.setup()

    render(
      <TuitionRequestPanel
        api={api}
        createClientId={() => '11111111-2222-4333-8444-555555555555'}
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      await screen.findByRole('heading', { name: 'Request 1:1 tuition' }),
    ).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Call the institute' })).toHaveAttribute(
      'href',
      'tel:+919876543210',
    )
    expect(screen.getByRole('link', { name: 'Message on WhatsApp' })).toHaveAttribute(
      'href',
      'https://wa.me/919876543210',
    )
    expect(screen.getByRole('link', { name: 'Email the institute' })).toHaveAttribute(
      'href',
      'mailto:direct@global-english.test',
    )
    await user.click(screen.getByText('Request 1:1 help'))
    await user.type(
      screen.getByLabelText('Preferred time window'),
      'Weekday evenings',
    )
    await user.type(screen.getByLabelText('What you want to practise'), 'Level 4 speaking')
    await user.click(screen.getByRole('button', { name: 'Send request' }))
    expect(create).toHaveBeenCalledWith(
      {
        preferred_window: 'Weekday evenings',
        topic: 'Level 4 speaking',
      },
      '11111111-2222-4333-8444-555555555555',
    )
    expect(await screen.findByText(/Level 4 speaking/)).toBeInTheDocument()
    expect(screen.getByText('Submitted')).toBeInTheDocument()
  })
})
