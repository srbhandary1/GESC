import type {
  MessageKey,
  MessageParameters,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import type { SessionPartId } from '../../learner/LevelSessionsApi'
import { conceptLabel } from '../../learner/conceptLabels'
import { useEffect, useMemo, useRef, useState, type CSSProperties } from 'react'
import { LocalizedMessage } from '../../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../../localization/interfaceLocalization'
import { AvatarSlot } from '../../ui/AvatarSlot'
import { OnboardingStepper } from '../../ui/OnboardingStepper'
import {
  GROVE_WELCOME_STORAGE_KEY,
  persistFirstRunDismissed,
  persistSpeakingComfort,
  readFirstRunDismissed,
} from '../../ui/firstRunPreference'
import { demoAzureTtsConfigured, speakDemoText, stopDemoSpeech } from '../demoTts'
import {
  PHASE1_MODULES,
  moduleFromHash,
  phase1AiTopics,
  phase1Attendance,
  phase1DailyActivity,
  phase1Levels,
  phase1Notices,
  type Phase1Level,
  type Phase1Module,
} from './phase1Fixtures'
import { demoLevelFixture } from '../demoLevelFixtures'
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

const attendanceStatusKeys = {
  present: 'demo.phase1.attendance.status.present',
  absent: 'demo.phase1.attendance.status.absent',
  holiday: 'demo.phase1.attendance.status.holiday',
  upcoming: 'demo.phase1.attendance.status.upcoming',
} as const satisfies Record<
  (typeof phase1Attendance.days)[number]['status'],
  MessageKey
>

const levelStatusKeys = {
  locked: 'demo.phase1.levels.status.locked',
  current: 'demo.phase1.levels.status.current',
  completed: 'demo.phase1.levels.status.completed',
} as const satisfies Record<Phase1Level['status'], MessageKey>

const SESSION_PART_KEYS = {
  introduction: 'level.path.part.introduction',
  practice: 'level.path.part.practice',
  conversation: 'level.path.part.conversation',
  interaction: 'level.path.part.interaction',
} as const satisfies Record<SessionPartId, MessageKey>

const SESSION_PARTS: readonly SessionPartId[] = [
  'introduction',
  'practice',
  'conversation',
  'interaction',
]

const noticeTitleKeys = {
  n1: 'demo.phase1.notices.n1.title',
  n2: 'demo.phase1.notices.n2.title',
  n3: 'demo.phase1.notices.n3.title',
  n4: 'demo.phase1.notices.n4.title',
  n5: 'demo.phase1.notices.n5.title',
  n6: 'demo.phase1.notices.n6.title',
} as const satisfies Record<(typeof phase1Notices)[number]['id'], MessageKey>

const noticeBodyKeys = {
  n1: 'demo.phase1.notices.n1.body',
  n2: 'demo.phase1.notices.n2.body',
  n3: 'demo.phase1.notices.n3.body',
  n4: 'demo.phase1.notices.n4.body',
  n5: 'demo.phase1.notices.n5.body',
  n6: 'demo.phase1.notices.n6.body',
} as const satisfies Record<(typeof phase1Notices)[number]['id'], MessageKey>

const aiTopicLabelKeys = {
  welcome: 'demo.phase1.ai.topic.welcome.label',
  structure: 'demo.phase1.ai.topic.structure.label',
  level: 'demo.phase1.ai.topic.level.label',
  attendance: 'demo.phase1.ai.topic.attendance.label',
  score: 'demo.phase1.ai.topic.score.label',
  motivate: 'demo.phase1.ai.topic.motivate.label',
} as const satisfies Record<(typeof phase1AiTopics)[number]['id'], MessageKey>

const aiTopicReplyKeys = {
  welcome: 'demo.phase1.ai.topic.welcome.reply',
  structure: 'demo.phase1.ai.topic.structure.reply',
  level: 'demo.phase1.ai.topic.level.reply',
  attendance: 'demo.phase1.ai.topic.attendance.reply',
  score: 'demo.phase1.ai.topic.score.reply',
  motivate: 'demo.phase1.ai.topic.motivate.reply',
} as const satisfies Record<(typeof phase1AiTopics)[number]['id'], MessageKey>

const localeEndonymKeys: Readonly<Record<SupportedLocale, MessageKey>> = {
  'en-IN': 'locale.en_IN',
  'hi-IN': 'locale.hi_IN',
  'kn-IN': 'locale.kn_IN',
  'ml-IN': 'locale.ml_IN',
  'ta-IN': 'locale.ta_IN',
  'te-IN': 'locale.te_IN',
}

export function DemoPhase1Shell({
  locale,
  localizationPolicy,
}: DemoPhase1ShellProps) {
  const [module, setModule] = useState<Phase1Module>(() =>
    moduleFromHash(globalThis.location?.hash ?? ''),
  )
  const [aiLanguage, setAiLanguage] = useState<{
    readonly locale: SupportedLocale
    readonly usesEnglish: boolean
  }>(() => ({ locale, usesEnglish: false }))
  const [activeTopicId, setActiveTopicId] = useState(phase1AiTopics[0]!.id)
  const [selectedLevelId, setSelectedLevelId] = useState('level-1')
  const [answers, setAnswers] = useState<Record<string, number>>({})
  const [quizSubmitted, setQuizSubmitted] = useState(false)
  const [audioPlayed, setAudioPlayed] = useState(false)
  const [aiSpeaking, setAiSpeaking] = useState(false)
  const [audioSpeaking, setAudioSpeaking] = useState(false)
  const [usedBrowserFallback, setUsedBrowserFallback] = useState(false)
  const [welcomeOpen, setWelcomeOpen] = useState(
    () => !readFirstRunDismissed(GROVE_WELCOME_STORAGE_KEY),
  )
  const welcomeTitleRef = useRef<HTMLHeadingElement | null>(null)

  const message = (key: MessageKey, parameters: MessageParameters = {}) =>
    resolveInterfaceMessage(key, locale, localizationPolicy, parameters)

  if (aiLanguage.locale !== locale) {
    setAiLanguage({ locale, usesEnglish: false })
  }

  const aiUsesEnglish =
    aiLanguage.locale === locale && aiLanguage.usesEnglish
  const aiLocale: SupportedLocale = aiUsesEnglish ? 'en-IN' : locale
  const aiMessage = (key: MessageKey) =>
    resolveInterfaceMessage(key, aiLocale, localizationPolicy)

  const activeTopic =
    phase1AiTopics.find((topic) => topic.id === activeTopicId) ??
    phase1AiTopics[0]!

  useEffect(() => {
    const onHash = () => setModule(moduleFromHash(globalThis.location.hash))
    globalThis.addEventListener('hashchange', onHash)
    return () => globalThis.removeEventListener('hashchange', onHash)
  }, [])

  useEffect(() => {
    return () => {
      stopDemoSpeech()
    }
  }, [])

  useEffect(() => {
    let cancelled = false
    void demoAzureTtsConfigured().then((configured) => {
      if (!cancelled && !configured) {
        setUsedBrowserFallback(true)
      }
    })
    return () => {
      cancelled = true
    }
  }, [])

  const listenAiReply = () => {
    const reply = aiMessage(aiTopicReplyKeys[activeTopic.id]).text
    stopDemoSpeech()
    setAiSpeaking(true)
    void speakDemoText(reply, aiLocale)
      .then((source) => {
        setUsedBrowserFallback(source === 'browser')
      })
      .catch(() => setUsedBrowserFallback(true))
      .finally(() => setAiSpeaking(false))
  }

  const playDailyAudio = () => {
    const title = message('demo.phase1.daily.audio_lesson_title').text
    const summary = message('demo.phase1.daily.audio_lesson_summary').text
    stopDemoSpeech()
    setAudioSpeaking(true)
    void speakDemoText(`${title}. ${summary}`, locale)
      .then((source) => {
        setAudioPlayed(true)
        setUsedBrowserFallback(source === 'browser')
      })
      .catch(() => setUsedBrowserFallback(true))
      .finally(() => setAudioSpeaking(false))
  }

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

  const selectedLevel =
    phase1Levels.find((level) => level.id === selectedLevelId) ??
    phase1Levels[2]!

  const levelTitle = (level: Phase1Level) =>
    message(
      level.kind === 'pre'
        ? 'demo.phase1.levels.pre_level_title'
        : 'demo.phase1.levels.level_title',
      { number: level.number },
    )

  if (welcomeOpen) {
    return (
      <div
        className="phase1-shell phase1-first-run"
        data-demo-phase1="synthetic"
      >
        <h1 className="visually-hidden" id="destination-title" tabIndex={-1}>
          <LocalizedMessage
            resolution={message('demo.phase1.first_run.title')}
          />
        </h1>
        <OnboardingStepper
          locale={locale}
          message={message}
          onSkip={() => {
            persistFirstRunDismissed(GROVE_WELCOME_STORAGE_KEY)
            setWelcomeOpen(false)
            go('levels')
          }}
          onSubmit={(selection) => {
            if (selection.level !== null) {
              persistSpeakingComfort(selection.level)
            }
            persistFirstRunDismissed(GROVE_WELCOME_STORAGE_KEY)
            setWelcomeOpen(false)
          }}
          skipLabel={message('demo.phase1.first_run.skip')}
          steps={['goal', 'level']}
          titleRef={welcomeTitleRef}
        />
      </div>
    )
  }

  const continueLevel =
    phase1Levels.find((level) => level.status === 'current') ?? selectedLevel
  const preLevels = phase1Levels.filter((level) => level.kind === 'pre')
  const curriculumLevels = phase1Levels.filter((level) => level.kind === 'level')
  const heroLevel =
    selectedLevel.kind === 'level' ? selectedLevel : continueLevel
  const heroFixture = demoLevelFixture(heroLevel.number)
  const heroConcept =
    conceptLabel(heroFixture?.concept_key) ??
    message('level.path.ready').text

  return (
    <div className="phase1-shell" data-demo-phase1="synthetic">
      <h1
        className={module === 'levels' ? 'visually-hidden' : 'phase1-shell__title'}
        id="destination-title"
        tabIndex={-1}
      >
        <LocalizedMessage resolution={message(moduleTitleKeys[module])} />
      </h1>

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
        <header className="phase1-home">
          <div className="phase1-home__hello">
            <div className="phase1-home__hello-copy">
              <p className="phase1-home__greeting">
                <LocalizedMessage
                  resolution={message('demo.phase1.home.greeting')}
                />
              </p>
              <p className="phase1-home__title">
                <LocalizedMessage
                  resolution={message('demo.phase1.home.title')}
                />
              </p>
            </div>
            <p className="phase1-home__streak">
              <LocalizedMessage
                resolution={message('demo.phase1.home.streak', { count: 3 })}
              />
            </p>
            <AvatarSlot
              label={message('shell.mira.name').text}
              size="lg"
              slot="home-teacher"
            />
          </div>
          <article className="phase1-home__lesson">
            <div className="phase1-home__lesson-meta">
              <p className="phase1-home__eyebrow">
                <LocalizedMessage
                  resolution={message('demo.phase1.home.hero_label')}
                />
              </p>
              <p className="phase1-home__duration">
                <LocalizedMessage
                  resolution={message('demo.phase1.dashboard.level_current')}
                />
              </p>
            </div>
            <p className="phase1-home__lesson-title">
              <LocalizedMessage
                resolution={message('demo.phase1.home.next_action')}
              />
            </p>
            <p className="phase1-home__level">
              <LocalizedMessage
                resolution={message('demo.phase1.home.level_line', {
                  level: levelTitle(selectedLevel).text,
                })}
              />
            </p>
            <div
              className="phase1-home__progress"
              aria-hidden="true"
            >
              <span className="phase1-home__progress-bar" />
            </div>
            <div className="ge-continue-hero__actions">
            <button
              className="ge-button ge-button--primary phase1-home__cta"
              type="button"
              onClick={() => go('levels')}
            >
              <span className="phase1-home__cta-label">
                <LocalizedMessage
                  resolution={message('demo.phase1.home.next_action')}
                />
              </span>
            </button>
            <a href="#speak" className="ge-button ge-button--ghost phase1-home__speak">
              <LocalizedMessage
                resolution={message('conversation.start')}
              />
            </a>
            </div>
          </article>
          <aside className="phase1-home__tip">
            <div>
              <p className="phase1-home__tip-title">
                <LocalizedMessage
                  resolution={message('demo.phase1.home.tip_title')}
                />
              </p>
              <p className="phase1-home__tip-body">
                <LocalizedMessage
                  resolution={message('demo.phase1.home.tip_body')}
                />
              </p>
            </div>
          </aside>
        </header>
      ) : null}

      {module === 'dashboard' ? (
        <section
          className="phase1-grid"
          aria-label={message('demo.phase1.nav.dashboard').text}
        >
          {(
            [
              [
                'attendance',
                'demo.phase1.dashboard.attendance',
                `${phase1Attendance.percentage}%`,
              ],
              [
                'ai-teacher',
                'demo.phase1.dashboard.ai_teacher',
                message('demo.phase1.dashboard.level_current').text,
              ],
              [
                'daily-activity',
                'demo.phase1.dashboard.daily',
                `${phase1DailyActivity.monthlyScore}/${phase1DailyActivity.monthlyMaximum}`,
              ],
              [
                'levels',
                'demo.phase1.dashboard.levels',
                message('demo.phase1.dashboard.level_progress').text,
              ],
              [
                'notices',
                'demo.phase1.dashboard.notices',
                String(phase1Notices.length),
              ],
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
            ·{' '}
            <LocalizedMessage
              resolution={message('demo.phase1.attendance.month_label')}
            />
          </h3>
          <ol className="phase1-attendance-days">
            {phase1Attendance.days.slice(0, 10).map((day) => (
              <li key={day.date} data-status={day.status}>
                <span>{day.date.slice(-2)}</span>
                <span>
                  <LocalizedMessage
                    resolution={message(attendanceStatusKeys[day.status])}
                  />
                </span>
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
            {usedBrowserFallback ? (
              <p className="phase1-muted" role="status">
                <LocalizedMessage
                  resolution={message('demo.speech.azure_unavailable')}
                />
              </p>
            ) : null}
            <div
              className="phase1-ai-lang"
              role="group"
              aria-label={message('demo.phase1.ai.lang_group').text}
            >
              <button
                type="button"
                className={!aiUsesEnglish ? 'is-active' : undefined}
                onClick={() => setAiLanguage({ locale, usesEnglish: false })}
              >
                <LocalizedMessage
                  resolution={message(localeEndonymKeys[locale])}
                />
              </button>
              <button
                type="button"
                className={aiUsesEnglish ? 'is-active' : undefined}
                onClick={() => setAiLanguage({ locale, usesEnglish: true })}
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
                <LocalizedMessage
                  resolution={aiMessage(aiTopicLabelKeys[topic.id])}
                />
              </button>
            ))}
          </div>
          <blockquote className="phase1-ai-reply" lang={aiLocale}>
            <LocalizedMessage
              resolution={aiMessage(aiTopicReplyKeys[activeTopic.id])}
            />
          </blockquote>
          <button
            type="button"
            className="ge-button ge-button--primary"
            onClick={listenAiReply}
            disabled={aiSpeaking}
            aria-busy={aiSpeaking}
          >
            <LocalizedMessage resolution={message('demo.phase1.ai.listen')} />
          </button>
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
            <p>
              <LocalizedMessage
                resolution={message('demo.phase1.daily.audio_lesson_title')}
              />
            </p>
            <p className="phase1-muted">
              <LocalizedMessage
                resolution={message('demo.phase1.daily.audio_lesson_summary')}
              />{' '}
              ·{' '}
              <LocalizedMessage
                resolution={message('demo.phase1.daily.audio_lesson_duration')}
              />
            </p>
            {usedBrowserFallback && module === 'daily-activity' ? (
              <p className="phase1-muted" role="status">
                <LocalizedMessage
                  resolution={message('demo.speech.azure_unavailable')}
                />
              </p>
            ) : null}
            <button
              type="button"
              className="ge-button ge-button--primary"
              onClick={playDailyAudio}
              disabled={audioSpeaking}
              aria-busy={audioSpeaking}
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
            <h2>
              <LocalizedMessage
                resolution={message('demo.phase1.daily.quiz_title')}
              />
            </h2>
            <p className="phase1-muted">
              <LocalizedMessage
                resolution={message('demo.phase1.daily.quiz_rules')}
              />
            </p>
            <ol className="phase1-quiz" lang="en-IN">
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
        <section className="phase1-learn" aria-label={message('nav.learn').text}>
          <article className="ge-continue-hero">
            <p className="ge-continue-hero__eyebrow">
              <LocalizedMessage
                resolution={message('demo.phase1.home.hero_label')}
              />
            </p>
            <h2 className="ge-continue-hero__title">{heroConcept}</h2>
            <p className="ge-continue-hero__meta">
              <span lang={levelTitle(heroLevel).resolvedLocale}>
                {levelTitle(heroLevel).text}
              </span>
              {heroLevel.status === 'current' ? (
                <span>{heroLevel.progressPercent}%</span>
              ) : null}
              <span>
                <LocalizedMessage resolution={message('level.path.open')} />
              </span>
            </p>
            {heroLevel.status === 'current' ? (
              <div
                className="ge-continue-hero__progress"
                aria-hidden="true"
              >
                <span
                  className="ge-continue-hero__progress-bar"
                  style={
                    {
                      '--ge-continue-progress': `${heroLevel.progressPercent}%`,
                    } as CSSProperties
                  }
                />
              </div>
            ) : null}
            <div className="ge-continue-hero__actions">
              {heroLevel.status === 'locked' ? (
                <button
                  className="ge-button ge-button--primary"
                  type="button"
                  disabled
                >
                  <LocalizedMessage resolution={message('level.path.open')} />
                </button>
              ) : (
                <a className="ge-button ge-button--primary" href="#learn">
                  <LocalizedMessage resolution={message('level.path.open')} />
                </a>
              )}
              <a
                className="ge-button ge-button--ghost"
                href="#speak"
              >
                <LocalizedMessage
                  resolution={message('conversation.start')}
                />
              </a>
            </div>
          </article>

          {preLevels.length > 0 ? (
            <section
              className="ge-chip-section"
              aria-label={message('demo.phase1.levels.pre_level_title', {
                number: '1',
              }).text}
            >
              <ul className="ge-chip-row">
                {preLevels.map((level) => {
                  const title = levelTitle(level)
                  const current = selectedLevelId === level.id
                  return (
                    <li key={level.id}>
                      <button
                        type="button"
                        className="ge-level-chip"
                        data-status={level.status}
                        aria-current={current ? 'true' : undefined}
                        onClick={() => setSelectedLevelId(level.id)}
                      >
                        <span lang={title.resolvedLocale}>{title.text}</span>
                        <span className="visually-hidden">
                          <LocalizedMessage
                            resolution={message(levelStatusKeys[level.status])}
                          />
                        </span>
                      </button>
                    </li>
                  )
                })}
              </ul>
            </section>
          ) : null}

          <section
            className="ge-chip-section"
            aria-label={message('level.path.title').text}
          >
            <h2 className="ge-chip-section__title">
              <LocalizedMessage resolution={message('level.path.title')} />
            </h2>
            <ul className="ge-chip-row">
              {curriculumLevels.map((level) => {
                const title = levelTitle(level)
                const fixture = demoLevelFixture(level.number)
                const concept = conceptLabel(fixture?.concept_key)
                const current = selectedLevelId === level.id
                return (
                  <li key={level.id}>
                    <button
                      type="button"
                      className="ge-level-chip ge-level-chip--rich"
                      data-status={level.status}
                      aria-current={current ? 'true' : undefined}
                      disabled={level.status === 'locked'}
                      onClick={() => setSelectedLevelId(level.id)}
                    >
                      <span className="ge-level-chip__label" lang={title.resolvedLocale}>
                        {title.text}
                      </span>
                      {concept ? (
                        <span className="ge-level-chip__concept">{concept}</span>
                      ) : null}
                      {level.status === 'current' ? (
                        <span>{level.progressPercent}%</span>
                      ) : null}
                      <span className="visually-hidden">
                        <LocalizedMessage
                          resolution={message(levelStatusKeys[level.status])}
                        />
                      </span>
                    </button>
                  </li>
                )
              })}
            </ul>
          </section>

          <section
            className="ge-chip-section"
            aria-label={message('level.path.sessions_nav').text}
          >
            <h2 className="ge-chip-section__title">
              <LocalizedMessage
                resolution={message('level.path.sessions_nav')}
              />
            </h2>
            <ul className="ge-chip-row">
              {SESSION_PARTS.map((part) => (
                <li key={part}>
                  <a
                    className="ge-room-chip"
                    data-status={part === 'introduction' ? 'active' : 'available'}
                    href="#learn"
                  >
                    <LocalizedMessage
                      resolution={message(SESSION_PART_KEYS[part])}
                    />
                  </a>
                </li>
              ))}
            </ul>
          </section>
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
                <h2>
                  <LocalizedMessage
                    resolution={message(noticeTitleKeys[notice.id])}
                  />
                </h2>
                <p>
                  <LocalizedMessage
                    resolution={message(noticeBodyKeys[notice.id])}
                  />
                </p>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  )
}
