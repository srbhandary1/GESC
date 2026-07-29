import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { MixedLanguageText } from './MixedLanguageText'

describe('MixedLanguageText', () => {
  it('renders ordered, visible bilingual text with language metadata', () => {
    const { container } = render(
      <MixedLanguageText
        data-testid="mixed-text"
        segments={[
          { locale: 'kn-IN', text: 'ಇಂದಿನ ಗುರಿ: ' },
          { locale: 'en-IN', text: 'Ask for directions.' },
        ]}
      />,
    )

    expect(screen.getByTestId('mixed-text')).toHaveTextContent(
      'ಇಂದಿನ ಗುರಿ: Ask for directions.',
    )
    expect(
      [...container.querySelectorAll('[lang]')].map((element) => [
        element.getAttribute('lang'),
        element.textContent,
      ]),
    ).toEqual([
      ['kn-IN', 'ಇಂದಿನ ಗುರಿ: '],
      ['en-IN', 'Ask for directions.'],
    ])
  })

  it('normalizes text to NFC by default', () => {
    render(
      <MixedLanguageText
        segments={[{ locale: 'en-IN', text: 'Cafe\u0301' }]}
      />,
    )

    expect(screen.getByText('Café')).toHaveTextContent('Café')
  })

  it('supports caller-normalized text and a governed normalization hook', () => {
    const normalizeText = vi.fn((text: string) => text.toUpperCase())
    const { rerender } = render(
      <MixedLanguageText
        normalization="caller"
        segments={[{ locale: 'en-IN', text: 'Cafe\u0301' }]}
      />,
    )

    expect(screen.getByText('Cafe\u0301').textContent).toBe('Cafe\u0301')

    rerender(
      <MixedLanguageText
        normalizeText={normalizeText}
        segments={[{ locale: 'en-IN', text: 'practice' }]}
      />,
    )

    expect(screen.getByText('PRACTICE')).toBeVisible()
    expect(normalizeText).toHaveBeenCalledWith('practice', 'en-IN')
  })
})
