import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  ANDROID_BACK_REQUEST_EVENT,
  closeFirstUseNavigation,
  handleAndroidBack,
  initializeLearnerNavigation,
  navigateToLearnerDestination,
  navigateToLearnerSignIn,
  openFirstUseStep,
  openLearnerLevel,
  openLearnerQuestion,
  openLearnerSessionPart,
  readLearnerNavigationState,
  restoreLearnerNavigationFocus,
} from './LearnerNavigationHistory'

beforeEach(() => {
  vi.restoreAllMocks()
  window.history.replaceState({}, '', '#home')
})

describe('learner navigation history', () => {
  it('represents destination, level, part and exact quiz question', () => {
    initializeLearnerNavigation('home')
    navigateToLearnerDestination('learn')
    openLearnerLevel(4, 'level-path-level-4')
    openLearnerSessionPart(4, 'practice', 'level-4-part-introduction')
    openLearnerQuestion(
      4,
      'practice',
      'quick-check',
      'question',
      1,
      3,
      'level-4-quick-check-question-0-continue',
    )

    expect(readLearnerNavigationState()).toMatchObject({
      assessment: {
        kind: 'quick-check',
        phase: 'question',
        questionIndex: 1,
        totalQuestions: 3,
      },
      destination: 'learn',
      index: 4,
      levelNumber: 4,
      partId: 'practice',
      previous: {
        destination: 'learn',
        levelNumber: 4,
        partId: 'practice',
      },
    })
  })

  it('routes Android Back through web history without legacy key handling', () => {
    initializeLearnerNavigation('home')
    navigateToLearnerDestination('learn')
    const back = vi.spyOn(window.history, 'back').mockImplementation(() => undefined)
    back.mockClear()

    expect(handleAndroidBack()).toBe(true)
    expect(back).toHaveBeenCalledOnce()
  })

  it('pushes sign-in through learner history so system Back returns to Home', () => {
    initializeLearnerNavigation('home')
    navigateToLearnerSignIn()

    expect(globalThis.location.hash).toBe('#sign-in')
    expect(readLearnerNavigationState()).toMatchObject({
      destination: 'home',
      index: 1,
      previous: { destination: 'home' },
    })

    const back = vi.spyOn(window.history, 'back').mockImplementation(() => undefined)
    expect(handleAndroidBack()).toBe(true)
    expect(back).toHaveBeenCalledOnce()
  })

  it('pushes allowlisted first-use steps and pops them with browser Back', () => {
    initializeLearnerNavigation('home')
    openFirstUseStep('welcome')
    expect(globalThis.location.hash).toBe('#home/first-use/welcome')
    expect(readLearnerNavigationState()?.index).toBe(0)

    openFirstUseStep('preferences')
    expect(globalThis.location.hash).toBe('#home/first-use/preferences')
    expect(readLearnerNavigationState()).toMatchObject({
      destination: 'home',
      firstUseStep: 'preferences',
      index: 1,
      previous: { destination: 'home', firstUseStep: 'welcome' },
    })

    closeFirstUseNavigation()
    expect(globalThis.location.hash).toBe('#home')
    expect(readLearnerNavigationState()?.firstUseStep).toBeUndefined()
  })

  it('lets an active recording consume Android Back before navigation', () => {
    initializeLearnerNavigation('home')
    navigateToLearnerDestination('speak')
    const back = vi.spyOn(window.history, 'back').mockImplementation(() => undefined)
    const cancelRecording = (event: Event) => event.preventDefault()
    globalThis.addEventListener(ANDROID_BACK_REQUEST_EVENT, cancelRecording)

    expect(handleAndroidBack()).toBe(true)
    expect(back).not.toHaveBeenCalled()

    globalThis.removeEventListener(ANDROID_BACK_REQUEST_EVENT, cancelRecording)
  })

  it('restores focus recorded on the revealed history entry', () => {
    vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
      callback(0)
      return 1
    })
    const button = document.createElement('button')
    button.id = 'level-path-level-4'
    document.body.append(button)

    initializeLearnerNavigation('learn')
    openLearnerLevel(4, button.id)
    const revealed = readLearnerNavigationState()?.previous
    window.history.replaceState(
      {
        __globalEnglishLearnerNavigation: {
          ...revealed,
          index: 0,
          previous: null,
          restoreFocusId: button.id,
          version: 1,
        },
      },
      '',
      '#learn',
    )

    const state = readLearnerNavigationState()
    expect(state).not.toBeNull()
    restoreLearnerNavigationFocus(state!)
    expect(button).toHaveFocus()
  })
})
