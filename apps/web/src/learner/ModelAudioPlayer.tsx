import {
  AudioControls,
  type AudioControlLabels,
  type AudioControlState,
} from '@global-english/design-system'
import { useEffect, useRef, useState } from 'react'

interface ModelAudioPlayerProps {
  readonly labels: AudioControlLabels
  readonly loadSource?: (
    source: string,
    signal?: AbortSignal,
  ) => Promise<Blob>
  readonly mimeType: string
  readonly onEnded?: () => void
  readonly onPause?: () => void
  readonly onPlaying?: () => void
  readonly source: string
}

export function ModelAudioPlayer({
  labels,
  loadSource,
  mimeType,
  onEnded,
  onPause,
  onPlaying,
  source,
}: ModelAudioPlayerProps) {
  const audioRef = useRef<HTMLAudioElement>(null)
  const onEndedRef = useRef(onEnded)
  const onPauseRef = useRef(onPause)
  const onPlayingRef = useRef(onPlaying)
  const [state, setState] = useState<AudioControlState>(
    loadSource === undefined ? 'ready' : 'loading',
  )
  const [loadedSource, setLoadedSource] = useState<{
    readonly loader: NonNullable<ModelAudioPlayerProps['loadSource']>
    readonly mimeType: string
    readonly objectUrl: string
    readonly source: string
  } | null>(null)

  useEffect(() => {
    onEndedRef.current = onEnded
    onPauseRef.current = onPause
    onPlayingRef.current = onPlaying
  })

  useEffect(() => {
    if (loadSource === undefined) {
      return
    }

    const controller = new AbortController()
    let objectUrl: string | null = null

    void loadSource(source, controller.signal)
      .then((audio) => {
        if (controller.signal.aborted) {
          return
        }

        objectUrl = URL.createObjectURL(audio)
        setLoadedSource({
          loader: loadSource,
          mimeType: audio.type === '' ? mimeType : audio.type,
          objectUrl,
          source,
        })
        setState('ready')
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setState('error')
        }
      })

    return () => {
      controller.abort()

      if (objectUrl !== null) {
        URL.revokeObjectURL(objectUrl)
      }
    }
  }, [loadSource, mimeType, source])

  const playableSource =
    loadSource === undefined
      ? source
      : loadedSource?.loader === loadSource &&
          loadedSource.source === source
        ? loadedSource.objectUrl
        : null

  // While a fetched source is not yet playable the player is loading unless
  // the fetch already failed. Deriving this replaces a synchronous setState
  // inside the fetch effect.
  const visibleState: AudioControlState =
    loadSource !== undefined && playableSource === null && state !== 'error'
      ? 'loading'
      : state

  useEffect(() => {
    const audio = audioRef.current

    if (audio === null) {
      return
    }

    const handleCanPlay = () => setState('ready')
    const handleEnded = () => {
      setState('ended')
      onEndedRef.current?.()
    }
    const handleError = () => setState('error')
    const handlePause = () => {
      if (!audio.ended) {
        setState('paused')
        onPauseRef.current?.()
      }
    }
    const handlePlaying = () => {
      setState('playing')
      onPlayingRef.current?.()
    }
    const handleWaiting = () => setState('loading')

    audio.addEventListener('canplay', handleCanPlay)
    audio.addEventListener('ended', handleEnded)
    audio.addEventListener('error', handleError)
    audio.addEventListener('pause', handlePause)
    audio.addEventListener('playing', handlePlaying)
    if (loadSource === undefined) {
      audio.addEventListener('waiting', handleWaiting)
    }
    audio.load()

    return () => {
      audio.removeEventListener('canplay', handleCanPlay)
      audio.removeEventListener('ended', handleEnded)
      audio.removeEventListener('error', handleError)
      audio.removeEventListener('pause', handlePause)
      audio.removeEventListener('playing', handlePlaying)
      audio.removeEventListener('waiting', handleWaiting)
      audio.pause()
      for (const sourceElement of audio.querySelectorAll('source')) {
        sourceElement.removeAttribute('src')
      }
      audio.load()
    }
  }, [loadSource, playableSource])

  const play = async () => {
    const audio = audioRef.current

    if (audio === null) {
      setState('unavailable')
      return
    }

    setState('loading')

    try {
      await audio.play()
    } catch {
      setState('error')
    }
  }

  const replay = async () => {
    const audio = audioRef.current

    if (audio === null) {
      setState('unavailable')
      return
    }

    audio.currentTime = 0
    await play()
  }

  return (
    <>
      {playableSource === null ? null : (
        <audio
          aria-hidden="true"
          data-testid="model-audio-element"
          key={playableSource}
          preload="metadata"
          ref={audioRef}
        >
          <source
            src={playableSource}
            type={loadedSource?.mimeType ?? mimeType}
          />
        </audio>
      )}
      <AudioControls
        labels={labels}
        onRequestPause={() => audioRef.current?.pause()}
        onRequestPlay={() => void play()}
        onRequestReplay={() => void replay()}
        state={visibleState}
      />
    </>
  )
}
