import type {
  MessageKey,
  MessageParameters,
  SupportedLocale,
} from '@global-english/localization'
import { useState, type FormEvent, type ReactNode, type RefObject } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import type { LearnerGoal } from '../learner/LearnerJourneyApi'
import { LEARNER_GOALS } from '../learner/LearnerJourneyApi'
import { AvatarSlot } from './AvatarSlot'

export const SPEAKING_COMFORT_LEVELS = [
  'just-starting',
  'some-english',
  'work-study',
] as const

export type SpeakingComfort = (typeof SPEAKING_COMFORT_LEVELS)[number]

export type OnboardingStepId = 'language' | 'adult' | 'goal' | 'level'

export interface OnboardingSelection {
  readonly adultConfirmed: boolean
  readonly goal: LearnerGoal | null
  readonly level: SpeakingComfort | null
}

interface OnboardingStepperProps {
  readonly busy?: boolean
  readonly commandLocked?: boolean
  readonly error?: ReactNode
  readonly locale: SupportedLocale
  readonly locales?: readonly SupportedLocale[]
  readonly message: (
    key: MessageKey,
    parameters?: MessageParameters,
  ) => InterfaceMessageResolution
  readonly onLocaleChange?: (locale: SupportedLocale) => void
  readonly onSubmit: (selection: OnboardingSelection) => void
  readonly steps: readonly OnboardingStepId[]
  readonly titleRef: RefObject<HTMLHeadingElement | null>
}

const goalKeys: Readonly<Record<LearnerGoal, MessageKey>> = {
  'everyday-conversation': 'learner.onboarding.goal_everyday',
  'travel-confidence': 'learner.onboarding.goal_travel',
  'workplace-confidence': 'learner.onboarding.goal_workplace',
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
  adult: 'learner.onboarding.adult_title',
  goal: 'learner.onboarding.goal_title',
  language: 'shell.locale_label',
  level: 'learner.onboarding.level_title',
}

export function OnboardingStepper({
  busy = false,
  commandLocked = false,
  error,
  locale,
  locales = [],
  message,
  onLocaleChange,
  onSubmit,
  steps,
  titleRef,
}: OnboardingStepperProps) {
  const [stepIndex, setStepIndex] = useState(0)
  const [adultConfirmed, setAdultConfirmed] = useState(false)
  const [goal, setGoal] = useState<LearnerGoal | null>(null)
  const [level, setLevel] = useState<SpeakingComfort | null>(null)

  const step = steps[stepIndex] ?? steps[0]!
  const isLast = stepIndex === steps.length - 1
  const canAdvance = canContinue(step, {
    adultConfirmed,
    goal,
    level,
  })

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (!isLast) {
      if (!canAdvance) {
        return
      }

      setStepIndex((current) => Math.min(current + 1, steps.length - 1))
      return
    }

    onSubmit({ adultConfirmed, goal, level })
  }

  return (
    <section
      aria-labelledby="onboarding-title"
      className="learner-card learner-onboarding ge-onboarding"
    >
      <div className="ge-onboarding__presence">
        <AvatarSlot
          label={message('shell.avatar.teacher').text}
          size="lg"
          slot="home-teacher"
        />
        <p className="ge-onboarding__step" role="status">
          <LocalizedMessage
            resolution={message('learner.onboarding.step', {
              current: String(stepIndex + 1),
              total: String(steps.length),
            })}
          />
        </p>
      </div>
      <h2 id="onboarding-title" ref={titleRef} tabIndex={-1}>
        <LocalizedMessage resolution={message(stepTitles[step])} />
      </h2>
      <p>
        <LocalizedMessage
          resolution={message(
            step === 'adult'
              ? 'learner.onboarding.adult_hint'
              : step === 'language'
                ? 'learner.onboarding.language_hint'
                : 'learner.onboarding.introduction',
          )}
        />
      </p>

      <form className="ge-onboarding__form" onSubmit={submit}>
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
                <input
                  checked={locale === candidate}
                  disabled={busy || commandLocked}
                  name="onboarding-language"
                  onChange={() => onLocaleChange?.(candidate)}
                  type="radio"
                  value={candidate}
                />
                <span lang={candidate}>
                  <LocalizedMessage resolution={message(localeKeys[candidate])} />
                </span>
              </label>
            ))}
          </fieldset>
        ) : null}

        {step === 'adult' ? (
          <label
            className="ge-select-card ge-select-card--confirm"
            data-selected={adultConfirmed ? 'true' : undefined}
          >
            <input
              checked={adultConfirmed}
              disabled={busy || commandLocked}
              onChange={(event) =>
                setAdultConfirmed(event.currentTarget.checked)
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
                data-selected={goal === candidate ? 'true' : undefined}
                key={candidate}
              >
                <input
                  checked={goal === candidate}
                  disabled={busy || commandLocked}
                  name="learner-goal"
                  onChange={() => setGoal(candidate)}
                  type="radio"
                  value={candidate}
                />
                <span>
                  <LocalizedMessage resolution={message(goalKeys[candidate])} />
                </span>
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
                data-selected={level === candidate ? 'true' : undefined}
                key={candidate}
              >
                <input
                  checked={level === candidate}
                  disabled={busy || commandLocked}
                  name="speaking-comfort"
                  onChange={() => setLevel(candidate)}
                  type="radio"
                  value={candidate}
                />
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
              </label>
            ))}
          </fieldset>
        ) : null}

        {error === undefined || error === null ? null : (
          <p role="alert">{error}</p>
        )}

        <div className="ge-onboarding__dock">
          {stepIndex > 0 && !commandLocked ? (
            <button
              className="ge-button"
              disabled={busy}
              onClick={() => setStepIndex((current) => Math.max(0, current - 1))}
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
              resolution={message(
                busy
                  ? 'learner.onboarding.saving'
                  : 'learner.onboarding.continue',
              )}
            />
          </button>
        </div>
      </form>
    </section>
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
