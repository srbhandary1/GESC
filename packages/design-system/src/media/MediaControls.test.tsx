import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { AudioControls } from './AudioControls'
import {
  RecordingControls,
  type RecordingControlLabels,
} from './RecordingControls'

const recordingLabels: RecordingControlLabels = {
  region: 'Recording controls',
  start: 'Start recording',
  pause: 'Pause recording',
  resume: 'Resume recording',
  stop: 'Stop recording',
  cancel: 'Cancel recording',
  idle: 'Not recording',
  ready: 'Ready to record',
  requestingPermission: 'Waiting for microphone permission',
  recording: 'Recording',
  paused: 'Recording paused',
  processing: 'Processing recording',
  blocked: 'Microphone unavailable',
  error: 'Recording failed',
}

describe('media presentation controls', () => {
  it('requests an audio action without implementing playback', async () => {
    const onRequestPause = vi.fn()
    const user = userEvent.setup()

    render(
      <AudioControls
        labels={{
          region: 'Lesson audio',
          play: 'Play',
          pause: 'Pause',
          resume: 'Resume',
          replay: 'Replay',
          loading: 'Loading audio',
          unavailable: 'Audio unavailable',
          error: 'Audio failed',
          readyStatus: 'Audio ready',
          loadingStatus: 'Audio loading',
          playingStatus: 'Audio playing',
          pausedStatus: 'Audio paused',
          endedStatus: 'Audio finished',
          unavailableStatus: 'Audio unavailable',
          errorStatus: 'Audio failed',
        }}
        onRequestPause={onRequestPause}
        onRequestPlay={vi.fn()}
        onRequestReplay={vi.fn()}
        state="playing"
      />,
    )

    const pauseButton = screen.getByRole('button', { name: 'Pause' })
    expect(screen.getByText('Audio playing')).toBeVisible()
    await user.click(pauseButton)
    expect(onRequestPause).toHaveBeenCalledOnce()
  })

  it('exposes recording requests and visible state without capturing media', async () => {
    const onRequestStop = vi.fn()
    const user = userEvent.setup()

    render(
      <RecordingControls
        labels={recordingLabels}
        onRequestCancel={vi.fn()}
        onRequestPause={vi.fn()}
        onRequestResume={vi.fn()}
        onRequestStart={vi.fn()}
        onRequestStop={onRequestStop}
        state="recording"
      />,
    )

    expect(screen.getByText('Recording')).toBeVisible()
    await user.click(screen.getByRole('button', { name: 'Stop recording' }))
    expect(onRequestStop).toHaveBeenCalledOnce()
  })

  it('disables hold-to-talk when interaction is locked', async () => {
    const onRequestStart = vi.fn()
    const user = userEvent.setup()

    render(
      <RecordingControls
        captureMode="hold"
        interactionDisabled
        labels={{
          ...recordingLabels,
          hold: 'Hold to talk',
        }}
        onRequestCancel={vi.fn()}
        onRequestPause={vi.fn()}
        onRequestResume={vi.fn()}
        onRequestStart={onRequestStart}
        onRequestStop={vi.fn()}
        state="ready"
        statusOverride="Teacher is speaking"
      />,
    )

    const button = screen.getByRole('button', { name: 'Hold to talk' })
    expect(button).toBeDisabled()
    expect(screen.getByText('Teacher is speaking')).toBeVisible()
    await user.pointer({ keys: '[MouseLeft>]', target: button })
    expect(onRequestStart).not.toHaveBeenCalled()
  })

  it('starts on a quick tap and stops on the next tap in hold-to-talk mode', async () => {
    const onRequestStart = vi.fn()
    const onRequestStop = vi.fn()
    const user = userEvent.setup()

    const { rerender } = render(
      <RecordingControls
        captureMode="hold"
        labels={{
          ...recordingLabels,
          hold: 'Hold to talk',
          holding: 'Listening…',
          release: 'Release to send',
        }}
        onRequestCancel={vi.fn()}
        onRequestPause={vi.fn()}
        onRequestResume={vi.fn()}
        onRequestStart={onRequestStart}
        onRequestStop={onRequestStop}
        state="ready"
      />,
    )

    const button = screen.getByRole('button', { name: 'Hold to talk' })
    await user.pointer({ keys: '[MouseLeft>]', target: button })
    await user.pointer({ keys: '[/MouseLeft]', target: button })
    expect(onRequestStart).toHaveBeenCalledOnce()
    expect(onRequestStop).not.toHaveBeenCalled()

    rerender(
      <RecordingControls
        captureMode="hold"
        labels={{
          ...recordingLabels,
          hold: 'Hold to talk',
          holding: 'Listening…',
          release: 'Release to send',
        }}
        onRequestCancel={vi.fn()}
        onRequestPause={vi.fn()}
        onRequestResume={vi.fn()}
        onRequestStart={onRequestStart}
        onRequestStop={onRequestStop}
        state="recording"
      />,
    )

    const sendButton = screen.getByRole('button', { name: 'Release to send' })
    await user.pointer({ keys: '[MouseLeft>]', target: sendButton })
    await user.pointer({ keys: '[/MouseLeft]', target: sendButton })
    expect(onRequestStop).toHaveBeenCalledOnce()
    expect(screen.queryByRole('button', { name: 'Pause recording' })).not.toBeInTheDocument()
  })

  it('stops when a sustained hold is released', async () => {
    const onRequestStart = vi.fn()
    const onRequestStop = vi.fn()
    const now = vi.spyOn(performance, 'now')

    render(
      <RecordingControls
        captureMode="hold"
        labels={{ ...recordingLabels, hold: 'Hold to talk' }}
        onRequestCancel={vi.fn()}
        onRequestPause={vi.fn()}
        onRequestResume={vi.fn()}
        onRequestStart={onRequestStart}
        onRequestStop={onRequestStop}
        state="ready"
      />,
    )

    const button = screen.getByRole('button', { name: 'Hold to talk' })
    now.mockReturnValue(100)
    fireEvent.pointerDown(button, { button: 0, pointerId: 1 })
    now.mockReturnValue(800)
    fireEvent.pointerUp(button, { button: 0, pointerId: 1 })

    expect(onRequestStart).toHaveBeenCalledOnce()
    expect(onRequestStop).toHaveBeenCalledOnce()
    now.mockRestore()
  })

  it('exposes cancel during hold processing so the request can be aborted', async () => {
    const onRequestCancel = vi.fn()
    const user = userEvent.setup()

    render(
      <RecordingControls
        captureMode="hold"
        labels={{
          ...recordingLabels,
          hold: 'Hold to talk',
          holding: 'Listening…',
          release: 'Release to send',
        }}
        onRequestCancel={onRequestCancel}
        onRequestPause={vi.fn()}
        onRequestResume={vi.fn()}
        onRequestStart={vi.fn()}
        onRequestStop={vi.fn()}
        state="processing"
      />,
    )

    await user.click(
      screen.getByRole('button', { name: 'Cancel recording' }),
    )
    expect(onRequestCancel).toHaveBeenCalledOnce()
  })

  it('keeps the primary hold control available after a capture error', async () => {
    const onRequestStart = vi.fn()
    const user = userEvent.setup()

    render(
      <RecordingControls
        captureMode="hold"
        labels={{
          ...recordingLabels,
          hold: 'Hold to talk',
          holding: 'Listening…',
          release: 'Release to send',
        }}
        onRequestCancel={vi.fn()}
        onRequestPause={vi.fn()}
        onRequestResume={vi.fn()}
        onRequestStart={onRequestStart}
        onRequestStop={vi.fn()}
        state="error"
      />,
    )

    expect(screen.getByText('Recording failed')).toBeVisible()
    const retry = screen.getByRole('button', { name: 'Hold to talk' })
    await user.pointer({ keys: '[MouseLeft>]', target: retry })
    expect(onRequestStart).toHaveBeenCalledOnce()
  })

  it('announces blocked state without offering a capture control', () => {
    render(
      <RecordingControls
        labels={recordingLabels}
        onRequestCancel={vi.fn()}
        onRequestPause={vi.fn()}
        onRequestResume={vi.fn()}
        onRequestStart={vi.fn()}
        onRequestStop={vi.fn()}
        state="blocked"
      />,
    )

    expect(screen.getByText('Microphone unavailable')).toBeVisible()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('keeps hold-to-talk available after microphone permission is blocked', async () => {
    const onRequestStart = vi.fn()
    const user = userEvent.setup()

    render(
      <RecordingControls
        captureMode="hold"
        labels={{
          ...recordingLabels,
          hold: 'Hold to talk',
          holding: 'Listening…',
          release: 'Release to send',
        }}
        onRequestCancel={vi.fn()}
        onRequestPause={vi.fn()}
        onRequestResume={vi.fn()}
        onRequestStart={onRequestStart}
        onRequestStop={vi.fn()}
        state="blocked"
      />,
    )

    expect(screen.getByText('Microphone unavailable')).toBeVisible()
    await user.pointer({
      keys: '[MouseLeft>]',
      target: screen.getByRole('button', { name: 'Hold to talk' }),
    })
    expect(onRequestStart).toHaveBeenCalledOnce()
  })

  it('offers cancel while microphone permission is being requested', () => {
    render(
      <RecordingControls
        captureMode="hold"
        labels={{
          ...recordingLabels,
          hold: 'Hold to talk',
          holding: 'Listening…',
          release: 'Release to send',
        }}
        onRequestCancel={vi.fn()}
        onRequestPause={vi.fn()}
        onRequestResume={vi.fn()}
        onRequestStart={vi.fn()}
        onRequestStop={vi.fn()}
        state="requesting-permission"
      />,
    )

    expect(
      screen.getByRole('button', { name: 'Cancel recording' }),
    ).toBeVisible()
  })
})
