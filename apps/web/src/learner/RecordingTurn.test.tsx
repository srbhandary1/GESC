import { act, cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type {
  AudioRecorder,
  AudioRecordingSession,
} from './BrowserAudioRecorder'
import {
  RecordingTurn,
  type RecordingTurnLabels,
} from './RecordingTurn'
import { isValidLocalCapture } from './LocalCaptureReceipt'

const labels: RecordingTurnLabels = {
  continueWithout: 'Continue without recording',
  continueWithoutExplanation: 'Speaking remains unassessed.',
  controls: {
    blocked: 'Recording blocked',
    cancel: 'Cancel',
    error: 'Recording error',
    idle: 'Idle',
    pause: 'Pause',
    paused: 'Paused',
    processing: 'Processing',
    ready: 'Ready',
    recording: 'Recording',
    region: 'Speaking recording',
    requestingPermission: 'Requesting permission',
    resume: 'Resume',
    start: 'Start',
    stop: 'Stop',
  },
  permissionExplanation: 'Permission is requested after Start.',
  playbackHelp: 'Review locally.',
  playbackLabel: 'Local playback',
  retry: 'Record again',
}

beforeEach(() => {
  Object.defineProperty(URL, 'createObjectURL', {
    configurable: true,
    value: vi.fn(() => 'blob:local'),
  })
  Object.defineProperty(URL, 'revokeObjectURL', {
    configurable: true,
    value: vi.fn(),
  })
})

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

describe('RecordingTurn privacy boundaries', () => {
  it.each([
    {
      byteSize: 127,
      caseName: 'too few bytes',
      durationMs: 250,
      mimeType: 'audio/webm',
    },
    {
      byteSize: 10_000_001,
      caseName: 'too many bytes',
      durationMs: 250,
      mimeType: 'audio/webm',
    },
    {
      byteSize: 128,
      caseName: 'too short',
      durationMs: 249,
      mimeType: 'audio/webm',
    },
    {
      byteSize: 128,
      caseName: 'too long',
      durationMs: 120_001,
      mimeType: 'audio/webm',
    },
    {
      byteSize: 128,
      caseName: 'fractional duration',
      durationMs: 250.5,
      mimeType: 'audio/webm',
    },
    {
      byteSize: 128,
      caseName: 'unsupported MIME',
      durationMs: 250,
      mimeType: 'audio/wav',
    },
  ])(
    'rejects $caseName before forming a local capture receipt',
    ({ byteSize, durationMs, mimeType }) => {
      const blob = new Blob([], { type: mimeType })
      Object.defineProperty(blob, 'size', { value: byteSize })

      expect(
        isValidLocalCapture({ blob, durationMs, mimeType }),
      ).toBe(false)
    },
  )

  it.each([
    'audio/webm',
    'audio/webm;codecs=opus',
    'audio/webm; codecs=opus',
    'audio/ogg',
    'audio/ogg;codecs=opus',
    'audio/mp4',
    'audio/mp4;codecs=mp4a.40.2',
  ])('accepts bounded integer metadata for %s', (mimeType) => {
    const blob = new Blob([], { type: mimeType })
    Object.defineProperty(blob, 'size', { value: 128 })

    expect(
      isValidLocalCapture({ blob, durationMs: 250, mimeType }),
    ).toBe(true)
  })

  it('cancels a recording session that resolves after unmount', async () => {
    let resolveStart:
      | ((session: AudioRecordingSession) => void)
      | undefined
    const pendingStart = new Promise<AudioRecordingSession>((resolve) => {
      resolveStart = resolve
    })
    const recorder: AudioRecorder = {
      isSupported: () => true,
      start: vi.fn(() => pendingStart),
    }
    const session: AudioRecordingSession = {
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob(['x'.repeat(128)], { type: 'audio/webm' }),
        durationMs: 250,
        mimeType: 'audio/webm',
      })),
    }
    const user = userEvent.setup()
    const view = renderTurn(recorder)

    await user.click(screen.getByRole('button', { name: labels.controls.start }))
    view.unmount()
    await act(async () => {
      resolveStart?.(session)
      await pendingStart
    })

    await waitFor(() => expect(session.cancel).toHaveBeenCalledOnce())
    expect(URL.createObjectURL).not.toHaveBeenCalled()
  })

  it('rejects out-of-contract capture metadata without creating a receipt', async () => {
    const session: AudioRecordingSession = {
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob(['x'.repeat(127)], { type: 'audio/webm' }),
        durationMs: 249.5,
        mimeType: 'audio/webm',
      })),
    }
    const recorder: AudioRecorder = {
      isSupported: () => true,
      start: vi.fn(async () => session),
    }
    const onCaptureChange = vi.fn()
    const user = userEvent.setup()

    renderTurn(recorder, onCaptureChange)
    await user.click(screen.getByRole('button', { name: labels.controls.start }))
    await user.click(
      await screen.findByRole('button', { name: labels.controls.stop }),
    )

    expect(
      await screen.findByText(labels.controls.error),
    ).toBeVisible()
    expect(
      screen.getByRole('button', { name: labels.retry }),
    ).toBeVisible()
    expect(URL.createObjectURL).not.toHaveBeenCalled()
    expect(onCaptureChange).toHaveBeenCalledWith(null)
    expect(
      onCaptureChange.mock.calls.some(([capture]) => capture !== null),
    ).toBe(false)
  })

  it('revokes a newly-created object URL when capture completion unmounts the turn', async () => {
    const session: AudioRecordingSession = {
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob(['x'.repeat(128)], { type: 'audio/webm' }),
        durationMs: 250,
        mimeType: 'audio/webm',
      })),
    }
    const recorder: AudioRecorder = {
      isSupported: () => true,
      start: vi.fn(async () => session),
    }
    const user = userEvent.setup()
    const view = renderTurn(recorder)

    await user.click(screen.getByRole('button', { name: labels.controls.start }))
    await user.click(
      await screen.findByRole('button', { name: labels.controls.stop }),
    )
    await screen.findByLabelText(labels.playbackLabel)
    view.unmount()

    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:local')
  })

  it('hold-to-talk release submits capture without local playback', async () => {
    const session: AudioRecordingSession = {
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob(['x'.repeat(128)], { type: 'audio/webm' }),
        durationMs: 250,
        mimeType: 'audio/webm',
      })),
    }
    const recorder: AudioRecorder = {
      isSupported: () => true,
      start: vi.fn(async () => session),
    }
    const onAudioCaptureChange = vi.fn()
    const user = userEvent.setup()

    render(
      <RecordingTurn
        captureMode="hold"
        createClientId={() => '018f4f82-6400-7a12-8f4b-20b12bf6b6f3'}
        labels={{
          ...labels,
          controls: {
            ...labels.controls,
            hold: 'Hold to talk',
            holding: 'Listening…',
            release: 'Release to send',
          },
        }}
        onAudioCaptureChange={onAudioCaptureChange}
        onCaptureChange={vi.fn()}
        onContinueWithoutRecording={vi.fn()}
        recorder={recorder}
        skipReviewPlayback
      />,
    )

    const button = screen.getByRole('button', { name: 'Hold to talk' })
    await user.pointer({ keys: '[MouseLeft>]', target: button })
    await screen.findByRole('button', { name: 'Release to send' })
    await user.pointer({ keys: '[/MouseLeft]', target: button })

    await waitFor(() => {
      expect(onAudioCaptureChange).toHaveBeenCalled()
    })
    expect(
      screen.queryByLabelText(labels.playbackLabel),
    ).not.toBeInTheDocument()
    expect(URL.createObjectURL).not.toHaveBeenCalled()
  })
})

function renderTurn(
  recorder: AudioRecorder,
  onCaptureChange = vi.fn(),
) {
  return render(
    <RecordingTurn
      createClientId={() => '018f4f82-6400-7a12-8f4b-20b12bf6b6f3'}
      labels={labels}
      onCaptureChange={onCaptureChange}
      onContinueWithoutRecording={vi.fn()}
      recorder={recorder}
    />,
  )
}
