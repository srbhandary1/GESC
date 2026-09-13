import { normalizeMorphName } from './arkitVisemeWeights'
import type { ArkitMorphWeights } from './arkitVisemeWeights'
import type { Mesh } from 'three'

export function applyMorphWeights(
  meshes: readonly Mesh[],
  weights: ArkitMorphWeights,
  lerp: number,
): void {
  for (const mesh of meshes) {
    const dictionary = mesh.morphTargetDictionary
    const influences = mesh.morphTargetInfluences
    if (dictionary === undefined || influences === undefined) {
      continue
    }

    const desired = new Array(influences.length).fill(0)
    for (const [name, index] of Object.entries(dictionary)) {
      const key = normalizeMorphName(name)
      for (const [targetName, value] of Object.entries(weights)) {
        if (normalizeMorphName(targetName) === key) {
          desired[index] = value
        }
      }
    }

    for (let index = 0; index < influences.length; index += 1) {
      const current = influences[index] ?? 0
      influences[index] = current + ((desired[index] ?? 0) - current) * lerp
    }
  }
}
