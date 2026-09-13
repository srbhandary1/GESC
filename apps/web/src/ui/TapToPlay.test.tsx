import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { TapToPlay } from './TapToPlay'

afterEach(() => {
  cleanup()
})

describe('TapToPlay', () => {
  it('plays through the provided handler', async () => {
    const user = userEvent.setup()
    const onPlay = vi.fn().mockResolvedValue(undefined)

    render(
      <TapToPlay
        idleLabel="Listen"
        onPlay={onPlay}
        playingLabel="Playing"
        unavailableLabel="Voice playback is not available yet."
      />,
    )

    await user.click(screen.getByRole('button', { name: 'Listen' }))
    expect(onPlay).toHaveBeenCalledOnce()
  })

  it('announces a stub when playback is not wired', async () => {
    const user = userEvent.setup()

    render(
      <TapToPlay
        idleLabel="Listen"
        playingLabel="Playing"
        unavailableLabel="Voice playback is not available yet."
      />,
    )

    await user.click(screen.getByRole('button', { name: 'Listen' }))
    expect(
      screen.getByText('Voice playback is not available yet.'),
    ).toBeInTheDocument()
  })
})
