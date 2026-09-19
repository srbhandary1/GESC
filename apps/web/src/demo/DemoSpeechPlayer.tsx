import {
  AudioControls,
  type AudioControlLabels,
  type AudioControlState,
} from '@global-english/design-system'
import { useEffect, useRef, useState } from 'react'
import {
  browserSpeechSynthesisAvailable,
  decodeDemoSpeechUrl,
} from './browserSpeech'
import { speakDemoText, stopDemoSpeech } from './demoTts'

export interface DemoSpeechPlayerProps {
  readonly labels: AudioControlLabels
  readonly onEnded?: () => void
  readonly onPause?: () => void
  readonly onPlaying?: () => void
  readonly source: string
}

/**
 * Demo-only model-audio player. Prefers cloud neural TTS via `/demo/tts`,
 * then falls back to on-device browser speech for `demo-speech:` URLs.
 */
export function DemoSpeechPlayer({
  labels,
  onEnded,
  onPause,
  onPlaying,
  source,
}: DemoSpeechPlayerProps) {
  const utterance = decodeDemoSpeechUrl(source)
  const [state, setState] = useState<AudioControlState>(
    utterance === null ? 'unavailable' : 'ready',
  )
  const playing = useRef(false)

  useEffect(() => {
    return () => {
      playing.current = false
      stopDemoSpeech()
    }
  }, [source])

  if (utterance === null) {
    return (
      <AudioControls
        hideStatus
        labels={labels}
        onRequestPause={() => undefined}
        onRequestPlay={() => undefined}
        onRequestReplay={() => undefined}
        state="unavailable"
      />
    )
  }

  const play = async () => {
    playing.current = true
    setState('playing')
    onPlaying?.()

    try {
      await speakDemoText(utterance.text, utterance.locale)
      if (playing.current) {
        setState('ended')
        onEnded?.()
      }
    } catch {
      if (playing.current) {
        setState(
          browserSpeechSynthesisAvailable() ? 'error' : 'unavailable',
        )
      }
    } finally {
      playing.current = false
    }
  }

  return (
    <div data-demo-speech="cloud-first-tts">
      <p className="demo-speech-caption" lang={utterance.locale}>
        {utterance.text}
      </p>
      <AudioControls
        hideStatus
        labels={labels}
        onRequestPause={() => {
          playing.current = false
          stopDemoSpeech()
          setState('paused')
          onPause?.()
        }}
        onRequestPlay={() => void play()}
        onRequestReplay={() => void play()}
        state={state}
      />
    </div>
  )
}
