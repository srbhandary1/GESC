import { markSpeakLoop } from '../conversation/speakLoopTimings'
import { concatenateFloat32 } from '../shared/pcmWav'
import { stopSpeechPlayback } from '../shared/stopSpeechPlayback'
import { encodeAudiblePcmCapture } from './encodeAudiblePcmCapture'
import { canonicalCaptureMimeType } from './LocalCaptureReceipt'

export interface CapturedAudio {
  readonly blob: Blob
  readonly durationMs: number
  readonly mimeType: string
}

export type RecordingLevelListener = (rms: number) => void

export interface AudioRecordingSession {
  pause(): void
  resume(): void
  stop(): Promise<CapturedAudio>
  cancel(): void
  subscribeLevels?(listener: RecordingLevelListener): () => void
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
  readonly #recorder: MediaRecorder | null
  readonly #levelListeners = new Set<RecordingLevelListener>()
  #paused = false
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
    this.#recorder?.start(100)
  }

  readonly #onAudioProcess = (event: AudioProcessingEvent): void => {
    if (this.#cancelled || this.#paused) {
      return
    }

    const input = event.inputBuffer.getChannelData(0)
    this.#pcmChunks.push(new Float32Array(input))

    if (this.#levelListeners.size > 0) {
      const rms = rootMeanSquare(input)
      for (const listener of this.#levelListeners) {
        listener(rms)
      }
    }
  }

  subscribeLevels(listener: RecordingLevelListener): () => void {
    this.#levelListeners.add(listener)

    return () => {
      this.#levelListeners.delete(listener)
    }
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

  async #finish(): Promise<CapturedAudio> {
    const sampleRate = this.#context.sampleRate
    const pcm = concatenateFloat32(this.#pcmChunks)
    const durationFallbackMs = Math.round(performance.now() - this.#startedAt)
    const receiptMime =
      canonicalCaptureMimeType(
        this.#recorder?.mimeType ||
          this.#mediaChunks[0]?.type ||
          'audio/webm',
      ) ?? 'audio/webm'

    const pcmCapture = encodeAudiblePcmCapture(
      pcm,
      sampleRate,
      receiptMime,
      durationFallbackMs,
    )
    if (pcmCapture !== null) {
      this.#teardown()
      if (this.#cancelled) {
        throw new RecordingUnavailableError()
      }

      markSpeakLoop('wav_ready')
      return pcmCapture
    }

    const mediaBlob = await this.#stopBackupRecorder()
    this.#teardown()

    if (this.#cancelled) {
      throw new RecordingUnavailableError()
    }

    const durationMs = Math.max(
      1,
      pcm.length > 0
        ? Math.round((pcm.length / sampleRate) * 1000)
        : durationFallbackMs,
    )

    if (mediaBlob !== null && mediaBlob.size > 0) {
      markSpeakLoop('wav_ready')
      return {
        blob: mediaBlob,
        durationMs,
        mimeType: receiptMime,
      }
    }

    throw new RecordingUnavailableError()
  }

  #stopBackupRecorder(): Promise<Blob | null> {
    const recorder = this.#recorder
    if (recorder === null) {
      return Promise.resolve(null)
    }

    return new Promise((resolve) => {
      recorder.addEventListener(
        'error',
        () => resolve(null),
        { once: true },
      )
      recorder.addEventListener(
        'stop',
        () => {
          resolve(
            this.#mediaChunks.length === 0
              ? null
              : new Blob(this.#mediaChunks, {
                  type: recorder.mimeType || this.#mediaChunks[0]?.type,
                }),
          )
        },
        { once: true },
      )

      if (recorder.state === 'inactive') {
        resolve(
          this.#mediaChunks.length === 0
            ? null
            : new Blob(this.#mediaChunks, { type: recorder.mimeType }),
        )
        return
      }

      if (typeof recorder.requestData === 'function') {
        recorder.requestData()
      }
      recorder.stop()
    })
  }

  #teardown(): void {
    this.#processor.removeEventListener('audioprocess', this.#onAudioProcess)
    this.#levelListeners.clear()
    try {
      this.#processor.disconnect()
      this.#source.disconnect()
      this.#mute.disconnect()
    } catch {
      // Nodes may already be disconnected after a previous stop/cancel.
    }
    void this.#context.close().catch(() => undefined)
    if (this.#recorder !== null && this.#recorder.state !== 'inactive') {
      try {
        this.#recorder.stop()
      } catch {
        // The backup recorder may already have stopped.
      }
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

function rootMeanSquare(samples: Float32Array): number {
  if (samples.length === 0) {
    return 0
  }

  let sum = 0
  for (let index = 0; index < samples.length; index += 1) {
    const sample = samples[index] ?? 0
    sum += sample * sample
  }

  return Math.sqrt(sum / samples.length)
}

export const defaultAudioRecorder = new BrowserAudioRecorder()
