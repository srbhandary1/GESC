import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { AvatarSlot } from './AvatarSlot'

afterEach(() => {
  cleanup()
})

describe('AvatarSlot', () => {
  it('renders the Mira portrait in an accessible teacher slot', () => {
    const { container } = render(
      <AvatarSlot label="AI Teacher" slot="home-teacher" />,
    )

    const slot = screen.getByRole('img', { name: 'AI Teacher' })
    expect(slot).toHaveAttribute('data-avatar-slot', 'home-teacher')
    expect(slot).toHaveAttribute('data-avatar-size', 'md')
    const image = slot.querySelector('img')
    expect(image).not.toBeNull()
    expect(image).toHaveAttribute('width', '148')
    expect(image).toHaveAttribute('height', '148')
    expect(image?.getAttribute('src') ?? '').toMatch(/mira\.webp/)
    expect(slot.querySelector('video, canvas')).toBeNull()
    expect(container.querySelector('[data-avatar-slot]')).toBe(slot)
  })
})
