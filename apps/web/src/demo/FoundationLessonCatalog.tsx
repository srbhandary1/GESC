import type {
  MessageKey,
  MessageParameters,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useState } from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { TapToPlay } from '../ui/TapToPlay'
import { speakDemoText, stopDemoSpeech } from './demoTts'
import {
  FOUNDATION_LESSON_GROUPS,
  FOUNDATION_LESSONS,
  lessonsInGroup,
  type FoundationLesson,
  type FoundationLessonGroup,
} from './foundationLessons'
import './FoundationLessonCatalog.css'

const groupTitleKeys = {
  foundations: 'demo.learn.group.foundations',
  daily: 'demo.learn.group.daily',
  work: 'demo.learn.group.work',
} as const satisfies Record<FoundationLessonGroup, MessageKey>

export interface FoundationLessonCatalogProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly onPractise?: () => void
}

export function FoundationLessonCatalog({
  locale,
  localizationPolicy,
  onPractise,
}: FoundationLessonCatalogProps) {
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const message = (key: MessageKey, parameters: MessageParameters = {}) =>
    resolveInterfaceMessage(key, locale, localizationPolicy, parameters)

  useEffect(() => {
    return () => {
      stopDemoSpeech()
    }
  }, [])

  const selected =
    FOUNDATION_LESSONS.find((lesson) => lesson.id === selectedId) ?? null

  const playModel = async (lesson: FoundationLesson) => {
    await speakDemoText(lesson.modelEn, 'en-IN')
  }

  if (selected !== null) {
    return (
      <section
        className="foundation-lessons foundation-lessons--detail"
        aria-labelledby="foundation-lesson-title"
        data-demo-lessons="synthetic"
      >
        <button
          type="button"
          className="phase1-text-link"
          onClick={() => setSelectedId(null)}
        >
          <LocalizedMessage resolution={message('demo.learn.back')} />
        </button>
        <p className="foundation-lessons__eyebrow">
          <LocalizedMessage
            resolution={message('demo.learn.lesson_label', {
              number: selected.number,
            })}
          />
        </p>
        <h2
          className="foundation-lessons__title"
          id="foundation-lesson-title"
          lang="en-IN"
        >
          {selected.title}
        </h2>
        <p className="foundation-lessons__guidance">{selected.guidance}</p>
        <blockquote className="foundation-lessons__model" lang="en-IN">
          <p className="foundation-lessons__model-label">
            <LocalizedMessage resolution={message('demo.learn.model_label')} />
          </p>
          <p>{selected.modelEn}</p>
        </blockquote>
        <TapToPlay
          idleLabel={
            <LocalizedMessage
              resolution={message('demo.learn.play', {
                number: selected.number,
              })}
            />
          }
          onPlay={() => playModel(selected)}
          playingLabel={
            <LocalizedMessage
              resolution={message('demo.learn.playing', {
                number: selected.number,
              })}
            />
          }
          unavailableLabel={
            <LocalizedMessage resolution={message('shell.listen.unavailable')} />
          }
        />
        {onPractise === undefined ? null : (
          <button
            type="button"
            className="ge-button ge-button--primary"
            onClick={onPractise}
          >
            <LocalizedMessage resolution={message('demo.learn.practise')} />
          </button>
        )}
      </section>
    )
  }

  return (
    <section
      className="foundation-lessons"
      aria-labelledby="foundation-lessons-title"
      data-demo-lessons="synthetic"
    >
      <h2 className="foundation-lessons__title" id="foundation-lessons-title">
        <LocalizedMessage resolution={message('demo.learn.lessons_title')} />
      </h2>
      <p className="foundation-lessons__lead">
        <LocalizedMessage resolution={message('demo.learn.lessons_lead')} />
      </p>
      {FOUNDATION_LESSON_GROUPS.map((group) => (
        <section
          key={group}
          className="foundation-lessons__group"
          aria-label={message(groupTitleKeys[group]).text}
        >
          <h3 className="foundation-lessons__group-title">
            <LocalizedMessage resolution={message(groupTitleKeys[group])} />
          </h3>
          <ol className="foundation-lessons__list">
            {lessonsInGroup(group).map((lesson) => (
              <li key={lesson.id}>
                <div className="foundation-lessons__row">
                  <button
                    type="button"
                    aria-label={`${message('demo.learn.open').text} ${lesson.title}`}
                    className="foundation-lessons__open"
                    onClick={() => setSelectedId(lesson.id)}
                  >
                    <span className="foundation-lessons__number">
                      {lesson.number}
                    </span>
                    <span className="foundation-lessons__copy">
                      <strong lang="en-IN">{lesson.title}</strong>
                      <span className="foundation-lessons__minutes">
                        <LocalizedMessage
                          resolution={message('learner.home.estimated_minutes', {
                            minutes: lesson.minutes,
                          })}
                        />
                      </span>
                    </span>
                  </button>
                  <TapToPlay
                    idleLabel={
                      <LocalizedMessage
                        resolution={message('demo.learn.play', {
                          number: lesson.number,
                        })}
                      />
                    }
                    onPlay={() => playModel(lesson)}
                    playingLabel={
                      <LocalizedMessage
                        resolution={message('demo.learn.playing', {
                          number: lesson.number,
                        })}
                      />
                    }
                    unavailableLabel={
                      <LocalizedMessage
                        resolution={message('shell.listen.unavailable')}
                      />
                    }
                  />
                </div>
              </li>
            ))}
          </ol>
        </section>
      ))}
    </section>
  )
}
