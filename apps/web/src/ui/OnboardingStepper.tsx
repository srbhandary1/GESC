import type {
  MessageKey,
  MessageParameters,
  SupportedLocale,
} from '@global-english/localization'
import {
  useEffect,
  useState,
  type FormEvent,
  type ReactNode,
  type RefObject,
} from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import type { LearnerGoal } from '../learner/LearnerJourneyApi'
import { LEARNER_GOALS } from '../learner/LearnerJourneyApi'
import { AvatarSlot } from './AvatarSlot'

export const SPEAKING_COMFORT_LEVELS = [ // eslint-disable-line react-refresh/only-export-components -- shared onboarding constants
  'just-starting',
  'some-english',
  'work-study',
] as const

export type SpeakingComfort = (typeof SPEAKING_COMFORT_LEVELS)[number]

export type OnboardingStepId =
  | 'welcome'
  | 'adult'
  | 'preferences'
  | 'goal'
  | 'placement'
  | 'audio-output'
  | 'consent'
  | 'microphone'
  | 'practice'
  | 'acknowledgement'
  | 'language'
  | 'level'

export interface OnboardingSelection {
  readonly adultConfirmed: boolean
  readonly goal: LearnerGoal | null
  readonly level: SpeakingComfort | null
}

interface OnboardingStepperProps {
  readonly activeStep?: OnboardingStepId
  readonly busy?: boolean
  readonly backAvailable?: boolean
  readonly canAdvance?: boolean
  readonly commandLocked?: boolean
  readonly continueLabel?: InterfaceMessageResolution
  readonly content?: ReactNode
  readonly description?: InterfaceMessageResolution
  readonly error?: ReactNode
  readonly locale: SupportedLocale
  readonly locales?: readonly SupportedLocale[]
  readonly message: (
    key: MessageKey,
    parameters?: MessageParameters,
  ) => InterfaceMessageResolution
  readonly onBack?: () => void
  readonly onContinue?: (selection: OnboardingSelection) => void
  readonly onLocaleChange?: (locale: SupportedLocale) => void
  readonly onSkip?: () => void
  readonly onSubmit?: (selection: OnboardingSelection) => void
  readonly selection?: OnboardingSelection
  readonly showCoach?: boolean
  readonly skipLabel?: InterfaceMessageResolution
  readonly steps: readonly OnboardingStepId[]
  readonly title?: InterfaceMessageResolution
  readonly titleRef: RefObject<HTMLHeadingElement | null>
}

const goalKeys: Readonly<
  Record<LearnerGoal, { readonly label: MessageKey; readonly hint: MessageKey }>
> = {
  'everyday-conversation': {
    label: 'learner.onboarding.goal_everyday',
    hint: 'learner.onboarding.goal_everyday_hint',
  },
  'travel-confidence': {
    label: 'learner.onboarding.goal_travel',
    hint: 'learner.onboarding.goal_travel_hint',
  },
  'workplace-confidence': {
    label: 'learner.onboarding.goal_workplace',
    hint: 'learner.onboarding.goal_workplace_hint',
  },
}

const levelKeys: Readonly<
  Record<SpeakingComfort, { readonly label: MessageKey; readonly hint: MessageKey }>
> = {
  'just-starting': {
    label: 'learner.onboarding.level_new',
    hint: 'learner.onboarding.level_new_hint',
  },
  'some-english': {
    label: 'learner.onboarding.level_some',
    hint: 'learner.onboarding.level_some_hint',
  },
  'work-study': {
    label: 'learner.onboarding.level_work',
    hint: 'learner.onboarding.level_work_hint',
  },
}

const localeKeys: Readonly<Record<SupportedLocale, MessageKey>> = {
  'en-IN': 'locale.en_IN',
  'hi-IN': 'locale.hi_IN',
  'kn-IN': 'locale.kn_IN',
  'ml-IN': 'locale.ml_IN',
  'ta-IN': 'locale.ta_IN',
  'te-IN': 'locale.te_IN',
}

const stepTitles: Readonly<Record<OnboardingStepId, MessageKey>> = {
  acknowledgement: 'learner.onboarding.acknowledgement_title',
  adult: 'learner.onboarding.adult_title',
  'audio-output': 'learner.onboarding.audio_title',
  consent: 'learner.onboarding.consent_title',
  goal: 'learner.onboarding.goal_title',
  language: 'shell.locale_label',
  level: 'learner.onboarding.level_title',
  microphone: 'learner.onboarding.microphone_title',
  placement: 'learner.onboarding.placement_title',
  practice: 'learner.onboarding.practice_title',
  preferences: 'learner.onboarding.preferences_title',
  welcome: 'learner.onboarding.welcome_title',
}

export function OnboardingStepper({
  activeStep,
  backAvailable = true,
  busy = false,
  canAdvance: controlledCanAdvance,
  commandLocked = false,
  continueLabel,
  content,
  description,
  error,
  locale,
  locales = [],
  message,
  onBack,
  onContinue,
  onLocaleChange,
  onSkip,
  onSubmit,
  selection,
  showCoach = true,
  skipLabel,
  steps,
  title,
  titleRef,
}: OnboardingStepperProps) {
  const [stepIndex, setStepIndex] = useState(0)
  const [internalAdultConfirmed, setInternalAdultConfirmed] = useState(false)
  const [internalGoal, setInternalGoal] = useState<LearnerGoal | null>(null)
  const [internalLevel, setInternalLevel] = useState<SpeakingComfort | null>(null)

  const controlledIndex =
    activeStep === undefined ? -1 : steps.indexOf(activeStep)
  const currentIndex = controlledIndex >= 0 ? controlledIndex : stepIndex
  const step = steps[currentIndex] ?? steps[0]!
  const isControlled = activeStep !== undefined
  const isLast = currentIndex === steps.length - 1
  const currentSelection = selection ?? {
    adultConfirmed: internalAdultConfirmed,
    goal: internalGoal,
    level: internalLevel,
  }
  const canAdvance =
    controlledCanAdvance ?? canContinue(step, currentSelection)

  useEffect(() => {
    titleRef.current?.focus()
  }, [currentIndex, titleRef])

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (isControlled) {
      if (canAdvance) {
        onContinue?.(currentSelection)
      }
      return
    }

    if (!isLast) {
      if (!canAdvance) {
        return
      }

      setStepIndex((current) => Math.min(current + 1, steps.length - 1))
      return
    }

    onSubmit?.(currentSelection)
  }

  return (
    <section
      aria-labelledby="onboarding-title"
      className="learner-card learner-onboarding ge-onboarding"
    >
      <div className="ge-onboarding__dots" aria-hidden="true">
        {steps.map((candidate, index) => (
          <span
            className="ge-onboarding__dot"
            data-active={index === currentIndex ? 'true' : undefined}
            key={candidate}
          />
        ))}
      </div>
      {showCoach ? <div className="ge-onboarding__presence">
        <AvatarSlot
          label={message('tutor.name').text}
          size="sm"
          slot="home-teacher"
        />
        <div className="ge-onboarding__coach">
          <p className="ge-onboarding__coach-role">
            <LocalizedMessage resolution={message('tutor.role')} />
          </p>
          <p className="ge-onboarding__coach-line">
            <LocalizedMessage resolution={message('tutor.intro')} />
          </p>
        </div>
      </div> : null}
      <p className="ge-onboarding__step" role="status">
        <LocalizedMessage
          resolution={message('learner.onboarding.step', {
            current: String(currentIndex + 1),
            total: String(steps.length),
          })}
        />
      </p>
      <h2 id="onboarding-title" ref={titleRef} tabIndex={-1}>
        <LocalizedMessage resolution={title ?? message(stepTitles[step])} />
      </h2>
      <p>
        <LocalizedMessage
          resolution={description ?? message(
            step === 'adult'
              ? 'learner.onboarding.adult_hint'
              : step === 'language'
                ? 'learner.onboarding.language_hint'
                : 'learner.onboarding.introduction',
          )}
        />
      </p>

      <form className="ge-onboarding__form" onSubmit={submit}>
        {content ?? <>
        {step === 'language' ? (
          <fieldset className="ge-select-grid">
            <legend className="ge-visually-hidden">
              <LocalizedMessage resolution={message('shell.locale_label')} />
            </legend>
            {locales.map((candidate) => (
              <label
                className="ge-select-card"
                data-selected={locale === candidate ? 'true' : undefined}
                key={candidate}
              >
                <span lang={candidate}>
                  <LocalizedMessage resolution={message(localeKeys[candidate])} />
                </span>
                <input
                  checked={locale === candidate}
                  disabled={busy || commandLocked}
                  name="onboarding-language"
                  onChange={() => onLocaleChange?.(candidate)}
                  type="radio"
                  value={candidate}
                />
              </label>
            ))}
          </fieldset>
        ) : null}

        {step === 'adult' ? (
          <label
            className="ge-select-card ge-select-card--confirm"
            data-selected={currentSelection.adultConfirmed ? 'true' : undefined}
          >
            <input
              checked={currentSelection.adultConfirmed}
              disabled={busy || commandLocked}
              onChange={(event) =>
                setInternalAdultConfirmed(event.currentTarget.checked)
              }
              type="checkbox"
            />
            <LocalizedMessage
              resolution={message('learner.onboarding.adult_confirmation')}
            />
          </label>
        ) : null}

        {step === 'goal' ? (
          <fieldset className="ge-select-grid">
            <legend className="ge-visually-hidden">
              <LocalizedMessage
                resolution={message('learner.onboarding.goal_legend')}
              />
            </legend>
            {LEARNER_GOALS.map((candidate) => (
              <label
                className="ge-select-card"
                data-selected={currentSelection.goal === candidate ? 'true' : undefined}
                key={candidate}
              >
                <span aria-hidden="true" className="ge-select-card__icon">
                  <GoalCardIcon goal={candidate} />
                </span>
                <span>
                  <strong>
                    <LocalizedMessage
                      resolution={message(goalKeys[candidate].label)}
                    />
                  </strong>
                  <span aria-hidden="true" className="ge-select-card__hint">
                    <LocalizedMessage
                      resolution={message(goalKeys[candidate].hint)}
                    />
                  </span>
                </span>
                <input
                  checked={currentSelection.goal === candidate}
                  disabled={busy || commandLocked}
                  name="learner-goal"
                  onChange={() => setInternalGoal(candidate)}
                  type="radio"
                  value={candidate}
                />
              </label>
            ))}
          </fieldset>
        ) : null}

        {step === 'level' ? (
          <fieldset className="ge-select-grid">
            <legend className="ge-visually-hidden">
              <LocalizedMessage
                resolution={message('learner.onboarding.level_legend')}
              />
            </legend>
            {SPEAKING_COMFORT_LEVELS.map((candidate) => (
              <label
                className="ge-select-card"
                data-selected={currentSelection.level === candidate ? 'true' : undefined}
                key={candidate}
              >
                <span>
                  <strong>
                    <LocalizedMessage
                      resolution={message(levelKeys[candidate].label)}
                    />
                  </strong>
                  <span aria-hidden="true" className="ge-select-card__hint">
                    <LocalizedMessage
                      resolution={message(levelKeys[candidate].hint)}
                    />
                  </span>
                </span>
                <input
                  checked={currentSelection.level === candidate}
                  disabled={busy || commandLocked}
                  name="speaking-comfort"
                  onChange={() => setInternalLevel(candidate)}
                  type="radio"
                  value={candidate}
                />
              </label>
            ))}
          </fieldset>
        ) : null}
        </>}

        {error === undefined || error === null ? null : (
          <p role="alert">{error}</p>
        )}

        <div className="ge-onboarding__dock">
          {onSkip !== undefined && skipLabel !== undefined ? (
            <button
              className="ge-button"
              disabled={busy}
              onClick={onSkip}
              type="button"
            >
              <LocalizedMessage resolution={skipLabel} />
            </button>
          ) : null}
          {currentIndex > 0 && backAvailable && !commandLocked ? (
            <button
              className="ge-button"
              disabled={busy}
              onClick={() => {
                if (isControlled) {
                  onBack?.()
                } else {
                  setStepIndex((current) => Math.max(0, current - 1))
                }
              }}
              type="button"
            >
              <LocalizedMessage
                resolution={message('learner.onboarding.back')}
              />
            </button>
          ) : null}
          <button
            className="ge-button ge-button--primary ge-onboarding__continue"
            disabled={busy || !canAdvance}
            type="submit"
          >
            <LocalizedMessage
              resolution={
                busy
                  ? message('learner.onboarding.saving')
                  : continueLabel ?? message('learner.onboarding.continue')
              }
            />
            {busy ? null : (
              <span aria-hidden="true" className="ge-onboarding__continue-arrow">
                <svg
                  fill="none"
                  stroke="currentColor"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  viewBox="0 0 24 24"
                >
                  <line x1="5" y1="12" x2="19" y2="12" />
                  <polyline points="12 5 19 12 12 19" />
                </svg>
              </span>
            )}
          </button>
        </div>
      </form>
    </section>
  )
}

function GoalCardIcon({ goal }: { readonly goal: LearnerGoal }) {
  if (goal === 'workplace-confidence') {
    return (
      <svg
        fill="none"
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="2"
        viewBox="0 0 24 24"
      >
        <rect height="14" rx="2" ry="2" width="20" x="2" y="7" />
        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
      </svg>
    )
  }

  if (goal === 'travel-confidence') {
    return (
      <svg
        fill="none"
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth="2"
        viewBox="0 0 24 24"
      >
        <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6" />
        <line x1="8" x2="8" y1="2" y2="18" />
        <line x1="16" x2="16" y1="6" y2="22" />
      </svg>
    )
  }

  return (
    <svg
      fill="none"
      stroke="currentColor"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth="2"
      viewBox="0 0 24 24"
    >
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    </svg>
  )
}

function canContinue(
  step: OnboardingStepId,
  selection: OnboardingSelection,
): boolean {
  if (step === 'adult') {
    return selection.adultConfirmed
  }

  if (step === 'goal') {
    return selection.goal !== null
  }

  if (step === 'level') {
    return selection.level !== null
  }

  return true
}
