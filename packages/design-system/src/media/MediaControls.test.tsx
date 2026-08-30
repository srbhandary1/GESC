import { render, screen } from '@testing-library/react'
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

  it('starts on press and stops on release in hold-to-talk mode', async () => {
    const onRequestStart = vi.fn()
    const onRequestStop = vi.fn()
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
        onRequestStop={onRequestStop}
        state="ready"
      />,
    )

    const button = screen.getByRole('button', { name: 'Hold to talk' })
    await user.pointer({ keys: '[MouseLeft>]', target: button })
    expect(onRequestStart).toHaveBeenCalledOnce()
    await user.pointer({ keys: '[/MouseLeft]', target: button })
    expect(onRequestStop).toHaveBeenCalledOnce()
    expect(screen.queryByRole('button', { name: 'Pause recording' })).not.toBeInTheDocument()
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
})
