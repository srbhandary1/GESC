import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { InteractiveAvatar } from '../components/InteractiveAvatar'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { useCoachPreference } from './coachPreferenceContextValue'
import './CoachSelector.css'

export function CoachSelector({
  locale,
  localizationPolicy,
  hideRename = false,
}: {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly hideRename?: boolean
}) {
  const { preference, selectCoach, renameCoach, resetCoachName } =
    useCoachPreference()
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const femaleName = message('coach.female.name').text
  const maleName = message('coach.male.name').text

  return (
    <section
      aria-labelledby="coach-selector-title"
      className="coach-selector"
    >
      <div className="coach-selector__header">
        <p className="coach-selector__eyebrow">
          <LocalizedMessage resolution={message('coach.selector.eyebrow')} />
        </p>
        <h2 id="coach-selector-title">
          <LocalizedMessage resolution={message('coach.selector.title')} />
        </h2>
      </div>
      <div
        aria-label={message('coach.selector.title').text}
        className="coach-selector__choices"
        role="radiogroup"
      >
        {(
          [
            { gender: 'female', name: femaleName },
            { gender: 'male', name: maleName },
          ] as const
        ).map((coach) => (
          <button
            aria-checked={preference.gender === coach.gender}
            className={
              preference.gender === coach.gender
                ? 'coach-selector__choice coach-selector__choice--selected'
                : 'coach-selector__choice'
            }
            key={coach.gender}
            onClick={() => selectCoach(coach.gender)}
            role="radio"
            type="button"
          >
            <InteractiveAvatar
              className="coach-selector__image"
              coachGender={coach.gender}
              name={coach.name}
              showName={false}
              size="sm"
            />
            <span>{coach.name}</span>
          </button>
        ))}
      </div>
      {hideRename ? null : (
        <>
      <label className="coach-selector__rename">
        <span>
          <LocalizedMessage resolution={message('coach.rename.label')} />
        </span>
        <input
          maxLength={32}
          onChange={(event) => renameCoach(event.currentTarget.value)}
          placeholder={
            preference.gender === 'female' ? femaleName : maleName
          }
          type="text"
          value={preference.displayName ?? ''}
        />
      </label>
      <div className="coach-selector__actions">
        <button onClick={resetCoachName} type="button">
          <LocalizedMessage resolution={message('coach.rename.reset')} />
        </button>
      </div>
        </>
      )}
      <p className="coach-selector__note">
        <LocalizedMessage resolution={message('coach.rename.note')} />
      </p>
    </section>
  )
}
