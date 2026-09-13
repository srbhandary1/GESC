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
      typeof window !== 'undefined' &&
      window.isSecureContext &&
      typeof navigator !== 'undefined' &&
      navigator.mediaDevices?.getUserMedia !== undefined &&
      audioContextConstructor() !== undefined
    )
  }

  async start(): Promise<AudioRecordingSession> {
    if (!this.isSupported()) {
      throw new RecordingUnavailableError()
    }

    stopSpeechPlayback()

    let stream: MediaStream

    try {
      stream = await requestMicrophone()
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
      await waitForLiveAudioTrack(stream)
      return await BrowserAudioRecordingSession.open(stream)
    } catch {
      stopTracks(stream)
      throw new RecordingUnavailableError()
    }
  }
}

class BrowserAudioRecordingSession implements AudioRecordingSession {
  readonly #stream: MediaStream
  readonly #context: AudioContext
  readonly #source: MediaStreamAudioSourceNode
  readonly #processor: ScriptProcessorNode
  readonly #mute: GainNode
  readonly #pcmChunks: Float32Array[] = []
  readonly #mediaChunks: Blob[] = []
  readonly #startedAt: number
  readonly #recorder: MediaRecorder | null
  readonly #levelListeners = new Set<RecordingLevelListener>()
  #paused = false
  #cancelled = false
  #completion: Promise<CapturedAudio> | null = null

  static async open(stream: MediaStream): Promise<BrowserAudioRecordingSession> {
    const AudioContextConstructor = audioContextConstructor()
    if (AudioContextConstructor === undefined) {
      throw new RecordingUnavailableError()
    }

    const context = new AudioContextConstructor()
    if (context.state === 'suspended') {
      await context.resume()
    }

    try {
      return new BrowserAudioRecordingSession(stream, context)
    } catch (error) {
      await context.close().catch(() => undefined)
      throw error
    }
  }

  private constructor(stream: MediaStream, context: AudioContext) {
    this.#stream = stream
    this.#context = context
    this.#source = context.createMediaStreamSource(stream)
    this.#processor = context.createScriptProcessor(4096, 1, 1)
    this.#mute = context.createGain()
    this.#mute.gain.value = 0
    this.#startedAt = performance.now()
    this.#processor.addEventListener('audioprocess', this.#onAudioProcess)
    this.#source.connect(this.#processor)
    this.#processor.connect(this.#mute)
    this.#mute.connect(context.destination)

    this.#recorder = createBackupRecorder(stream, (chunk) => {
      if (!this.#cancelled && chunk.size > 0) {
        this.#mediaChunks.push(chunk)
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
    this.#paused = true
    if (this.#recorder?.state === 'recording') {
      this.#recorder.pause()
    }
  }

  resume(): void {
    this.#paused = false
    if (this.#recorder?.state === 'paused') {
      this.#recorder.resume()
    }
  }

  stop(): Promise<CapturedAudio> {
    if (this.#completion !== null) {
      return this.#completion
    }

    this.#completion = this.#finish()
    return this.#completion
  }

  cancel(): void {
    this.#cancelled = true
    this.#pcmChunks.length = 0
    this.#mediaChunks.length = 0
    this.#teardown()
  }

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

function createBackupRecorder(
  stream: MediaStream,
  onChunk: (chunk: Blob) => void,
): MediaRecorder | null {
  if (typeof MediaRecorder === 'undefined') {
    return null
  }

  try {
    const mimeType = preferredMimeType()
    const recorder =
      mimeType === undefined
        ? new MediaRecorder(stream)
        : new MediaRecorder(stream, { mimeType })
    recorder.addEventListener('dataavailable', (event) => {
      onChunk(event.data)
    })
    return recorder
  } catch {
    return null
  }
}

function preferredMimeType(): string | undefined {
  if (typeof MediaRecorder === 'undefined') {
    return undefined
  }

  for (const candidate of [
    'audio/webm;codecs=opus',
    'audio/webm',
    'audio/ogg;codecs=opus',
    'audio/ogg',
    'audio/mp4',
  ]) {
    if (MediaRecorder.isTypeSupported(candidate)) {
      return candidate
    }
  }

  return undefined
}

function audioContextConstructor(): typeof AudioContext | undefined {
  if (typeof globalThis === 'undefined') {
    return undefined
  }

  return (
    globalThis.AudioContext ??
    (
      globalThis as typeof globalThis & {
        webkitAudioContext?: typeof AudioContext
      }
    ).webkitAudioContext
  )
}

async function requestMicrophone(): Promise<MediaStream> {
  const constraints: MediaStreamConstraints = {
    audio: {
      channelCount: 1,
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl: true,
    },
    video: false,
  }

  try {
    return await navigator.mediaDevices.getUserMedia(constraints)
  } catch (error) {
    if (
      !(error instanceof DOMException) ||
      (error.name !== 'NotReadableError' && error.name !== 'AbortError')
    ) {
      throw error
    }
  }

  await wait(150)
  stopSpeechPlayback()

  return navigator.mediaDevices.getUserMedia(constraints)
}

async function waitForLiveAudioTrack(stream: MediaStream): Promise<void> {
  const track = stream.getAudioTracks()[0]
  if (track === undefined) {
    throw new RecordingUnavailableError()
  }

  if (!track.muted && track.readyState === 'live') {
    return
  }

  await Promise.race([
    new Promise<void>((resolve) => {
      track.addEventListener('unmute', () => resolve(), { once: true })
    }),
    wait(400),
  ])
}

function wait(milliseconds: number): Promise<void> {
  return new Promise((resolve) => {
    globalThis.setTimeout(resolve, milliseconds)
  })
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
