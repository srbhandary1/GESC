import {
  AudioControls,
  type AudioControlLabels,
  type AudioControlState,
} from '@global-english/design-system'
import { useEffect, useRef, useState } from 'react'
import './LearnerJourney.css'

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
  readonly seekLabel: string
  readonly slowLabel: string
  readonly source: string
  readonly stopLabel: string
}

const SLOW_RATE = 0.75
const positionsBySource = new Map<string, number>()

export function ModelAudioPlayer({
  labels,
  loadSource,
  mimeType,
  onEnded,
  onPause,
  onPlaying,
  seekLabel,
  slowLabel,
  source,
  stopLabel,
}: ModelAudioPlayerProps) {
  const audioRef = useRef<HTMLAudioElement>(null)
  const onEndedRef = useRef(onEnded)
  const onPauseRef = useRef(onPause)
  const onPlayingRef = useRef(onPlaying)
  const [slow, setSlow] = useState(false)
  const slowRef = useRef(false)
  const [state, setState] = useState<AudioControlState>(
    loadSource === undefined ? 'ready' : 'loading',
  )
  const [currentTime, setCurrentTime] = useState(0)
  const [duration, setDuration] = useState(0)
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

  const visibleState: AudioControlState =
    loadSource !== undefined && playableSource === null && state !== 'error'
      ? 'loading'
      : state

  useEffect(() => {
    const audio = audioRef.current

    if (audio === null) {
      return
    }

    const restorePosition = () => {
      const saved = positionsBySource.get(source)
      if (saved !== undefined && saved > 0 && !audio.ended) {
        audio.currentTime = saved
      }
    }

    const handleCanPlay = () => {
      restorePosition()
      audio.playbackRate = slowRef.current ? SLOW_RATE : 1
      setDuration(Number.isFinite(audio.duration) ? audio.duration : 0)
      setCurrentTime(audio.currentTime)
      setState('ready')
    }
    const handleLoadedMetadata = () => {
      setDuration(Number.isFinite(audio.duration) ? audio.duration : 0)
    }
    const handleEnded = () => {
      positionsBySource.delete(source)
      setState('ended')
      onEndedRef.current?.()
    }
    const handleError = () => setState('error')
    const handlePause = () => {
      positionsBySource.set(source, audio.currentTime)
      if (!audio.ended) {
        setState('paused')
        onPauseRef.current?.()
      }
    }
    const handlePlaying = () => {
      audio.playbackRate = slowRef.current ? SLOW_RATE : 1
      setState('playing')
      onPlayingRef.current?.()
    }
    const handleTimeUpdate = () => {
      positionsBySource.set(source, audio.currentTime)
      setCurrentTime(audio.currentTime)
      if (Number.isFinite(audio.duration)) {
        setDuration(audio.duration)
      }
    }
    const handleWaiting = () => setState('loading')

    audio.addEventListener('canplay', handleCanPlay)
    audio.addEventListener('loadedmetadata', handleLoadedMetadata)
    audio.addEventListener('ended', handleEnded)
    audio.addEventListener('error', handleError)
    audio.addEventListener('pause', handlePause)
    audio.addEventListener('playing', handlePlaying)
    audio.addEventListener('timeupdate', handleTimeUpdate)
    if (loadSource === undefined) {
      audio.addEventListener('waiting', handleWaiting)
    }
    audio.load()

    return () => {
      audio.removeEventListener('canplay', handleCanPlay)
      audio.removeEventListener('loadedmetadata', handleLoadedMetadata)
      audio.removeEventListener('ended', handleEnded)
      audio.removeEventListener('error', handleError)
      audio.removeEventListener('pause', handlePause)
      audio.removeEventListener('playing', handlePlaying)
      audio.removeEventListener('timeupdate', handleTimeUpdate)
      audio.removeEventListener('waiting', handleWaiting)
      audio.pause()
      for (const sourceElement of audio.querySelectorAll('source')) {
        sourceElement.removeAttribute('src')
      }
      audio.load()
    }
  }, [loadSource, playableSource, source])

  useEffect(() => {
    slowRef.current = slow
    if (audioRef.current !== null) {
      audioRef.current.playbackRate = slow ? SLOW_RATE : 1
    }
  }, [slow, playableSource])

  const play = async () => {
    const audio = audioRef.current

    if (audio === null) {
      setState('unavailable')
      return
    }

    audio.playbackRate = slow ? SLOW_RATE : 1
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

    positionsBySource.delete(source)
    audio.currentTime = 0
    setCurrentTime(0)
    await play()
  }

  const stop = () => {
    const audio = audioRef.current

    if (audio === null) {
      return
    }

    audio.pause()
    audio.currentTime = 0
    positionsBySource.delete(source)
    setCurrentTime(0)
    setState('ready')
    onPauseRef.current?.()
  }

  const seek = (nextTime: number) => {
    const audio = audioRef.current

    if (audio === null) {
      return
    }

    audio.currentTime = nextTime
    positionsBySource.set(source, nextTime)
    setCurrentTime(nextTime)
  }

  const showStop = state === 'playing' || state === 'paused'
  const showSeek = duration > 0 && playableSource !== null

  return (
    <div className="model-audio-player">
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
      <div className="model-audio-player__toolbar">
        <AudioControls
          labels={labels}
          onRequestPause={() => audioRef.current?.pause()}
          onRequestPlay={() => void play()}
          onRequestReplay={() => void replay()}
          state={visibleState}
        />
        {showStop ? (
          <button
            className="model-audio-player__stop ge-button ge-button--ghost"
            onClick={stop}
            type="button"
          >
            {stopLabel}
          </button>
        ) : null}
        <button
          aria-pressed={slow}
          className="model-audio-player__slow ge-button ge-button--ghost"
          onClick={() => {
            setSlow((current) => {
              const next = !current
              if (audioRef.current !== null) {
                audioRef.current.playbackRate = next ? SLOW_RATE : 1
              }
              return next
            })
          }}
          type="button"
        >
          {slowLabel}
        </button>
      </div>
      {showSeek ? (
        <input
          aria-label={seekLabel}
          className="model-audio-player__seek"
          max={duration}
          min={0}
          onChange={(event) => {
            seek(Number(event.currentTarget.value))
          }}
          step={0.1}
          type="range"
          value={Math.min(currentTime, duration)}
        />
      ) : null}
    </div>
  )
}
