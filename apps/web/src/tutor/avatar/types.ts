import type { AvatarVisemeTimeline } from '@global-english/contracts'

export type { AvatarVisemeTimeline, AvatarVisemeFrame } from '@global-english/contracts'

export interface AvatarPlaybackState {
  readonly timeline: AvatarVisemeTimeline
  readonly timeMs: number
}

export interface VisemeMouthShape {
  readonly jawOpen: number
  readonly lipWide: number
  readonly lipRound: number
}
