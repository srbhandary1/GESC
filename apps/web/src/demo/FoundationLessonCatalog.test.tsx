import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { FoundationLessonCatalog } from './FoundationLessonCatalog'
import { FOUNDATION_LESSONS } from './foundationLessons'

vi.mock('./demoTts', () => ({
  speakDemoText: vi.fn().mockResolvedValue('browser'),
  stopDemoSpeech: vi.fn(),
}))

afterEach(() => {
  cleanup()
})

describe('FoundationLessonCatalog', () => {
  it('lists 16 foundation lessons with unique Tap to Play speech', () => {
    render(
      <FoundationLessonCatalog
        locale="en-IN"
        localizationPolicy="development-preview"
      />,
    )

    expect(
      screen.getByRole('heading', { name: 'Course levels' }),
    ).toBeVisible()
    expect(screen.getByRole('heading', { name: 'Foundations' })).toBeVisible()
    expect(screen.getByRole('heading', { name: 'Daily life' })).toBeVisible()
    expect(
      screen.getByRole('heading', { name: 'Work and opportunity' }),
    ).toBeVisible()
    expect(FOUNDATION_LESSONS).toHaveLength(16)
    expect(
      screen.getByRole('button', { name: /Open Greetings and introductions/ }),
    ).toBeVisible()
    expect(
      screen.getByRole('button', {
        name: /Office introductions and daily stand-up/,
      }),
    ).toBeVisible()
    expect(
      screen.getByRole('button', { name: 'Tap to Play lesson 1' }),
    ).toBeVisible()
    expect(
      screen.getByRole('button', { name: 'Tap to Play lesson 16' }),
    ).toBeVisible()
  })

  it('opens a lesson, plays the English model, and can return to the list', async () => {
    const user = userEvent.setup()
    const { speakDemoText } = await import('./demoTts')

    const onPractise = vi.fn()
    render(
      <FoundationLessonCatalog
        locale="en-IN"
        localizationPolicy="development-preview"
        onPractise={onPractise}
      />,
    )

    await user.click(
      screen.getByRole('button', { name: /Open Greetings and introductions/ }),
    )
    expect(
      screen.getByRole('heading', { name: 'Greetings and introductions' }),
    ).toBeVisible()
    expect(
      screen.getByText('Hello, my name is Ravi. I am pleased to meet you.'),
    ).toBeVisible()
    expect(
      screen.getByRole('button', { name: 'Practise on Speak' }),
    ).toBeVisible()

    await user.click(screen.getByRole('button', { name: 'Tap to Play lesson 1' }))
    expect(speakDemoText).toHaveBeenCalledWith(
      'Hello, my name is Ravi. I am pleased to meet you.',
      'en-IN',
    )
    await user.click(screen.getByRole('button', { name: 'Practise on Speak' }))
    expect(onPractise).toHaveBeenCalledWith(FOUNDATION_LESSONS[0])

    await user.click(screen.getByRole('button', { name: 'All lessons' }))
    expect(
      screen.getByRole('heading', { name: 'Course levels' }),
    ).toBeVisible()
  })

  it('shows all 16 lessons in a home grid that opens a selected lesson', async () => {
    const user = userEvent.setup()
    const onOpen = vi.fn()

    render(
      <FoundationLessonCatalog
        locale="en-IN"
        localizationPolicy="development-preview"
        onOpen={onOpen}
        variant="home"
      />,
    )

    expect(screen.queryByRole('heading', { name: 'Foundations' })).toBeNull()
    expect(
      screen.getByRole('button', { name: /Open Greetings and introductions/ }),
    ).toBeVisible()
    expect(
      screen.getByRole('button', { name: 'Tap to Play lesson 16' }),
    ).toBeVisible()

    await user.click(
      screen.getByRole('button', { name: /Open Greetings and introductions/ }),
    )
    expect(onOpen).toHaveBeenCalledWith(FOUNDATION_LESSONS[0])
  })
})
