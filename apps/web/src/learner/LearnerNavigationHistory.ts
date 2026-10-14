import type { PrimaryDestination } from '@global-english/design-system'
import {
  FIRST_USE_STEPS,
  type FirstUseStep,
} from './FirstUseOnboardingResume'
import type { SessionPartId } from './LevelSessionsApi'

const HISTORY_KEY = '__globalEnglishLearnerNavigation'
const NAVIGATION_EVENT = 'ge:learner-navigation'
export const ANDROID_BACK_REQUEST_EVENT = 'ge:android-back-request'

export type LearnerAssessmentKind = 'quick-check' | 'checkpoint'

export interface LearnerNavigationTarget {
  readonly destination: PrimaryDestination
  readonly firstUseStep?: FirstUseStep
  readonly levelNumber?: number
  readonly partId?: SessionPartId
  readonly assessment?: Readonly<{
    kind: LearnerAssessmentKind
    phase: 'question' | 'feedback'
    questionIndex: number
    totalQuestions: number
  }>
}

export interface LearnerNavigationState extends LearnerNavigationTarget {
  readonly index: number
  readonly previous: LearnerNavigationTarget | null
  readonly restoreFocusId: string | null
  readonly version: 1
}

type NavigationListener = (state: LearnerNavigationState) => void

function destinationFromHash(hash: string): PrimaryDestination | null {
  const candidate = hash.replace(/^#/u, '')

  if (candidate === 'practice' || candidate === 'speak') {
    return 'speak'
  }
  if (candidate === 'home' || candidate.startsWith('home/')) {
    return 'home'
  }
  if (candidate === 'progress') {
    return 'progress'
  }
  if (candidate === 'learn' || candidate.startsWith('learn/')) {
    return 'learn'
  }
  if (candidate === 'tuition' || candidate === 'one-to-one') {
    return 'tuition'
  }

  return null
}

function targetFromHash(hash: string): LearnerNavigationTarget | null {
  const destination = destinationFromHash(hash)
  if (destination === null) {
    return null
  }
  const candidate = hash.replace(/^#/u, '')
  if (destination === 'home') {
    const match = /^home\/first-use\/([A-Za-z0-9-]+)$/u.exec(candidate)
    const step = match?.[1]
    if (
      step !== undefined &&
      (FIRST_USE_STEPS as readonly string[]).includes(step)
    ) {
      return { destination: 'home', firstUseStep: step as FirstUseStep }
    }

    return { destination: 'home' }
  }

  if (destination !== 'learn') {
    return { destination }
  }

  const segments = hash.replace(/^#/u, '').split('/')
  if (segments.length < 4 || segments[1] !== 'levels') {
    return { destination: 'learn' }
  }
  const levelNumber = Number(segments[2])
  const partId = segments[3]
  if (
    !Number.isInteger(levelNumber) ||
    levelNumber < 1 ||
    partId === undefined ||
    !['introduction', 'practice', 'conversation', 'interaction'].includes(partId)
  ) {
    return { destination: 'learn' }
  }

  const target: LearnerNavigationTarget = {
    destination: 'learn',
    levelNumber,
    partId: partId as SessionPartId,
  }
  if (segments.length !== 8) {
    return target
  }

  const kind = segments[4]
  const phase = segments[5]
  const questionIndex = Number(segments[6])
  const totalQuestions = Number(segments[7])
  if (
    kind === undefined ||
    phase === undefined ||
    !['quick-check', 'checkpoint'].includes(kind) ||
    !['question', 'feedback'].includes(phase) ||
    !Number.isInteger(questionIndex) ||
    questionIndex < 0 ||
    !Number.isInteger(totalQuestions) ||
    totalQuestions < 1 ||
    questionIndex >= totalQuestions
  ) {
    return target
  }

  return {
    ...target,
    assessment: {
      kind: kind as LearnerAssessmentKind,
      phase: phase as 'question' | 'feedback',
      questionIndex,
      totalQuestions,
    },
  }
}

function urlForTarget(target: LearnerNavigationTarget): string {
  if (target.firstUseStep !== undefined) {
    return `#home/first-use/${target.firstUseStep}`
  }
  if (target.destination !== 'learn' || target.levelNumber === undefined) {
    return `#${target.destination}`
  }
  const partId = target.partId ?? 'introduction'
  const base = `#learn/levels/${target.levelNumber}/${partId}`
  return target.assessment === undefined
    ? base
    : `${base}/${target.assessment.kind}/${target.assessment.phase}/${target.assessment.questionIndex}/${target.assessment.totalQuestions}`
}

function sameTarget(
  left: LearnerNavigationTarget,
  right: LearnerNavigationTarget,
): boolean {
  return (
    left.destination === right.destination &&
    left.firstUseStep === right.firstUseStep &&
    left.levelNumber === right.levelNumber &&
    left.partId === right.partId &&
    left.assessment?.kind === right.assessment?.kind &&
    left.assessment?.phase === right.assessment?.phase &&
    left.assessment?.questionIndex === right.assessment?.questionIndex &&
    left.assessment?.totalQuestions === right.assessment?.totalQuestions
  )
}

function targetFromState(state: LearnerNavigationState): LearnerNavigationTarget {
  return {
    destination: state.destination,
    ...(state.firstUseStep === undefined ? {} : { firstUseStep: state.firstUseStep }),
    ...(state.levelNumber === undefined ? {} : { levelNumber: state.levelNumber }),
    ...(state.partId === undefined ? {} : { partId: state.partId }),
    ...(state.assessment === undefined ? {} : { assessment: state.assessment }),
  }
}

function storedState(value: unknown): LearnerNavigationState | null {
  if (typeof value !== 'object' || value === null) {
    return null
  }

  const record = value as Record<string, unknown>
  const candidate = record[HISTORY_KEY]
  if (typeof candidate !== 'object' || candidate === null) {
    return null
  }

  const state = candidate as Partial<LearnerNavigationState>
  if (
    state.version !== 1 ||
    typeof state.index !== 'number' ||
    state.index < 0 ||
    !['home', 'learn', 'speak', 'progress', 'tuition'].includes(
      state.destination ?? '',
    )
  ) {
    return null
  }

  if (
    state.firstUseStep !== undefined &&
    !(FIRST_USE_STEPS as readonly string[]).includes(state.firstUseStep)
  ) {
    return {
      ...(state as LearnerNavigationState),
      firstUseStep: undefined,
    }
  }

  return state as LearnerNavigationState
}

function mergedBrowserState(state: LearnerNavigationState): Record<string, unknown> {
  const current = globalThis.history.state
  const base = typeof current === 'object' && current !== null ? current : {}

  return { ...base, [HISTORY_KEY]: state }
}

function announceNavigationState(state: LearnerNavigationState): void {
  globalThis.dispatchEvent(new CustomEvent<LearnerNavigationState>(NAVIGATION_EVENT, {
    detail: state,
  }))
}

export function readLearnerNavigationState(): LearnerNavigationState | null {
  return storedState(globalThis.history?.state)
}

export function initializeLearnerNavigation(
  destination: PrimaryDestination,
): LearnerNavigationState {
  const current = readLearnerNavigationState()
  if (current !== null && current.destination === destination) {
    return current
  }

  const state: LearnerNavigationState = {
    ...(targetFromHash(globalThis.location.hash) ?? { destination }),
    index: 0,
    previous: null,
    restoreFocusId: null,
    version: 1,
  }
  globalThis.history.replaceState(mergedBrowserState(state), '', globalThis.location.href)
  return state
}

function pushTarget(
  target: LearnerNavigationTarget,
  restoreFocusId: string | null,
  url = urlForTarget(target),
): LearnerNavigationState {
  const current =
    readLearnerNavigationState() ??
    initializeLearnerNavigation(
      destinationFromHash(globalThis.location.hash) ?? target.destination,
    )

  const restoredCurrent: LearnerNavigationState = {
    ...current,
    restoreFocusId,
  }
  globalThis.history.replaceState(
    mergedBrowserState(restoredCurrent),
    '',
    globalThis.location.href,
  )

  const state: LearnerNavigationState = {
    ...target,
    index: current.index + 1,
    previous: targetFromState(current),
    restoreFocusId: null,
    version: 1,
  }
  globalThis.history.pushState(
    mergedBrowserState(state),
    '',
    url,
  )
  announceNavigationState(state)
  return state
}

export function synchronizeLearnerNavigationFromLocation(
  destination: PrimaryDestination,
): LearnerNavigationState {
  const target = targetFromHash(globalThis.location.hash) ?? { destination }
  const current = readLearnerNavigationState()
  if (current !== null && sameTarget(targetFromState(current), target)) {
    return current
  }

  const state: LearnerNavigationState = {
    ...target,
    index: current === null ? 0 : current.index + 1,
    previous: current === null ? null : targetFromState(current),
    restoreFocusId: null,
    version: 1,
  }
  globalThis.history.replaceState(mergedBrowserState(state), '', globalThis.location.href)
  announceNavigationState(state)
  return state
}

export function navigateToLearnerDestination(
  destination: PrimaryDestination,
  restoreFocusId: string | null = `primary-navigation-${destination}`,
): LearnerNavigationState {
  return pushTarget({ destination }, restoreFocusId)
}

export function navigateToLearnerSignIn(): LearnerNavigationState {
  return pushTarget({ destination: 'home' }, null, '#sign-in')
}

export function navigateToPaidSignUp(planCode = ''): LearnerNavigationState {
  const hash = planCode === '' ? '#sign-up' : `#sign-up/${planCode}`
  return pushTarget({ destination: 'home' }, null, hash)
}

export function openLearnerLevel(
  levelNumber: number,
  restoreFocusId: string,
): LearnerNavigationState {
  return pushTarget(
    { destination: 'learn', levelNumber, partId: 'introduction' },
    restoreFocusId,
  )
}

export function openLearnerLevelList(
  restoreFocusId: string,
): LearnerNavigationState {
  return pushTarget({ destination: 'learn' }, restoreFocusId)
}

export function openLearnerSessionPart(
  levelNumber: number,
  partId: SessionPartId,
  restoreFocusId: string,
): LearnerNavigationState {
  return pushTarget({ destination: 'learn', levelNumber, partId }, restoreFocusId)
}

export function openLearnerQuestion(
  levelNumber: number,
  partId: SessionPartId,
  kind: LearnerAssessmentKind,
  phase: 'question' | 'feedback',
  questionIndex: number,
  totalQuestions: number,
  restoreFocusId: string,
): LearnerNavigationState {
  const current = readLearnerNavigationState()
  if (
    current?.destination === 'learn' &&
    current.levelNumber === levelNumber &&
    current.partId === partId &&
    current.assessment?.kind === kind &&
    current.assessment.phase === phase &&
    current.assessment.questionIndex === questionIndex &&
    current.assessment.totalQuestions === totalQuestions
  ) {
    return current
  }

  return pushTarget(
    {
      assessment: { kind, phase, questionIndex, totalQuestions },
      destination: 'learn',
      levelNumber,
      partId,
    },
    restoreFocusId,
  )
}

function replaceTarget(
  target: LearnerNavigationTarget,
  index?: number,
  previous?: LearnerNavigationTarget | null,
): LearnerNavigationState {
  const current =
    readLearnerNavigationState() ?? initializeLearnerNavigation(target.destination)
  const state: LearnerNavigationState = {
    ...target,
    index: index ?? current.index,
    previous: previous === undefined ? current.previous : previous,
    restoreFocusId: null,
    version: 1,
  }
  globalThis.history.replaceState(
    mergedBrowserState(state),
    '',
    urlForTarget(target),
  )
  announceNavigationState(state)
  return state
}

export function openFirstUseStep(
  step: FirstUseStep,
  restoreFocusId: string | null = null,
): LearnerNavigationState {
  const current =
    readLearnerNavigationState() ?? initializeLearnerNavigation('home')
  const target: LearnerNavigationTarget = {
    destination: 'home',
    firstUseStep: step,
  }
  if (sameTarget(targetFromState(current), target)) {
    return current
  }

  if (current.firstUseStep === undefined) {
    return replaceTarget(target)
  }

  const currentIndex = FIRST_USE_STEPS.indexOf(current.firstUseStep)
  const nextIndex = FIRST_USE_STEPS.indexOf(step)
  if (nextIndex < currentIndex) {
    return replaceTarget(target, current.index, current.previous)
  }

  return pushTarget(target, restoreFocusId)
}

export function closeFirstUseNavigation(): LearnerNavigationState {
  const current =
    readLearnerNavigationState() ?? initializeLearnerNavigation('home')
  const target: LearnerNavigationTarget = { destination: 'home' }
  if (current.firstUseStep === undefined && current.destination === 'home') {
    return current
  }

  return replaceTarget(target, current.index, current.previous)
}

export function subscribeToLearnerNavigation(listener: NavigationListener): () => void {
  const onNavigation = (event: Event) => {
    const state = (event as CustomEvent<LearnerNavigationState>).detail
    listener(state)
  }
  const onPopState = () => {
    const state = readLearnerNavigationState()
    if (state !== null) {
      listener(state)
    }
  }

  globalThis.addEventListener(NAVIGATION_EVENT, onNavigation)
  globalThis.addEventListener('popstate', onPopState)
  return () => {
    globalThis.removeEventListener(NAVIGATION_EVENT, onNavigation)
    globalThis.removeEventListener('popstate', onPopState)
  }
}

export function handleAndroidBack(): boolean {
  const request = new CustomEvent(ANDROID_BACK_REQUEST_EVENT, {
    cancelable: true,
  })
  if (!globalThis.dispatchEvent(request)) {
    return true
  }

  const state = readLearnerNavigationState()
  if (state === null || state.index === 0) {
    return false
  }

  globalThis.history.back()
  return true
}

export function restoreLearnerNavigationFocus(state: LearnerNavigationState): void {
  if (state.restoreFocusId === null) {
    return
  }

  globalThis.requestAnimationFrame(() => {
    globalThis.requestAnimationFrame(() => {
      document.getElementById(state.restoreFocusId ?? '')?.focus()
    })
  })
}
