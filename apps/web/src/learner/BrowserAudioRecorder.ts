export interface CapturedAudio {
  readonly blob: Blob
  readonly durationMs: number
  readonly mimeType: string
}

export interface AudioRecordingSession {
  pause(): void
  resume(): void
  stop(): Promise<CapturedAudio>
  cancel(): void
}

export interface AudioRecorder {
  isSupported(): boolean
  start(): Promise<AudioRecordingSession>
}

export class RecordingPermissionDeniedError extends Error {
  constructor() {
    super('Microphone permission was denied.')
    this.name = 'RecordingPermissionDeniedError'
  }
}

export class RecordingUnavailableError extends Error {
  constructor() {
    super('Audio recording is unavailable.')
    this.name = 'RecordingUnavailableError'
  }
}

export class BrowserAudioRecorder implements AudioRecorder {
  isSupported(): boolean {
    return (
      typeof navigator !== 'undefined' &&
      navigator.mediaDevices?.getUserMedia !== undefined &&
      typeof MediaRecorder !== 'undefined'
    )
  }

  async start(): Promise<AudioRecordingSession> {
    if (!this.isSupported()) {
      throw new RecordingUnavailableError()
    }

    let stream: MediaStream

    try {
      stream = await navigator.mediaDevices.getUserMedia({
        audio: true,
        video: false,
      })
    } catch (error) {
      if (
        error instanceof DOMException &&
        (error.name === 'NotAllowedError' ||
          error.name === 'SecurityError')
      ) {
        throw new RecordingPermissionDeniedError()
      }

      throw new RecordingUnavailableError()
    }

    try {
      return new BrowserAudioRecordingSession(stream)
    } catch {
      stopTracks(stream)
      throw new RecordingUnavailableError()
    }
  }
}

class BrowserAudioRecordingSession implements AudioRecordingSession {
  readonly #recorder: MediaRecorder
  readonly #stream: MediaStream
  readonly #chunks: Blob[] = []
  readonly #startedAt: number
  #cancelled = false
  #completion: Promise<CapturedAudio> | null = null

  constructor(stream: MediaStream) {
    this.#stream = stream
    const mimeType = preferredMimeType()
    this.#recorder =
      mimeType === undefined
        ? new MediaRecorder(stream)
        : new MediaRecorder(stream, { mimeType })
    this.#startedAt = performance.now()
    this.#recorder.addEventListener('dataavailable', (event) => {
      if (!this.#cancelled && event.data.size > 0) {
        this.#chunks.push(event.data)
      }
    })
    this.#recorder.start()
  }

  pause(): void {
    if (this.#recorder.state === 'recording') {
      this.#recorder.pause()
    }
  }

  resume(): void {
    if (this.#recorder.state === 'paused') {
      this.#recorder.resume()
    }
  }

  stop(): Promise<CapturedAudio> {
    if (this.#completion !== null) {
      return this.#completion
    }

    this.#completion = new Promise<CapturedAudio>((resolve, reject) => {
      this.#recorder.addEventListener(
        'error',
        () => {
          stopTracks(this.#stream)
          reject(new RecordingUnavailableError())
        },
        { once: true },
      )
      this.#recorder.addEventListener(
        'stop',
        () => {
          stopTracks(this.#stream)

          if (this.#cancelled) {
            reject(new RecordingUnavailableError())
            return
          }

          const mimeType =
            this.#recorder.mimeType ||
            this.#chunks[0]?.type ||
            'application/octet-stream'
          const blob = new Blob(this.#chunks, { type: mimeType })

          if (blob.size === 0) {
            reject(new RecordingUnavailableError())
            return
          }

          resolve({
            blob,
            durationMs: Math.max(
              1,
              Math.round(performance.now() - this.#startedAt),
            ),
            mimeType,
          })
        },
        { once: true },
      )

      if (this.#recorder.state === 'inactive') {
        stopTracks(this.#stream)
        reject(new RecordingUnavailableError())
        return
      }

      this.#recorder.stop()
    })

    return this.#completion
  }

  cancel(): void {
    this.#cancelled = true
    this.#chunks.length = 0

    if (this.#recorder.state !== 'inactive') {
      this.#recorder.stop()
    }

    stopTracks(this.#stream)
  }
}

function preferredMimeType(): string | undefined {
  for (const candidate of [
    'audio/webm;codecs=opus',
    'audio/webm',
    'audio/mp4',
  ]) {
    if (MediaRecorder.isTypeSupported(candidate)) {
      return candidate
    }
  }

  return undefined
}

function stopTracks(stream: MediaStream): void {
  for (const track of stream.getTracks()) {
    track.stop()
  }
}

export const defaultAudioRecorder = new BrowserAudioRecorder()
