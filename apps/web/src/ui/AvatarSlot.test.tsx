import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { AvatarSlot } from './AvatarSlot'

afterEach(() => {
  cleanup()
})

describe('AvatarSlot', () => {
  it('reserves an empty accessible teacher hook without a face', () => {
    const { container } = render(
      <AvatarSlot label="AI Teacher" slot="home-teacher" />,
    )

    const slot = screen.getByRole('img', { name: 'AI Teacher' })
    expect(slot).toHaveAttribute('data-avatar-slot', 'home-teacher')
    expect(slot.querySelector('img, svg, video, canvas')).toBeNull()
    expect(container.querySelector('[data-avatar-slot]')).toBe(slot)
  })
})
