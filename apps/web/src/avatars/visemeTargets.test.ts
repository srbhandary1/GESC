import { describe, expect, it } from 'vitest'
import {
  GLB_VISEME_TARGETS,
  mouthShapeToVisemeTarget,
  webglAvailable,
} from './visemeTargets'

describe('mouthShapeToVisemeTarget', () => {
  it('maps canonical letters and Azure envelope labels onto the GLB morph names', () => {
    expect(mouthShapeToVisemeTarget('A')).toBe('viseme_aa')
    expect(mouthShapeToVisemeTarget('open')).toBe('viseme_aa')
    expect(mouthShapeToVisemeTarget('E')).toBe('viseme_E')
    expect(mouthShapeToVisemeTarget('closed')).toBe('viseme_PP')
    expect(mouthShapeToVisemeTarget('slightly_open')).toBe('viseme_DD')
    expect(mouthShapeToVisemeTarget('viseme_TH')).toBe('viseme_TH')
    expect(mouthShapeToVisemeTarget('jawOpen')).toBe('jawOpen')
    expect(mouthShapeToVisemeTarget('unknown-shape')).toBe('viseme_sil')
  })

  it('keeps the required morph-target name set stable for streaming metadata', () => {
    expect(GLB_VISEME_TARGETS).toEqual([
      'viseme_sil',
      'viseme_aa',
      'viseme_E',
      'viseme_I',
      'viseme_O',
      'viseme_U',
      'viseme_PP',
      'viseme_FF',
      'viseme_TH',
      'viseme_DD',
      'jawOpen',
    ])
  })

  it('reports no WebGL in the unit-test jsdom canvas', () => {
    expect(webglAvailable()).toBe(false)
  })
})
