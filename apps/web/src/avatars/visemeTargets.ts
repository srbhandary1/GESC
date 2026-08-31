import { tutorGlbUrl } from '../config/avatarConfig'

export const GLB_VISEME_TARGETS = [
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
] as const

export type GlbVisemeTarget = (typeof GLB_VISEME_TARGETS)[number]

const VISEME_ALIASES: Readonly<Record<string, GlbVisemeTarget>> = {
  viseme_sil: 'viseme_sil',
  sil: 'viseme_sil',
  rest: 'viseme_sil',
  idle: 'viseme_sil',
  silence: 'viseme_sil',
  '0': 'viseme_sil',
  viseme_aa: 'viseme_aa',
  aa: 'viseme_aa',
  a: 'viseme_aa',
  ah: 'viseme_aa',
  ae: 'viseme_aa',
  open: 'viseme_aa',
  wide: 'viseme_aa',
  '2': 'viseme_aa',
  '4': 'viseme_aa',
  viseme_e: 'viseme_E',
  e: 'viseme_E',
  eh: 'viseme_E',
  ee: 'viseme_E',
  ei: 'viseme_E',
  smile: 'viseme_E',
  '3': 'viseme_E',
  viseme_i: 'viseme_I',
  i: 'viseme_I',
  ih: 'viseme_I',
  y: 'viseme_I',
  '6': 'viseme_I',
  viseme_o: 'viseme_O',
  o: 'viseme_O',
  oh: 'viseme_O',
  ow: 'viseme_O',
  ou: 'viseme_O',
  round: 'viseme_O',
  '8': 'viseme_O',
  viseme_u: 'viseme_U',
  u: 'viseme_U',
  uh: 'viseme_U',
  ooh: 'viseme_U',
  '7': 'viseme_U',
  viseme_pp: 'viseme_PP',
  pp: 'viseme_PP',
  p: 'viseme_PP',
  b: 'viseme_PP',
  m: 'viseme_PP',
  mm: 'viseme_PP',
  bb: 'viseme_PP',
  closed: 'viseme_PP',
  viseme_ff: 'viseme_FF',
  ff: 'viseme_FF',
  f: 'viseme_FF',
  v: 'viseme_FF',
  viseme_th: 'viseme_TH',
  th: 'viseme_TH',
  viseme_dd: 'viseme_DD',
  dd: 'viseme_DD',
  d: 'viseme_DD',
  t: 'viseme_DD',
  slightly_open: 'viseme_DD',
  jawopen: 'jawOpen',
  jaw_open: 'jawOpen',
  jaw: 'jawOpen',
}

export function mouthShapeToVisemeTarget(shape: string): GlbVisemeTarget {
  const normalized = shape.trim().toLowerCase()
  return VISEME_ALIASES[normalized] ?? 'viseme_sil'
}

export function tutorGltfUrl(gender: 'female' | 'male'): string {
  return tutorGlbUrl(gender)
}

export function webglAvailable(): boolean {
  if (typeof document === 'undefined') {
    return false
  }
  if (
    typeof WebGLRenderingContext === 'undefined' &&
    typeof WebGL2RenderingContext === 'undefined'
  ) {
    return false
  }

  try {
    const canvas = document.createElement('canvas')
    return (
      canvas.getContext('webgl2') !== null || canvas.getContext('webgl') !== null
    )
  } catch {
    return false
  }
}
