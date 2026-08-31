import { useEffect, useRef } from 'react'
import type { AudioRecordingSession } from './BrowserAudioRecorder'

const BAR_COUNT = 24

interface LiveLevelMeterProps {
  readonly session: AudioRecordingSession | null
}

export function LiveLevelMeter({ session }: LiveLevelMeterProps) {
  const canvasRef = useRef<HTMLCanvasElement | null>(null)
  const barsRef = useRef<number[]>(Array.from({ length: BAR_COUNT }, () => 0))

  useEffect(() => {
    if (session?.subscribeLevels === undefined) {
      return
    }

    const canvas = canvasRef.current
    if (canvas === null) {
      return
    }

    const unsubscribe = session.subscribeLevels((rms) => {
      const next = barsRef.current.slice(1)
      next.push(Math.min(1, rms * 4))
      barsRef.current = next
      drawLevelBars(canvas, next)
    })

    return unsubscribe
  }, [session])

  return (
    <canvas
      aria-hidden="true"
      className="learner-recording__waveform"
      height={48}
      ref={canvasRef}
      width={240}
    />
  )
}

function drawLevelBars(canvas: HTMLCanvasElement, bars: readonly number[]): void {
  const context = canvas.getContext('2d')
  if (context === null) {
    return
  }

  const width = canvas.width
  const height = canvas.height
  context.clearRect(0, 0, width, height)
  const gap = 2
  const barWidth = Math.max(2, (width - gap * (bars.length - 1)) / bars.length)

  for (let index = 0; index < bars.length; index += 1) {
    const value = bars[index] ?? 0
    const barHeight = Math.max(4, value * height)
    const x = index * (barWidth + gap)
    const y = height - barHeight
    context.fillStyle = 'rgb(46 230 255)'
    context.fillRect(x, y, barWidth, barHeight)
  }
}
