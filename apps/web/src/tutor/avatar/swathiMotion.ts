import type { TutorMood } from '../TutorPresence'
import type { VisemeMouthShape } from './types'

export const SWATHI_LOOK = 'stylized-teal-auburn-glasses' as const

export interface SwathiSpeakingMotion {
  readonly jawOpen: number
  readonly lipWide: number
  readonly lipRound: number
  readonly rightUpperX: number
  readonly rightUpperZ: number
  readonly rightForearmX: number
  readonly leftUpperX: number
  readonly leftUpperZ: number
  readonly leftForearmX: number
}

export function speakingMotion(
  mood: TutorMood,
  mouth: VisemeMouthShape,
  time: number,
): SwathiSpeakingMotion {
  const talking = mood === 'speaking'
  const listening = mood === 'listening'
  const syllable = talking ? Math.abs(Math.sin(time * 12.8)) ** 1.25 : 0
  const beat = Math.sin(time * (talking ? 2.8 : 1.6))

  return {
    jawOpen: talking
      ? 0.2 + 0.74 * Math.max(syllable, mouth.jawOpen * 0.8)
      : mouth.jawOpen * 0.2,
    lipWide: talking
      ? 0.25 + mouth.lipWide * 0.55 + syllable * 0.35
      : mouth.lipWide * 0.2,
    lipRound: talking
      ? 0.15 + mouth.lipRound * 0.5 + (1 - syllable) * 0.2
      : mouth.lipRound * 0.15,
    rightUpperX: talking
      ? -1.05 + beat * 0.28
      : listening
        ? 0.18 + beat * 0.08
        : 0.12 + beat * 0.12,
    rightUpperZ: talking ? -0.72 + Math.sin(time * 3.1) * 0.18 : -0.22 + beat * 0.08,
    rightForearmX: talking ? 0.55 + Math.sin(time * 3.4) * 0.22 : 0.28 + beat * 0.06,
    leftUpperX: talking ? 0.32 + Math.sin(time * 2.2) * 0.16 : 0.14 + beat * 0.1,
    leftUpperZ: talking ? 0.28 : 0.16,
    leftForearmX: talking ? 0.42 + Math.sin(time * 2.5) * 0.12 : 0.22,
  }
}
