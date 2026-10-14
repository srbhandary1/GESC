import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, expect, it, vi } from 'vitest'
import { SpeakRoom } from './SpeakRoom'

vi.mock('./ConversationJourney', () => ({ ConversationJourney: () => <p>Everyday conversation</p> }))
vi.mock('./RoleplayJourney', () => ({ RoleplayJourney: () => <p>Roleplay conversation</p> }))
vi.mock('./QuestionHelpJourney', () => ({ QuestionHelpJourney: () => <p>Question conversation</p> }))
afterEach(cleanup)
const api = { startSession: vi.fn(), createTurn: vi.fn(), events: vi.fn() }

it('keeps question help available when Role-play alone is locked', async () => {
  render(<SpeakRoom api={api} allowsRoleplay={false} locale="en-IN" localizationPolicy="development-preview" />)
  expect(screen.getByRole('tab', { name: 'A situation' })).toBeDisabled()
  const user = userEvent.setup()
  screen.getByRole('tab', { name: 'Everyday talk' }).focus()
  await user.keyboard('{ArrowRight}')
  expect(screen.getByRole('tab', { name: 'Ask Swathi' })).toHaveFocus()
  expect(screen.getByText('Question conversation')).toBeVisible()
  await user.keyboard('{Home}')
  expect(screen.getByRole('tab', { name: 'Everyday talk' })).toHaveFocus()
  await user.keyboard('{End}')
  expect(screen.getByRole('tab', { name: 'Ask Swathi' })).toHaveFocus()
  await user.keyboard('{ArrowLeft}')
  expect(screen.getByRole('tab', { name: 'Everyday talk' })).toHaveFocus()
})

it('preserves a requested help entry independently of Role-play access', () => {
  render(<SpeakRoom api={api} allowsRoleplay={false} initialMode="question_help" locale="kn-IN" localizationPolicy="development-preview" />)
  expect(screen.getByText('Question conversation')).toBeVisible()
  expect(screen.getByRole('tab', { name: 'ಸ್ವಾತಿಯನ್ನು ಕೇಳಿ' })).toHaveAttribute('aria-selected', 'true')
})
