import { phase1Levels, type Phase1Level } from './phase1Fixtures'

export const PHASE1_LEVEL_AUDIO_PUBLIC_DIR = 'demo/phase1/audio'
export const PHASE1_LEVEL_AUDIO_REPO_DIR = 'apps/web/public/demo/phase1/audio'

export const PHASE1_LEVEL_AUDIO_EXTENSIONS = [
  'mp3',
  'wav',
  'm4a',
  'ogg',
  'webm',
] as const

export type Phase1LevelAudioExtension =
  (typeof PHASE1_LEVEL_AUDIO_EXTENSIONS)[number]

export const PHASE1_LEVEL_AUDIO_MIME = {
  mp3: 'audio/mpeg',
  wav: 'audio/wav',
  m4a: 'audio/mp4',
  ogg: 'audio/ogg',
  webm: 'audio/webm',
} as const satisfies Record<Phase1LevelAudioExtension, string>

export const PHASE1_PRIVATE_LEVEL_MIN = 1
export const PHASE1_PRIVATE_LEVEL_MAX = 17

/**
 * Same-origin Sanctum/Passport stream used by the studio / elevate stack.
 * This branch's Laravel does not register the route; the live Real-Studioz
 * host at :8080 does. Pre-1 / Pre-2 have no numeric slot.
 */
export const PHASE1_PRIVATE_INTRODUCTION_AUDIO_PATH =
  /^\/api\/v1\/me\/levels\/([1-9]|1[0-7])\/sessions\/introduction\/audio$/

export interface Phase1AudioManifestLevel {
  readonly id: string
  readonly file: string | null
  readonly mimeType?: string
}

export interface Phase1AudioManifest {
  readonly schemaVersion: 1
  readonly kind: 'phase1-level-audio-lessons'
  readonly teacher: 'Swathi'
  readonly basePath: string
  readonly acceptedExtensions: readonly Phase1LevelAudioExtension[]
  readonly levels: readonly Phase1AudioManifestLevel[]
}

export interface Phase1AuthoredAudio {
  readonly levelId: string
  readonly url: string
  readonly mimeType: string
  readonly file: string
  readonly source: 'manifest' | 'probe' | 'private-api'
}

export type Phase1AuthoredAudioLookup =
  | { readonly status: 'ready'; readonly audio: Phase1AuthoredAudio }
  | { readonly status: 'missing' }
  | { readonly status: 'unauthenticated' }

export function asPhase1AuthoredAudioLookup(
  value: Phase1AuthoredAudioLookup | Phase1AuthoredAudio | null,
): Phase1AuthoredAudioLookup {
  if (value === null) {
    return { status: 'missing' }
  }

  if ('status' in value) {
    return value
  }

  return { status: 'ready', audio: value }
}

export function phase1LevelAudioIds(): readonly string[] {
  return phase1Levels.map((level) => level.id)
}

export function defaultPhase1AudioManifest(): Phase1AudioManifest {
  return {
    schemaVersion: 1,
    kind: 'phase1-level-audio-lessons',
    teacher: 'Swathi',
    basePath: `/${PHASE1_LEVEL_AUDIO_PUBLIC_DIR}/`,
    acceptedExtensions: PHASE1_LEVEL_AUDIO_EXTENSIONS,
    levels: phase1LevelAudioIds().map((id) => ({ id, file: null })),
  }
}

export function phase1LevelAudioPublicUrl(file: string): string {
  const base = import.meta.env.BASE_URL || '/'
  const prefix = base.endsWith('/') ? base : `${base}/`

  return `${prefix}${PHASE1_LEVEL_AUDIO_PUBLIC_DIR}/${file}`
}

export function phase1LevelAudioRepoPath(
  levelId: string,
  extension: Phase1LevelAudioExtension = 'mp3',
): string {
  return `${PHASE1_LEVEL_AUDIO_REPO_DIR}/${levelId}.${extension}`
}

export function mimeTypeForAudioFile(file: string): string | null {
  const extension = file.split('.').pop()?.toLowerCase()
  if (
    extension === undefined ||
    !PHASE1_LEVEL_AUDIO_EXTENSIONS.includes(
      extension as Phase1LevelAudioExtension,
    )
  ) {
    return null
  }

  return PHASE1_LEVEL_AUDIO_MIME[extension as Phase1LevelAudioExtension]
}

export function phase1LevelPrivateSessionNumber(
  levelId: string,
): number | null {
  const match = /^level-(\d+)$/.exec(levelId)
  if (match === null) {
    return null
  }

  const levelNumber = Number(match[1])
  if (
    !Number.isInteger(levelNumber) ||
    levelNumber < PHASE1_PRIVATE_LEVEL_MIN ||
    levelNumber > PHASE1_PRIVATE_LEVEL_MAX
  ) {
    return null
  }

  return levelNumber
}

export function phase1PrivateIntroductionAudioPath(
  levelNumber: number,
): string {
  return `/api/v1/me/levels/${levelNumber}/sessions/introduction/audio`
}

export function isPhase1PrivateLessonAudioPath(pathname: string): boolean {
  return PHASE1_PRIVATE_INTRODUCTION_AUDIO_PATH.test(pathname)
}

export function phase1PrivateIntroductionAudioUrl(
  levelNumber: number,
): string {
  const configured = import.meta.env.VITE_API_BASE_URL
  if (typeof configured === 'string' && configured.trim() !== '') {
    return `${configured.replace(/\/$/, '')}/me/levels/${levelNumber}/sessions/introduction/audio`
  }

  return phase1PrivateIntroductionAudioPath(levelNumber)
}

async function resourceExists(
  url: string,
  fetchImpl: typeof fetch,
): Promise<boolean> {
  try {
    const head = await fetchImpl(url, { method: 'HEAD' })
    if (head.ok) {
      return true
    }
    if (head.status === 404) {
      return false
    }
  } catch {
    // Some hosts reject HEAD; try GET below.
  }

  try {
    const get = await fetchImpl(url, { method: 'GET' })
    return get.ok
  } catch {
    return false
  }
}

async function loadManifest(
  fetchImpl: typeof fetch,
): Promise<Phase1AudioManifest> {
  const fallback = defaultPhase1AudioManifest()

  try {
    const response = await fetchImpl(phase1LevelAudioPublicUrl('manifest.json'))
    if (!response.ok) {
      return fallback
    }

    const parsed = (await response.json()) as Phase1AudioManifest
    if (parsed.kind !== 'phase1-level-audio-lessons' || !Array.isArray(parsed.levels)) {
      return fallback
    }

    return parsed
  } catch {
    return fallback
  }
}

export async function resolveLocalPhase1AuthoredAudio(
  levelId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<Phase1AuthoredAudio | null> {
  const manifest = await loadManifest(fetchImpl)
  const registered = manifest.levels.find((level) => level.id === levelId)
  if (registered?.file) {
    const mimeType =
      registered.mimeType ?? mimeTypeForAudioFile(registered.file)
    if (mimeType !== null) {
      return {
        levelId,
        url: phase1LevelAudioPublicUrl(registered.file),
        mimeType,
        file: registered.file,
        source: 'manifest',
      }
    }
  }

  for (const extension of PHASE1_LEVEL_AUDIO_EXTENSIONS) {
    const file = `${levelId}.${extension}`
    const url = phase1LevelAudioPublicUrl(file)
    if (await resourceExists(url, fetchImpl)) {
      return {
        levelId,
        url,
        mimeType: PHASE1_LEVEL_AUDIO_MIME[extension],
        file,
        source: 'probe',
      }
    }
  }

  return null
}

export async function fetchPrivateLevelIntroductionAudio(
  levelId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<Phase1AuthoredAudioLookup> {
  const levelNumber = phase1LevelPrivateSessionNumber(levelId)
  if (levelNumber === null) {
    return { status: 'missing' }
  }

  const path = phase1PrivateIntroductionAudioPath(levelNumber)
  const url = phase1PrivateIntroductionAudioUrl(levelNumber)

  try {
    const response = await fetchImpl(url, {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'audio/*,application/problem+json,application/json',
      },
    })

    if (response.status === 401 || response.status === 403) {
      return { status: 'unauthenticated' }
    }

    if (!response.ok) {
      return { status: 'missing' }
    }

    const contentType = response.headers.get('content-type') ?? ''
    const mimeType = contentType.split(';')[0]?.trim() ?? ''
    if (!mimeType.startsWith('audio/')) {
      return { status: 'missing' }
    }

    const blob = await response.blob()
    if (blob.size === 0) {
      return { status: 'missing' }
    }

    return {
      status: 'ready',
      audio: {
        levelId,
        url: URL.createObjectURL(blob),
        mimeType,
        file: path,
        source: 'private-api',
      },
    }
  } catch {
    return { status: 'missing' }
  }
}

export async function resolvePhase1AuthoredAudioLookup(
  levelId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<Phase1AuthoredAudioLookup> {
  const local = await resolveLocalPhase1AuthoredAudio(levelId, fetchImpl)
  if (local !== null) {
    return { status: 'ready', audio: local }
  }

  return fetchPrivateLevelIntroductionAudio(levelId, fetchImpl)
}

export async function resolvePhase1AuthoredAudio(
  levelId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<Phase1AuthoredAudio | null> {
  const lookup = await resolvePhase1AuthoredAudioLookup(levelId, fetchImpl)

  return lookup.status === 'ready' ? lookup.audio : null
}

export function phase1AudioCoverageTable(
  resolved: Readonly<Record<string, string | null>>,
): readonly { readonly levelId: string; readonly file: string | 'MISSING' }[] {
  return phase1LevelAudioIds().map((levelId) => ({
    levelId,
    file: resolved[levelId] ?? 'MISSING',
  }))
}

export function phase1LevelAudioLabel(level: Phase1Level): string {
  return level.kind === 'pre' ? `Pre-${level.number}` : `Level ${level.number}`
}
