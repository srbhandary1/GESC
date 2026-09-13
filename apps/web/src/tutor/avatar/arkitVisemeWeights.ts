export type ArkitMorphWeights = Readonly<Record<string, number>>

const VISEME_ARKIT: readonly ArkitMorphWeights[] = [
  {},
  { jawOpen: 0.42, mouthSmileLeft: 0.18, mouthSmileRight: 0.18 },
  { jawOpen: 0.72 },
  { jawOpen: 0.48, mouthFunnel: 0.42 },
  { jawOpen: 0.36, mouthSmileLeft: 0.12, mouthSmileRight: 0.12 },
  { jawOpen: 0.32, mouthPucker: 0.12 },
  { jawOpen: 0.16, mouthSmileLeft: 0.58, mouthSmileRight: 0.58 },
  { mouthFunnel: 0.32, mouthPucker: 0.72 },
  { jawOpen: 0.38, mouthFunnel: 0.68 },
  { jawOpen: 0.55, mouthFunnel: 0.38 },
  { mouthFunnel: 0.4, mouthSmileLeft: 0.32, mouthSmileRight: 0.32 },
  { jawOpen: 0.46, mouthSmileLeft: 0.4, mouthSmileRight: 0.4 },
  { jawOpen: 0.16 },
  { jawOpen: 0.14, mouthFunnel: 0.28 },
  { jawOpen: 0.22 },
  { mouthPressLeft: 0.38, mouthPressRight: 0.38, mouthStretchLeft: 0.32, mouthStretchRight: 0.32 },
  { mouthFunnel: 0.52, mouthPucker: 0.34 },
  { jawOpen: 0.22 },
  { mouthLowerDownLeft: 0.46, mouthLowerDownRight: 0.46, mouthPressLeft: 0.22, mouthPressRight: 0.22 },
  { jawOpen: 0.24 },
  { jawOpen: 0.18 },
  { mouthClose: 0.22, mouthPucker: 0.58 },
]

export function arkitWeightsForViseme(visemeId: number): ArkitMorphWeights {
  const clamped = Math.max(0, Math.min(21, Math.round(visemeId)))
  return VISEME_ARKIT[clamped] ?? {}
}

export function normalizeMorphName(name: string): string {
  return name.toLowerCase().replace(/[^a-z0-9]/gu, '')
}
