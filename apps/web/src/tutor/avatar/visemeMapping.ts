import type { AvatarVisemeTimeline } from '@global-english/contracts'
import type { VisemeMouthShape } from './types'

const VISEME_SHAPES: readonly VisemeMouthShape[] = [
  { jawOpen: 0.02, lipWide: 0.35, lipRound: 0.35 },
  { jawOpen: 0.55, lipWide: 0.72, lipRound: 0.28 },
  { jawOpen: 0.62, lipWide: 0.55, lipRound: 0.22 },
  { jawOpen: 0.58, lipWide: 0.42, lipRound: 0.48 },
  { jawOpen: 0.48, lipWide: 0.62, lipRound: 0.32 },
  { jawOpen: 0.42, lipWide: 0.48, lipRound: 0.38 },
  { jawOpen: 0.38, lipWide: 0.35, lipRound: 0.55 },
  { jawOpen: 0.34, lipWide: 0.28, lipRound: 0.72 },
  { jawOpen: 0.52, lipWide: 0.32, lipRound: 0.68 },
  { jawOpen: 0.56, lipWide: 0.58, lipRound: 0.42 },
  { jawOpen: 0.54, lipWide: 0.46, lipRound: 0.58 },
  { jawOpen: 0.58, lipWide: 0.78, lipRound: 0.24 },
  { jawOpen: 0.18, lipWide: 0.42, lipRound: 0.36 },
  { jawOpen: 0.22, lipWide: 0.38, lipRound: 0.42 },
  { jawOpen: 0.24, lipWide: 0.34, lipRound: 0.44 },
  { jawOpen: 0.16, lipWide: 0.52, lipRound: 0.28 },
  { jawOpen: 0.2, lipWide: 0.48, lipRound: 0.34 },
  { jawOpen: 0.14, lipWide: 0.44, lipRound: 0.36 },
  { jawOpen: 0.12, lipWide: 0.58, lipRound: 0.24 },
  { jawOpen: 0.18, lipWide: 0.46, lipRound: 0.32 },
  { jawOpen: 0.22, lipWide: 0.4, lipRound: 0.36 },
  { jawOpen: 0.08, lipWide: 0.34, lipRound: 0.62 },
]

export function visemeMouthShape(visemeId: number): VisemeMouthShape {
  const clamped = Math.max(0, Math.min(21, Math.round(visemeId)))
  return VISEME_SHAPES[clamped] ?? VISEME_SHAPES[0]!
}

export function resolveVisemeAtTime(
  timeline: AvatarVisemeTimeline,
  timeMs: number,
): number {
  const frames = timeline.frames
  if (frames.length === 0) {
    return 0
  }

  let active = frames[0]!.viseme_id
  for (const frame of frames) {
    if (frame.offset_ms > timeMs) {
      break
    }

    active = frame.viseme_id
  }

  return active
}

export function interpolateMouthShape(
  from: VisemeMouthShape,
  to: VisemeMouthShape,
  alpha: number,
): VisemeMouthShape {
  const t = Math.max(0, Math.min(1, alpha))
  return {
    jawOpen: from.jawOpen + (to.jawOpen - from.jawOpen) * t,
    lipWide: from.lipWide + (to.lipWide - from.lipWide) * t,
    lipRound: from.lipRound + (to.lipRound - from.lipRound) * t,
  }
}
