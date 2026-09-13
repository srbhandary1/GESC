/**
 * Phase-1 Audio Lessons: signed-in private stream first for Levels 1–17,
 * then same-origin drop-in files. Public `/storage/lesson-audio/{level}.mp3`
 * is never used — those objects are private on S3-compatible disks.
 *
 * Resolution order (Levels 1–17):
 * 1. Authenticated sessions index → allowlisted `audio_stream_path`
 *    (prefer `introduction`) → authorized GET → blob URL for `<audio src>`
 * 2. Hardcoded introduction path when the index has no allowlisted path
 * 3. Same-origin drop-in `demo/phase1/audio/level-N.mp3` if present
 * 4. Unauthenticated (401/403) or missing
 *
 * Pre-1 / Pre-2 have no `/me/levels` slot: drop-in only, then missing.
 */
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

export const PHASE1_PRIVATE_SESSION_AUDIO_PATH =
  /^\/api\/v1\/me\/levels\/([1-9]|1[0-7])\/sessions\/(?:introduction|practice|conversation|interaction)\/audio$/

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
  return PHASE1_PRIVATE_SESSION_AUDIO_PATH.test(pathname)
}

export function phase1ApiUrl(path: string): string {
  const configured = import.meta.env.VITE_API_BASE_URL
  if (typeof configured === 'string' && configured.trim() !== '') {
    const prefix = configured.replace(/\/$/, '')
    const suffix = path.startsWith('/api/v1')
      ? path.slice('/api/v1'.length)
      : path

    return `${prefix}${suffix.startsWith('/') ? suffix : `/${suffix}`}`
  }

  return path
}

export function phase1PrivateIntroductionAudioUrl(
  levelNumber: number,
): string {
  return phase1ApiUrl(phase1PrivateIntroductionAudioPath(levelNumber))
}

export function phase1SessionsIndexPath(levelNumber: number): string {
  return `/api/v1/me/levels/${levelNumber}/sessions`
}

export interface Phase1SessionAudioHint {
  readonly part: string
  readonly audio_stream_path: string
  readonly media_asset_id?: string
}

export function allowlistedPhase1AudioStreamPath(
  candidate: string | null | undefined,
  expectedLevel?: number,
): string | null {
  if (candidate === null || candidate === undefined || candidate.trim() === '') {
    return null
  }

  try {
    const parsed =
      candidate.startsWith('http://') || candidate.startsWith('https://')
        ? new URL(candidate)
        : new URL(candidate, 'https://phase1.invalid')
    const pathname = parsed.pathname
    const match = PHASE1_PRIVATE_SESSION_AUDIO_PATH.exec(pathname)

    if (match === null) {
      return null
    }

    if (
      expectedLevel !== undefined &&
      Number(match[1]) !== expectedLevel
    ) {
      return null
    }

    if (
      (candidate.startsWith('http://') || candidate.startsWith('https://')) &&
      typeof globalThis.location?.origin === 'string' &&
      parsed.origin !== globalThis.location.origin
    ) {
      return null
    }

    return pathname
  } catch {
    return null
  }
}

function asRecord(value: unknown): Record<string, unknown> | null {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : null
}

function sessionHintFrom(value: unknown): Phase1SessionAudioHint | null {
  const record = asRecord(value)
  if (record === null) {
    return null
  }

  const path =
    typeof record.audio_stream_path === 'string'
      ? record.audio_stream_path
      : null
  if (path === null || path.trim() === '') {
    return null
  }

  const part =
    typeof record.part === 'string'
      ? record.part
      : typeof record.part_id === 'string'
        ? record.part_id
        : typeof record.id === 'string'
          ? record.id
          : ''
  const mediaAssetId =
    typeof record.media_asset_id === 'string'
      ? record.media_asset_id
      : undefined

  return {
    part,
    audio_stream_path: path,
    media_asset_id: mediaAssetId,
  }
}

export function parsePhase1SessionsIndex(
  payload: unknown,
): readonly Phase1SessionAudioHint[] {
  if (Array.isArray(payload)) {
    return payload
      .map(sessionHintFrom)
      .filter((hint): hint is Phase1SessionAudioHint => hint !== null)
  }

  const record = asRecord(payload)
  if (record === null) {
    return []
  }

  const collections = [record.data, record.sessions, record.parts]
  for (const collection of collections) {
    if (Array.isArray(collection)) {
      return collection
        .map(sessionHintFrom)
        .filter((hint): hint is Phase1SessionAudioHint => hint !== null)
    }

    const nested = asRecord(collection)
    if (nested !== null && Array.isArray(nested.data)) {
      return nested.data
        .map(sessionHintFrom)
        .filter((hint): hint is Phase1SessionAudioHint => hint !== null)
    }
  }

  const hints: Phase1SessionAudioHint[] = []
  for (const part of [
    'introduction',
    'practice',
    'conversation',
    'interaction',
  ] as const) {
    const hint = sessionHintFrom(record[part])
    if (hint !== null) {
      hints.push(hint.part === '' ? { ...hint, part } : hint)
    }
  }

  return hints
}

export function preferIntroductionSession(
  sessions: readonly Phase1SessionAudioHint[],
): Phase1SessionAudioHint | null {
  return (
    sessions.find((session) => session.part === 'introduction') ??
    sessions[0] ??
    null
  )
}

export function createPhase1AuthorizedFetch(
  fetchImpl: typeof fetch = fetch,
  authorize?: (headers: Headers) => void,
): typeof fetch {
  return ((input: RequestInfo | URL, init?: RequestInit) => {
    const headers = new Headers(init?.headers)
    authorize?.(headers)

    return fetchImpl(input, {
      ...init,
      credentials: 'include',
      headers,
    })
  }) as typeof fetch
}

function audioMimeFrom(response: Response): string | null {
  const mimeType =
    response.headers.get('content-type')?.split(';')[0]?.trim() ?? ''
  return mimeType.startsWith('audio/') ? mimeType : null
}

async function resourceExists(
  url: string,
  fetchImpl: typeof fetch,
): Promise<boolean> {
  try {
    const head = await fetchImpl(url, {
      method: 'HEAD',
      headers: { Accept: 'audio/*' },
    })
    if (audioMimeFrom(head) !== null) {
      return true
    }
    if (head.status === 404) {
      return false
    }
    const declared = head.headers.get('content-type')
    if (head.ok && declared && !declared.trim().toLowerCase().startsWith('audio/')) {
      return false
    }
  } catch {
    // Some hosts reject HEAD; try GET below.
  }

  try {
    const get = await fetchImpl(url, {
      method: 'GET',
      headers: { Accept: 'audio/*' },
    })
    return audioMimeFrom(get) !== null
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

type PrivateStreamResolution =
  | { readonly kind: 'path'; readonly path: string }
  | { readonly kind: 'unauthenticated' }
  | { readonly kind: 'missing' }

/**
 * Read `audio_stream_path` from the signed-in sessions index. Rejects any
 * path that is not the allowlisted `/me/levels/{1-17}/sessions/{part}/audio`.
 */
async function resolveAllowlistedStreamPath(
  levelNumber: number,
  fetchImpl: typeof fetch,
): Promise<PrivateStreamResolution> {
  let response: Response

  try {
    response = await fetchImpl(phase1ApiUrl(phase1SessionsIndexPath(levelNumber)), {
      method: 'GET',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })
  } catch {
    return { kind: 'missing' }
  }

  if (response.status === 401 || response.status === 403) {
    return { kind: 'unauthenticated' }
  }

  if (!response.ok) {
    return { kind: 'missing' }
  }

  let payload: unknown
  try {
    payload = await response.json()
  } catch {
    return { kind: 'missing' }
  }

  const preferred = preferIntroductionSession(parsePhase1SessionsIndex(payload))
  const path = allowlistedPhase1AudioStreamPath(
    preferred?.audio_stream_path,
    levelNumber,
  )

  if (path === null) {
    return { kind: 'missing' }
  }

  return { kind: 'path', path }
}

/**
 * Fetch the private lesson-audio stream with the same credentials the SPA
 * already uses for `/api/v1/me/*`. The response is copied into a blob URL
 * so `<audio src>` does not need to re-send cookies (or Bearer) on every
 * range request.
 */
export async function fetchPrivateLessonAudio(
  levelId: string,
  path: string,
  fetchImpl: typeof fetch = fetch,
): Promise<Phase1AuthoredAudioLookup> {
  try {
    const response = await fetchImpl(phase1ApiUrl(path), {
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

export async function fetchPrivateLevelIntroductionAudio(
  levelId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<Phase1AuthoredAudioLookup> {
  const levelNumber = phase1LevelPrivateSessionNumber(levelId)
  if (levelNumber === null) {
    return { status: 'missing' }
  }

  return fetchPrivateLessonAudio(
    levelId,
    phase1PrivateIntroductionAudioPath(levelNumber),
    fetchImpl,
  )
}

async function localOr(
  levelId: string,
  fetchImpl: typeof fetch,
  fallback: Phase1AuthoredAudioLookup,
): Promise<Phase1AuthoredAudioLookup> {
  const local = await resolveLocalPhase1AuthoredAudio(levelId, fetchImpl)
  return local === null ? fallback : { status: 'ready', audio: local }
}

export async function resolvePhase1AuthoredAudioLookup(
  levelId: string,
  fetchImpl: typeof fetch = fetch,
): Promise<Phase1AuthoredAudioLookup> {
  const levelNumber = phase1LevelPrivateSessionNumber(levelId)
  if (levelNumber === null) {
    return localOr(levelId, fetchImpl, { status: 'missing' })
  }

  const stream = await resolveAllowlistedStreamPath(levelNumber, fetchImpl)
  if (stream.kind === 'unauthenticated') {
    return localOr(levelId, fetchImpl, { status: 'unauthenticated' })
  }

  const path =
    stream.kind === 'path'
      ? stream.path
      : phase1PrivateIntroductionAudioPath(levelNumber)
  const remote = await fetchPrivateLessonAudio(levelId, path, fetchImpl)
  if (remote.status === 'ready') {
    return remote
  }

  if (remote.status === 'unauthenticated') {
    return localOr(levelId, fetchImpl, { status: 'unauthenticated' })
  }

  return localOr(levelId, fetchImpl, { status: 'missing' })
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
