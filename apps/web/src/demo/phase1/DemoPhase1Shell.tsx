import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useMemo, useState } from 'react'
import { LocalizedMessage } from '../../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../../localization/interfaceLocalization'
import {
  PHASE1_MODULES,
  moduleFromHash,
  phase1AiTopics,
  phase1Attendance,
  phase1DailyActivity,
  phase1LevelRooms,
  phase1Levels,
  phase1Notices,
  type Phase1Module,
} from './phase1Fixtures'
import './DemoPhase1Shell.css'

export interface DemoPhase1ShellProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}

const moduleTitleKeys: Readonly<Record<Phase1Module, MessageKey>> = {
  dashboard: 'demo.phase1.nav.dashboard',
  attendance: 'demo.phase1.nav.attendance',
  'ai-teacher': 'demo.phase1.nav.ai_teacher',
  'daily-activity': 'demo.phase1.nav.daily_activity',
  levels: 'demo.phase1.nav.levels',
  notices: 'demo.phase1.nav.notices',
}

const noticeCategoryKeys: Readonly<
  Record<(typeof phase1Notices)[number]['category'], MessageKey>
> = {
  announcement: 'demo.phase1.notices.category.announcement',
  trainer: 'demo.phase1.notices.category.trainer',
  course: 'demo.phase1.notices.category.course',
  holiday: 'demo.phase1.notices.category.holiday',
  motivation: 'demo.phase1.notices.category.motivation',
  event: 'demo.phase1.notices.category.event',
}

export function DemoPhase1Shell({
  locale,
  localizationPolicy,
}: DemoPhase1ShellProps) {
  const [module, setModule] = useState<Phase1Module>(() =>
    moduleFromHash(globalThis.location?.hash ?? ''),
  )
  const [aiLanguage, setAiLanguage] = useState<'kn' | 'en'>('kn')
  const [activeTopicId, setActiveTopicId] = useState(phase1AiTopics[0]!.id)
  const [selectedLevelId, setSelectedLevelId] = useState('level-1')
  const [answers, setAnswers] = useState<Record<string, number>>({})
  const [quizSubmitted, setQuizSubmitted] = useState(false)
  const [audioPlayed, setAudioPlayed] = useState(false)

  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  useEffect(() => {
    const onHash = () => setModule(moduleFromHash(globalThis.location.hash))
    globalThis.addEventListener('hashchange', onHash)
    return () => globalThis.removeEventListener('hashchange', onHash)
  }, [])

  const go = (next: Phase1Module) => {
    const url = new URL(globalThis.location.href)
    url.hash = next
    globalThis.history.pushState(null, '', url)
    setModule(next)
  }

  const quizScore = useMemo(() => {
    if (!quizSubmitted) {
      return null
    }
    return phase1DailyActivity.questions.reduce((score, question) => {
      return answers[question.id] === question.correctIndex ? score + 1 : score
    }, 0)
  }, [answers, quizSubmitted])

  const activeTopic =
    phase1AiTopics.find((topic) => topic.id === activeTopicId) ??
    phase1AiTopics[0]!
  const selectedLevel =
    phase1Levels.find((level) => level.id === selectedLevelId) ??
    phase1Levels[2]!

  return (
    <div className="phase1-shell" data-demo-phase1="synthetic">
      <p className="phase1-shell__eyebrow">
        <LocalizedMessage resolution={message('demo.phase1.eyebrow')} />
      </p>
      <h1 className="phase1-shell__title" id="destination-title" tabIndex={-1}>
        <LocalizedMessage resolution={message(moduleTitleKeys[module])} />
      </h1>
      <p className="phase1-shell__lead">
        <LocalizedMessage resolution={message('demo.phase1.lead')} />
      </p>

      <nav
        className="phase1-shell__nav"
        aria-label={message('demo.phase1.nav_label').text}
      >
        <ul>
          {PHASE1_MODULES.map((item) => (
            <li key={item}>
              <a
                href={`#${item}`}
                className="phase1-shell__nav-link"
                aria-current={module === item ? 'page' : undefined}
                onClick={(event) => {
                  event.preventDefault()
                  go(item)
                }}
              >
                <LocalizedMessage
                  resolution={message(moduleTitleKeys[item])}
                />
              </a>
            </li>
          ))}
        </ul>
      </nav>

      {module === 'dashboard' ? (
        <section className="phase1-grid" aria-label={message('demo.phase1.nav.dashboard').text}>
          {(
            [
              ['attendance', 'demo.phase1.dashboard.attendance', `${phase1Attendance.percentage}%`],
              ['ai-teacher', 'demo.phase1.dashboard.ai_teacher', 'Level 1'],
              [
                'daily-activity',
                'demo.phase1.dashboard.daily',
                `${phase1DailyActivity.monthlyScore}/${phase1DailyActivity.monthlyMaximum}`,
              ],
              ['levels', 'demo.phase1.dashboard.levels', 'Level 1 · 35%'],
              ['notices', 'demo.phase1.dashboard.notices', String(phase1Notices.length)],
            ] as const
          ).map(([id, key, value]) => (
            <button
              key={id}
              type="button"
              className="phase1-card phase1-card--action"
              onClick={() => go(id)}
            >
              <span className="phase1-card__label">
                <LocalizedMessage resolution={message(key)} />
              </span>
              <strong className="phase1-card__value">{value}</strong>
              <span className="phase1-card__hint">
                <LocalizedMessage
                  resolution={message('demo.phase1.dashboard.open')}
                />
              </span>
            </button>
          ))}
        </section>
      ) : null}

      {module === 'attendance' ? (
        <section className="phase1-panel">
          <div className="phase1-stats">
            <article>
              <h2>
                <LocalizedMessage
                  resolution={message('demo.phase1.attendance.today')}
                />
              </h2>
              <p className="phase1-stats__value">
                <LocalizedMessage
                  resolution={message('demo.phase1.attendance.present')}
                />
              </p>
            </article>
            <article>
              <h2>
                <LocalizedMessage
                  resolution={message('demo.phase1.attendance.percentage')}
                />
              </h2>
              <p className="phase1-stats__value">
                {phase1Attendance.percentage}%
              </p>
            </article>
            <article>
              <h2>
                <LocalizedMessage
                  resolution={message('demo.phase1.attendance.month')}
                />
              </h2>
              <p className="phase1-stats__value">
                {phase1Attendance.presentDays}/
                {phase1Attendance.workingDaysSoFar}
              </p>
            </article>
          </div>
          <h3>
            <LocalizedMessage
              resolution={message('demo.phase1.attendance.report')}
            />{' '}
            · {phase1Attendance.monthLabel}
          </h3>
          <ol className="phase1-attendance-days">
            {phase1Attendance.days.slice(0, 10).map((day) => (
              <li key={day.date} data-status={day.status}>
                <span>{day.date.slice(-2)}</span>
                <span>{day.status}</span>
              </li>
            ))}
          </ol>
        </section>
      ) : null}

      {module === 'ai-teacher' ? (
        <section className="phase1-panel">
          <div className="phase1-ai-toolbar">
            <p>
              <LocalizedMessage
                resolution={message('demo.phase1.ai.scripted_notice')}
              />
            </p>
            <div className="phase1-ai-lang" role="group" aria-label="AI language">
              <button
                type="button"
                className={aiLanguage === 'kn' ? 'is-active' : undefined}
                onClick={() => setAiLanguage('kn')}
              >
                <LocalizedMessage
                  resolution={message('demo.phase1.ai.lang_kn')}
                />
              </button>
              <button
                type="button"
                className={aiLanguage === 'en' ? 'is-active' : undefined}
                onClick={() => setAiLanguage('en')}
              >
                <LocalizedMessage
                  resolution={message('demo.phase1.ai.lang_en')}
                />
              </button>
            </div>
          </div>
          <div className="phase1-ai-topics">
            {phase1AiTopics.map((topic) => (
              <button
                key={topic.id}
                type="button"
                className={
                  activeTopicId === topic.id ? 'is-active' : undefined
                }
                onClick={() => setActiveTopicId(topic.id)}
              >
                {aiLanguage === 'kn' ? topic.labelKn : topic.labelEn}
              </button>
            ))}
          </div>
          <blockquote className="phase1-ai-reply" lang={aiLanguage === 'kn' ? 'kn-IN' : 'en-IN'}>
            {aiLanguage === 'kn' ? activeTopic.replyKn : activeTopic.replyEn}
          </blockquote>
        </section>
      ) : null}

      {module === 'daily-activity' ? (
        <section className="phase1-panel phase1-daily">
          <article className="phase1-subpanel">
            <h2>
              <LocalizedMessage
                resolution={message('demo.phase1.daily.audio_title')}
              />
            </h2>
            <p>{phase1DailyActivity.audioLessonTitle}</p>
            <p className="phase1-muted">
              {phase1DailyActivity.audioLessonSummary} ·{' '}
              {phase1DailyActivity.audioLessonDuration}
            </p>
            <button
              type="button"
              className="ge-button ge-button--primary"
              onClick={() => setAudioPlayed(true)}
            >
              <LocalizedMessage
                resolution={message(
                  audioPlayed
                    ? 'demo.phase1.daily.audio_played'
                    : 'demo.phase1.daily.audio_play',
                )}
              />
            </button>
          </article>

          <article className="phase1-subpanel">
            <h2>{phase1DailyActivity.quizTitle}</h2>
            <p className="phase1-muted">
              <LocalizedMessage
                resolution={message('demo.phase1.daily.quiz_rules')}
              />
            </p>
            <ol className="phase1-quiz">
              {phase1DailyActivity.questions.map((question) => (
                <li key={question.id}>
                  <p>{question.prompt}</p>
                  <div className="phase1-quiz__options">
                    {question.options.map((option, index) => (
                      <label key={option}>
                        <input
                          type="radio"
                          name={question.id}
                          checked={answers[question.id] === index}
                          disabled={quizSubmitted}
                          onChange={() =>
                            setAnswers((current) => ({
                              ...current,
                              [question.id]: index,
                            }))
                          }
                        />
                        <span>{option}</span>
                      </label>
                    ))}
                  </div>
                </li>
              ))}
            </ol>
            <button
              type="button"
              className="ge-button ge-button--primary"
              disabled={
                quizSubmitted ||
                phase1DailyActivity.questions.some(
                  (question) => answers[question.id] === undefined,
                )
              }
              onClick={() => setQuizSubmitted(true)}
            >
              <LocalizedMessage
                resolution={message('demo.phase1.daily.submit_quiz')}
              />
            </button>
            {quizScore === null ? null : (
              <p className="phase1-quiz-result" role="status">
                <LocalizedMessage
                  resolution={message('demo.phase1.daily.quiz_result')}
                />{' '}
                {quizScore}/5
              </p>
            )}
          </article>

          <article className="phase1-subpanel">
            <h2>
              <LocalizedMessage
                resolution={message('demo.phase1.daily.monthly')}
              />
            </h2>
            <p className="phase1-stats__value">
              {phase1DailyActivity.monthlyScore}/
              {phase1DailyActivity.monthlyMaximum}
            </p>
          </article>
        </section>
      ) : null}

      {module === 'levels' ? (
        <section className="phase1-panel">
          <div className="phase1-levels">
            {phase1Levels.map((level) => (
              <button
                key={level.id}
                type="button"
                className="phase1-level"
                data-status={level.status}
                aria-current={
                  selectedLevelId === level.id ? 'true' : undefined
                }
                disabled={level.status === 'locked'}
                onClick={() => setSelectedLevelId(level.id)}
              >
                <strong>{level.title}</strong>
                <span>{level.status}</span>
                <span>{level.progressPercent}%</span>
              </button>
            ))}
          </div>
          <div className="phase1-rooms">
            <h2>
              {selectedLevel.title}:{' '}
              <LocalizedMessage
                resolution={message('demo.phase1.levels.rooms_title')}
              />
            </h2>
            <ul>
              {phase1LevelRooms.map((room) => (
                <li key={room.id} data-available={room.available}>
                  <strong>{room.title}</strong>
                  <p>{room.summary}</p>
                </li>
              ))}
            </ul>
          </div>
        </section>
      ) : null}

      {module === 'notices' ? (
        <section className="phase1-panel">
          <ul className="phase1-notices">
            {phase1Notices.map((notice) => (
              <li key={notice.id}>
                <p className="phase1-notices__meta">
                  <LocalizedMessage
                    resolution={message(noticeCategoryKeys[notice.category])}
                  />{' '}
                  · {notice.postedOn}
                </p>
                <h2>{notice.title}</h2>
                <p>{notice.body}</p>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  )
}
