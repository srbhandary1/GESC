import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type {
  AudioRecorder,
  AudioRecordingSession,
} from './BrowserAudioRecorder'
import { RecordingPermissionDeniedError } from './BrowserAudioRecorder'
import { HOLD_MAXIMUM_DURATION_MS } from '../conversation/holdToTalkLimit'
import {
  RecordingTurn,
  type RecordingTurnLabels,
} from './RecordingTurn'
import { isValidLocalCapture } from './LocalCaptureReceipt'

const labels: RecordingTurnLabels = {
  backCancelled: 'Recording cancelled because you went back.',
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
  vi.useRealTimers()
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

  it('consumes Android Back while recording and explains the cancellation', async () => {
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

    renderTurn(recorder)
    await user.click(screen.getByRole('button', { name: labels.controls.start }))
    await screen.findByText(labels.controls.recording)

    const request = new CustomEvent('ge:android-back-request', {
      cancelable: true,
    })
    expect(window.dispatchEvent(request)).toBe(false)
    expect(session.cancel).toHaveBeenCalledOnce()
    expect(await screen.findByRole('status')).toHaveTextContent(
      labels.backCancelled,
    )
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
    const send = screen.getByRole('button', { name: 'Release to send' })
    await user.pointer({ keys: '[MouseLeft>]', target: send })
    await user.pointer({ keys: '[/MouseLeft]', target: send })

    await waitFor(() => {
      expect(onAudioCaptureChange).toHaveBeenCalled()
    })
    expect(
      screen.queryByLabelText(labels.playbackLabel),
    ).not.toBeInTheDocument()
    expect(URL.createObjectURL).not.toHaveBeenCalled()
    expect(
      screen.getByRole('button', { name: labels.controls.cancel }),
    ).toBeInTheDocument()
  })

  it('keeps voice capture in place and explains a sub-second clip', async () => {
    const session: AudioRecordingSession = {
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob(['too short'], { type: 'audio/webm' }),
        durationMs: 750,
        mimeType: 'audio/webm',
      })),
    }
    const recorder: AudioRecorder = {
      isSupported: () => true,
      start: vi.fn(async () => session),
    }
    const user = userEvent.setup()

    render(
      <RecordingTurn
        captureMode="hold"
        createClientId={() => '018f4f82-6400-7a12-8f4b-20b12bf6b6f3'}
        labels={{
          ...labels,
          tooShort: 'That recording was too short.',
          controls: {
            ...labels.controls,
            hold: 'Hold to talk',
            holding: 'Listening…',
            release: 'Release to send',
          },
        }}
        onAudioCaptureChange={vi.fn()}
        onCaptureChange={vi.fn()}
        onContinueWithoutRecording={vi.fn()}
        recorder={recorder}
        skipReviewPlayback
        minimumDurationMs={1_000}
      />,
    )

    const hold = screen.getByRole('button', { name: 'Hold to talk' })
    await user.pointer({ keys: '[MouseLeft>]', target: hold })
    await screen.findByRole('button', { name: 'Release to send' })
    await user.pointer({ keys: '[/MouseLeft]', target: hold })
    const send = screen.getByRole('button', { name: 'Release to send' })
    await user.pointer({ keys: '[MouseLeft>]', target: send })
    await user.pointer({ keys: '[/MouseLeft]', target: send })

    expect(await screen.findByText(labels.controls.error)).toBeVisible()
    expect(screen.getByRole('alert')).toHaveTextContent(
      'That recording was too short.',
    )
    const retry = screen.getByRole('button', { name: 'Hold to talk' })
    expect(retry).toBeVisible()
    expect(
      screen.queryByRole('button', { name: labels.retry }),
    ).not.toBeInTheDocument()

    await user.pointer({ keys: '[MouseLeft>]', target: retry })
    await waitFor(() => {
      expect(recorder.start).toHaveBeenCalledTimes(2)
    })
  })

  it('hold cancel after release aborts the in-flight submit', async () => {
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
    const onCancelInFlight = vi.fn()
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
        onAudioCaptureChange={vi.fn()}
        onCaptureChange={vi.fn()}
        onCancelInFlight={onCancelInFlight}
        onContinueWithoutRecording={vi.fn()}
        recorder={recorder}
        skipReviewPlayback
      />,
    )

    const button = screen.getByRole('button', { name: 'Hold to talk' })
    await user.pointer({ keys: '[MouseLeft>]', target: button })
    await screen.findByRole('button', { name: 'Release to send' })
    await user.pointer({ keys: '[/MouseLeft]', target: button })
    const send = screen.getByRole('button', { name: 'Release to send' })
    await user.pointer({ keys: '[MouseLeft>]', target: send })
    await user.pointer({ keys: '[/MouseLeft]', target: send })
    await screen.findByRole('button', { name: labels.controls.cancel })
    await user.click(screen.getByRole('button', { name: labels.controls.cancel }))
    expect(onCancelInFlight).toHaveBeenCalledOnce()
  })

  it('hold-to-talk auto-stops at the 25 second Speak cap', async () => {
    vi.useFakeTimers()
    const session: AudioRecordingSession = {
      cancel: vi.fn(),
      pause: vi.fn(),
      resume: vi.fn(),
      stop: vi.fn(async () => ({
        blob: new Blob(['x'.repeat(128)], { type: 'audio/webm' }),
        durationMs: HOLD_MAXIMUM_DURATION_MS,
        mimeType: 'audio/webm',
      })),
    }
    const recorder: AudioRecorder = {
      isSupported: () => true,
      start: vi.fn(async () => session),
    }
    const onAudioCaptureChange = vi.fn()

    render(
      <RecordingTurn
        captureMode="hold"
        createClientId={() => '018f4f82-6400-7a12-8f4b-20b12bf6b6f3'}
        labels={{
          ...labels,
          holdRemaining: '{seconds} seconds left',
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

    await act(async () => {
      fireEvent.pointerDown(
        screen.getByRole('button', { name: 'Hold to talk' }),
        { button: 0 },
      )
    })

    expect(
      screen.getByRole('button', { name: 'Release to send' }),
    ).toBeVisible()
    expect(screen.getByText('25 seconds left')).toBeVisible()

    await act(async () => {
      vi.advanceTimersByTime(HOLD_MAXIMUM_DURATION_MS)
    })

    expect(session.stop).toHaveBeenCalledOnce()
    expect(onAudioCaptureChange).toHaveBeenCalled()
  })

  it('tap recording does not auto-stop at the Speak hold cap', async () => {
    vi.useFakeTimers()
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
    renderTurn(recorder)

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: labels.controls.start }))
    })

    expect(
      screen.getByRole('button', { name: labels.controls.stop }),
    ).toBeVisible()

    await act(async () => {
      vi.advanceTimersByTime(HOLD_MAXIMUM_DURATION_MS)
    })

    expect(session.stop).not.toHaveBeenCalled()
  })

  it('recovers from denied microphone permission in hold-to-talk mode', async () => {
    const onHoldPhaseChange = vi.fn()
    const onContinueWithoutRecording = vi.fn()
    const recorder: AudioRecorder = {
      isSupported: () => true,
      start: vi.fn(async () => {
        throw new RecordingPermissionDeniedError()
      }),
    }
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
        onAudioCaptureChange={vi.fn()}
        onCaptureChange={vi.fn()}
        onContinueWithoutRecording={onContinueWithoutRecording}
        onHoldPhaseChange={onHoldPhaseChange}
        recorder={recorder}
        skipReviewPlayback
      />,
    )

    await user.pointer({
      keys: '[MouseLeft>]',
      target: screen.getByRole('button', { name: 'Hold to talk' }),
    })

    expect(await screen.findByText('Recording blocked')).toBeVisible()
    expect(onHoldPhaseChange).toHaveBeenCalledWith('idle')
    expect(
      screen.getByRole('button', { name: 'Record again' }),
    ).toBeVisible()
    expect(
      screen.getByRole('button', { name: 'Continue without recording' }),
    ).toBeVisible()
    expect(
      screen.getByRole('button', { name: 'Hold to talk' }),
    ).toBeEnabled()
  })

  it.each([
    {
      denial: 'denied_once' as const,
      message: 'Permission denied once',
      retryVisible: true,
    },
    {
      denial: 'denied_permanently' as const,
      message: 'Permission denied permanently',
      retryVisible: false,
    },
  ])(
    'shows a rationale and exposes the $denial Android recovery branch',
    async ({ denial, message, retryVisible }) => {
      const onContinueWithoutRecording = vi.fn()
      const recorder: AudioRecorder = {
        isSupported: () => true,
        requiresContextualPermissionRationale: () => true,
        start: vi.fn(async () => {
          throw new RecordingPermissionDeniedError(denial)
        }),
      }
      const user = userEvent.setup()

      render(
        <RecordingTurn
          createClientId={() => '018f4f82-6400-7a12-8f4b-20b12bf6b6f3'}
          labels={{
            ...labels,
            continueWithout: 'Type instead',
            permissionContinue: 'Continue to microphone',
            permissionDeniedOnce: 'Permission denied once',
            permissionDeniedPermanently: 'Permission denied permanently',
          }}
          onCaptureChange={vi.fn()}
          onContinueWithoutRecording={onContinueWithoutRecording}
          recorder={recorder}
        />,
      )

      await user.click(screen.getByRole('button', { name: 'Start' }))
      expect(recorder.start).not.toHaveBeenCalled()
      expect(screen.getByText(labels.permissionExplanation)).toBeVisible()
      expect(screen.getByRole('button', {
        name: 'Type instead',
      })).toBeVisible()

      await user.click(screen.getByRole('button', {
        name: 'Continue to microphone',
      }))

      expect(await screen.findByText(message)).toBeVisible()
      expect(recorder.start).toHaveBeenCalledOnce()
      expect(screen.getByRole('button', {
        name: 'Type instead',
      })).toBeVisible()
      expect(screen.queryByRole('button', {
        name: labels.retry,
      }) !== null).toBe(retryVisible)
    },
  )
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
