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
  readonly source: string
}

/**
 * Demo-only model-audio player. Prefers Azure neural TTS via `/demo/tts`,
 * then falls back to on-device browser speech for `demo-speech:` URLs.
 */
export function DemoSpeechPlayer({
  labels,
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

    try {
      await speakDemoText(utterance.text, utterance.locale)
      if (playing.current) {
        setState('ended')
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
    <div data-demo-speech="azure-first-tts">
      <p className="demo-speech-caption" lang={utterance.locale}>
        {utterance.text}
      </p>
      <AudioControls
        labels={labels}
        onRequestPause={() => {
          playing.current = false
          stopDemoSpeech()
          setState('paused')
        }}
        onRequestPlay={() => void play()}
        onRequestReplay={() => void play()}
        state={state}
      />
    </div>
  )
}
