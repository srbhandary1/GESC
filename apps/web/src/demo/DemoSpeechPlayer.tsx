import {
  AudioControls,
  type AudioControlLabels,
  type AudioControlState,
} from '@global-english/design-system'
import { useEffect, useRef, useState } from 'react'
import {
  browserSpeechSynthesisAvailable,
  decodeDemoSpeechUrl,
  speakWithBrowserSpeech,
  stopBrowserSpeech,
} from './browserSpeech'

export interface DemoSpeechPlayerProps {
  readonly labels: AudioControlLabels
  readonly source: string
}

/**
 * Demo-only model-audio player that uses the browser speechSynthesis API
 * instead of a WAV file. Activated only for `demo-speech:` URLs.
 */
export function DemoSpeechPlayer({
  labels,
  source,
}: DemoSpeechPlayerProps) {
  const utterance = decodeDemoSpeechUrl(source)
  const [state, setState] = useState<AudioControlState>(
    utterance === null || !browserSpeechSynthesisAvailable()
      ? 'unavailable'
      : 'ready',
  )
  const playing = useRef(false)

  useEffect(() => {
    return () => {
      playing.current = false
      stopBrowserSpeech()
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
    if (!browserSpeechSynthesisAvailable()) {
      setState('unavailable')
      return
    }

    playing.current = true
    setState('playing')

    try {
      await speakWithBrowserSpeech(utterance.text, utterance.locale)
      if (playing.current) {
        setState('ended')
      }
    } catch {
      if (playing.current) {
        setState('error')
      }
    } finally {
      playing.current = false
    }
  }

  return (
    <div data-demo-speech="browser-tts">
      <p className="demo-speech-caption" lang={utterance.locale}>
        {utterance.text}
      </p>
      <AudioControls
        labels={labels}
        onRequestPause={() => {
          playing.current = false
          stopBrowserSpeech()
          setState('paused')
        }}
        onRequestPlay={() => void play()}
        onRequestReplay={() => void play()}
        state={state}
      />
    </div>
  )
}
