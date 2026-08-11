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
  readonly source: string
}

export function ModelAudioPlayer({
  labels,
  loadSource,
  mimeType,
  source,
}: ModelAudioPlayerProps) {
  const audioRef = useRef<HTMLAudioElement>(null)
  const [state, setState] = useState<AudioControlState>('ready')
  const [loadedSource, setLoadedSource] = useState<{
    readonly loader: NonNullable<ModelAudioPlayerProps['loadSource']>
    readonly objectUrl: string
    readonly source: string
  } | null>(null)

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
          objectUrl,
          source,
        })
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
  }, [loadSource, source])

  const playableSource =
    loadSource === undefined
      ? source
      : loadedSource?.loader === loadSource &&
          loadedSource.source === source
        ? loadedSource.objectUrl
        : null

  useEffect(() => {
    const audio = audioRef.current

    if (audio === null) {
      return
    }

    const onCanPlay = () => setState('ready')
    const onEnded = () => setState('ended')
    const onError = () => setState('error')
    const onPause = () => {
      if (!audio.ended) {
        setState('paused')
      }
    }
    const onPlaying = () => setState('playing')
    const onWaiting = () => setState('loading')

    audio.addEventListener('canplay', onCanPlay)
    audio.addEventListener('ended', onEnded)
    audio.addEventListener('error', onError)
    audio.addEventListener('pause', onPause)
    audio.addEventListener('playing', onPlaying)
    audio.addEventListener('waiting', onWaiting)
    setState('loading')
    audio.load()

    return () => {
      audio.removeEventListener('canplay', onCanPlay)
      audio.removeEventListener('ended', onEnded)
      audio.removeEventListener('error', onError)
      audio.removeEventListener('pause', onPause)
      audio.removeEventListener('playing', onPlaying)
      audio.removeEventListener('waiting', onWaiting)
      audio.pause()
      for (const sourceElement of audio.querySelectorAll('source')) {
        sourceElement.removeAttribute('src')
      }
      audio.load()
    }
  }, [playableSource])

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
            type={mimeType}
          />
        </audio>
      )}
      <AudioControls
        labels={labels}
        onRequestPause={() => audioRef.current?.pause()}
        onRequestPlay={() => void play()}
        onRequestReplay={() => void replay()}
        state={state}
      />
    </>
  )
}
