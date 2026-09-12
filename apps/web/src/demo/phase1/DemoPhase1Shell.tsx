import type {
  MessageKey,
  MessageParameters,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useMemo, useRef, useState } from 'react'
import { LocalizedMessage } from '../../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../../localization/interfaceLocalization'
import { SUPPORTED_LOCALES } from '@global-english/localization'
import { AvatarSlot } from '../../ui/AvatarSlot'
import { TapToPlay } from '../../ui/TapToPlay'
import { OnboardingStepper } from '../../ui/OnboardingStepper'
import { AndroidDemoDownload } from '../AndroidDemoDownload'
import {
  GROVE_WELCOME_STORAGE_KEY,
  persistFirstRunDismissed,
  persistSpeakingComfort,
  readFirstRunDismissed,
} from '../../ui/firstRunPreference'
import { DemoTtsAdminSettings } from '../DemoTtsAdminSettings'
import { demoAzureTtsConfigured, speakDemoText, stopDemoSpeech } from '../demoTts'
import {
  PHASE1_FOCUS_AI_TOPICS,
  moduleFromHash,
  phase1AiTopics,
  phase1Attendance,
  phase1DailyActivity,
  phase1LevelRooms,
  phase1Levels,
  phase1Notices,
  type Phase1Level,
  type Phase1Module,
  type Phase1RoomId,
} from './phase1Fixtures'
import './DemoPhase1Shell.css'

export interface DemoPhase1ShellProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly compact?: boolean
  readonly demoOrganization?: string
  readonly forcedModule?: Phase1Module
  readonly onLocaleChange?: (locale: SupportedLocale) => void
}

const moduleTitleKeys: Readonly<Record<Phase1Module, MessageKey>> = {
  dashboard: 'demo.phase1.home.title',
  attendance: 'demo.phase1.nav.attendance',
  'ai-teacher': 'demo.phase1.nav.ai_teacher',
  'daily-activity': 'demo.phase1.nav.daily_activity',
  levels: 'demo.phase1.nav.levels',
  notices: 'demo.phase1.nav.notices',
  profile: 'demo.phase1.profile.title',
}

const noticeCategoryKeys = {
  announcement: 'demo.phase1.notices.category.announcement',
  trainer: 'demo.phase1.notices.category.trainer',
  course: 'demo.phase1.notices.category.course',
  holiday: 'demo.phase1.notices.category.holiday',
  motivation: 'demo.phase1.notices.category.motivation',
  event: 'demo.phase1.notices.category.event',
} as const satisfies Record<
  (typeof phase1Notices)[number]['category'],
  MessageKey
>

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

const roomTitleKeys = {
  'ai-teacher': 'demo.phase1.rooms.ai_teacher.title',
  audio: 'demo.phase1.rooms.audio.title',
  content: 'demo.phase1.rooms.content.title',
  conversation: 'demo.phase1.rooms.conversation.title',
  activity: 'demo.phase1.rooms.activity.title',
  assignment: 'demo.phase1.rooms.assignment.title',
  assessment: 'demo.phase1.rooms.assessment.title',
  unlock: 'demo.phase1.rooms.unlock.title',
} as const satisfies Record<Phase1RoomId, MessageKey>

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

const currentLevel =
  phase1Levels.find((level) => level.status === 'current') ?? phase1Levels[2]!

function roomDestination(roomId: Phase1RoomId): Phase1Module | 'practice' {
  if (roomId === 'ai-teacher') {
    return 'ai-teacher'
  }
  if (roomId === 'conversation') {
    return 'practice'
  }
  if (
    roomId === 'audio' ||
    roomId === 'content' ||
    roomId === 'activity' ||
    roomId === 'assignment'
  ) {
    return 'daily-activity'
  }
  return 'levels'
}

export function DemoPhase1Shell({
  locale,
  localizationPolicy,
  compact = false,
  demoOrganization,
  forcedModule,
  onLocaleChange,
}: DemoPhase1ShellProps) {
  const [hashModule, setHashModule] = useState<Phase1Module>(() =>
    moduleFromHash(globalThis.location?.hash ?? ''),
  )
  const module = forcedModule ?? hashModule
  const [aiLanguageLocale, setAiLanguageLocale] = useState(locale)
  const [aiUsesEnglish, setAiUsesEnglish] = useState(false)
  if (aiLanguageLocale !== locale) {
    setAiLanguageLocale(locale)
    setAiUsesEnglish(false)
  }
  const [activeTopicId, setActiveTopicId] = useState<
    (typeof PHASE1_FOCUS_AI_TOPICS)[number]
  >(PHASE1_FOCUS_AI_TOPICS[0])
  const [selectedLevelId, setSelectedLevelId] = useState(currentLevel.id)
  const [answers, setAnswers] = useState<Record<string, number>>({})
  const [quizSubmitted, setQuizSubmitted] = useState(false)
  const [audioPlayed, setAudioPlayed] = useState(false)
  const [audioSpeaking, setAudioSpeaking] = useState(false)
  const [usedBrowserFallback, setUsedBrowserFallback] = useState(false)
  const [welcomeOpen, setWelcomeOpen] = useState(
    () =>
      !compact &&
      forcedModule === undefined &&
      !readFirstRunDismissed(GROVE_WELCOME_STORAGE_KEY),
  )
  const welcomeTitleRef = useRef<HTMLHeadingElement | null>(null)

  const message = (key: MessageKey, parameters: MessageParameters = {}) =>
    resolveInterfaceMessage(key, locale, localizationPolicy, parameters)

  const aiLocale: SupportedLocale = aiUsesEnglish ? 'en-IN' : locale
  const aiMessage = (key: MessageKey) =>
    resolveInterfaceMessage(key, aiLocale, localizationPolicy)

  const activeTopic =
    phase1AiTopics.find((topic) => topic.id === activeTopicId) ??
    phase1AiTopics[0]!

  useEffect(() => {
    if (forcedModule !== undefined) {
      return
    }
    const onHash = () => setHashModule(moduleFromHash(globalThis.location.hash))
    globalThis.addEventListener('hashchange', onHash)
    return () => globalThis.removeEventListener('hashchange', onHash)
  }, [forcedModule])

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

  const listenAiReply = async () => {
    const reply = aiMessage(aiTopicReplyKeys[activeTopic.id]).text
    stopDemoSpeech()
    try {
      const source = await speakDemoText(reply, aiLocale)
      setUsedBrowserFallback(source === 'browser')
    } catch {
      setUsedBrowserFallback(true)
    }
  }

  const playDailyAudio = () => {
    stopDemoSpeech()
    setAudioSpeaking(true)
    void speakDemoText(phase1DailyActivity.modelLine, 'en-IN')
      .then((source) => {
        setAudioPlayed(true)
        setUsedBrowserFallback(source === 'browser')
      })
      .catch(() => setUsedBrowserFallback(true))
      .finally(() => setAudioSpeaking(false))
  }

  const go = (next: Phase1Module | 'practice' | 'learn') => {
    const url = new URL(globalThis.location.href)
    url.hash = next === 'dashboard' ? 'home' : next
    globalThis.history.pushState(null, '', url)
    if (next !== 'practice' && next !== 'learn') {
      setHashModule(next)
    }
    globalThis.dispatchEvent(new Event('hashchange'))
    globalThis.scrollTo(0, 0)
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
    phase1Levels.find((level) => level.id === selectedLevelId) ?? currentLevel

  const levelTitle = (level: Phase1Level) =>
    message(
      level.kind === 'pre'
        ? 'demo.phase1.levels.pre_level_title'
        : 'demo.phase1.levels.level_title',
      { number: level.number },
    )

  const currentLevelTitle = levelTitle(currentLevel)

  if (welcomeOpen && !compact) {
    return (
      <div
        aria-labelledby="onboarding-title"
        aria-modal="true"
        className="phase1-shell phase1-first-run"
        data-demo-phase1="synthetic"
        data-grove-first-run="open"
        role="dialog"
      >
        <h1 className="visually-hidden" id="destination-title" tabIndex={-1}>
          <LocalizedMessage
            resolution={message('demo.phase1.first_run.title')}
          />
        </h1>
        <div className="phase1-first-run__download">
          <p className="phase1-home__android-hint">
            <LocalizedMessage
              resolution={message('demo.phase1.home.android_download_hint')}
            />
          </p>
          <AndroidDemoDownload message={message} variant="inline" />
        </div>
        <OnboardingStepper
          locale={locale}
          locales={SUPPORTED_LOCALES}
          message={message}
          onLocaleChange={onLocaleChange}
          onSubmit={({ level }) => {
            if (level !== null) {
              persistSpeakingComfort(level)
            }
            persistFirstRunDismissed(GROVE_WELCOME_STORAGE_KEY)
            setWelcomeOpen(false)
          }}
          steps={['language', 'goal', 'level']}
          titleRef={welcomeTitleRef}
        />
      </div>
    )
  }

  return (
    <div
      className={
        compact ? 'phase1-shell phase1-shell--compact' : 'phase1-shell'
      }
      data-demo-phase1="synthetic"
    >
      {compact ? null : module === 'dashboard' ? (
        <header className="phase1-home">
          <div className="phase1-home__hello">
            <div className="phase1-home__hello-copy">
              <p className="phase1-home__greeting">
                <LocalizedMessage
                  resolution={message('demo.phase1.home.greeting')}
                />
              </p>
              <h1
                className="phase1-home__title"
                id="destination-title"
                tabIndex={-1}
              >
                <LocalizedMessage
                  resolution={message('demo.phase1.home.title')}
                />
              </h1>
            </div>
            <p className="phase1-home__streak">
              <span aria-hidden="true" className="phase1-home__streak-glyph">
                <svg
                  fill="none"
                  stroke="currentColor"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  viewBox="0 0 24 24"
                >
                  <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                </svg>
              </span>
              <LocalizedMessage
                resolution={message('demo.phase1.home.streak', {
                  count: phase1Attendance.streakDays,
                })}
              />
            </p>
            <AvatarSlot
              label={message('shell.avatar.teacher').text}
              size="sm"
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
                  resolution={message('demo.phase1.daily.audio_lesson_duration')}
                />
              </p>
            </div>
            <p className="phase1-home__lesson-title">
              <LocalizedMessage
                resolution={message('demo.phase1.daily.audio_lesson_title')}
              />
            </p>
            <p className="phase1-home__level">
              <LocalizedMessage
                resolution={message('demo.phase1.home.level_line', {
                  level: currentLevelTitle.text,
                })}
              />
            </p>
            <div
              className="phase1-home__progress"
              role="img"
              aria-label={message('demo.phase1.dashboard.level_progress').text}
            >
              <span className="phase1-home__progress-bar" />
            </div>
            <a href="#practice" className="ge-button ge-button--primary phase1-home__speak">
              <LocalizedMessage
                resolution={message('demo.phase1.home.speak')}
              />
            </a>
            <button
              type="button"
              className="ge-button phase1-home__cta"
              onClick={() => go('daily-activity')}
            >
              <span className="phase1-home__cta-label">
                <LocalizedMessage
                  resolution={message('demo.phase1.home.next_action')}
                />
              </span>
            </button>
            <p className="phase1-home__android-hint">
              <LocalizedMessage
                resolution={message('demo.phase1.home.android_download_hint')}
              />
            </p>
            <AndroidDemoDownload message={message} />
          </article>
          <nav
            className="phase1-grid"
            aria-label={message('demo.phase1.nav_label').text}
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
                aria-label={message(key).text}
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
          </nav>
          <aside className="phase1-home__tip">
            <span aria-hidden="true" className="phase1-home__tip-glyph">
              <svg
                fill="none"
                stroke="currentColor"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
                viewBox="0 0 24 24"
              >
                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
                <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07" />
              </svg>
            </span>
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
          <nav
            className="phase1-home__more"
            aria-label={message('demo.phase1.home.more_label').text}
          >
            <p className="phase1-home__more-label">
              <LocalizedMessage
                resolution={message('demo.phase1.home.more_label')}
              />
            </p>
            <ul>
              <li>
                <button
                  type="button"
                  className="phase1-text-link"
                  onClick={() => go('learn')}
                >
                  <LocalizedMessage
                    resolution={message('demo.phase1.home.lessons')}
                  />
                </button>
              </li>
              <li>
                <button
                  type="button"
                  className="phase1-text-link"
                  onClick={() => go('profile')}
                >
                  <LocalizedMessage
                    resolution={message('demo.phase1.nav.profile')}
                  />
                </button>
              </li>
            </ul>
          </nav>
        </header>
      ) : (
        <header className="phase1-screen-head">
          <button
            type="button"
            className="phase1-text-link phase1-back"
            onClick={() => go('dashboard')}
          >
            <LocalizedMessage resolution={message('demo.phase1.back')} />
          </button>
          <h1
            className="phase1-screen-head__title"
            id="destination-title"
            tabIndex={-1}
          >
            <LocalizedMessage resolution={message(moduleTitleKeys[module])} />
          </h1>
        </header>
      )}

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
          <div className="flex items-start gap-4">
            <AvatarSlot
              label={message('shell.avatar.teacher').text}
              slot="ai-teacher"
            />
            <blockquote
              className="phase1-ai-reply min-w-0 flex-1"
              lang={aiLocale}
            >
              <LocalizedMessage
                resolution={aiMessage(aiTopicReplyKeys[activeTopic.id])}
              />
              <TapToPlay
                idleLabel={
                  <LocalizedMessage resolution={message('shell.listen')} />
                }
                onPlay={listenAiReply}
                playingLabel={
                  <LocalizedMessage
                    resolution={message('shell.listen.playing')}
                  />
                }
                unavailableLabel={
                  <LocalizedMessage
                    resolution={message('shell.listen.unavailable')}
                  />
                }
              />
            </blockquote>
          </div>
          <div className="phase1-ai-topics">
            {PHASE1_FOCUS_AI_TOPICS.map((topicId) => (
              <button
                key={topicId}
                type="button"
                className={activeTopicId === topicId ? 'is-active' : undefined}
                onClick={() => setActiveTopicId(topicId)}
              >
                <LocalizedMessage
                  resolution={aiMessage(aiTopicLabelKeys[topicId])}
                />
              </button>
            ))}
          </div>
          <div className="phase1-ai-toolbar">
            <div
              className="phase1-ai-lang"
              role="group"
              aria-label={message('demo.phase1.ai.lang_group').text}
            >
              <button
                type="button"
                className={!aiUsesEnglish ? 'is-active' : undefined}
                onClick={() => setAiUsesEnglish(false)}
              >
                <LocalizedMessage
                  resolution={message(localeEndonymKeys[locale])}
                />
              </button>
              <button
                type="button"
                className={aiUsesEnglish ? 'is-active' : undefined}
                onClick={() => setAiUsesEnglish(true)}
              >
                <LocalizedMessage
                  resolution={message('demo.phase1.ai.lang_en')}
                />
              </button>
            </div>
          </div>
          <p className="phase1-muted">
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
            <p lang="en-IN">{phase1DailyActivity.modelLine}</p>
            <p className="phase1-muted">
              <LocalizedMessage
                resolution={message('demo.phase1.daily.audio_lesson_duration')}
              />
            </p>
            {usedBrowserFallback ? (
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
        </section>
      ) : null}

      {module === 'levels' ? (
          <LevelsHub
          compact={compact}
          message={message}
          onOpenRoom={(roomId) => {
            if (compact) {
              return
            }
            const next = roomDestination(roomId)
            go(next)
          }}
          selectedLevel={selectedLevel}
          selectedLevelId={selectedLevelId}
          setSelectedLevelId={setSelectedLevelId}
          levelTitle={levelTitle}
        />
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

      {module === 'profile' ? (
        <section className="phase1-panel phase1-profile">
          <article className="grid gap-2">
            <h2>
              <LocalizedMessage
                resolution={message('demo.phase1.profile.demo_status')}
              />
            </h2>
            <aside
              aria-label={message('demo.label').text}
              className="grid gap-2 rounded-xl border border-line/70 bg-surface-high p-4 text-sm leading-relaxed text-ink"
              data-demo-mode="synthetic-local"
            >
              <strong className="text-action">
                <LocalizedMessage resolution={message('demo.label')} />
              </strong>
              <span>
                <LocalizedMessage resolution={message('demo.notice')} />
              </span>
              {demoOrganization === undefined ? null : (
                <span className="text-muted">{demoOrganization}</span>
              )}
            </aside>
          </article>
          <ul className="phase1-profile__links">
            <li>
              <button
                type="button"
                className="phase1-text-link"
                onClick={() => go('attendance')}
              >
                <LocalizedMessage
                  resolution={message('demo.phase1.profile.attendance')}
                />
              </button>
            </li>
            <li>
              <button
                type="button"
                className="phase1-text-link"
                onClick={() => go('notices')}
              >
                <LocalizedMessage
                  resolution={message('demo.phase1.profile.notices')}
                />
              </button>
            </li>
          </ul>
          <DemoTtsAdminSettings
            locale={locale}
            localizationPolicy={localizationPolicy}
          />
          <article>
            <h2>
              <LocalizedMessage
                resolution={message('demo.phase1.profile.about_title')}
              />
            </h2>
            <p>
              <LocalizedMessage
                resolution={message('demo.phase1.profile.about_body')}
              />
            </p>
            <p className="phase1-muted">
              <LocalizedMessage
                resolution={message('demo.phase1.profile.course_structure')}
              />
            </p>
          </article>
        </section>
      ) : null}
    </div>
  )
}

interface LevelsHubProps {
  readonly compact: boolean
  readonly message: (
    key: MessageKey,
    parameters?: MessageParameters,
  ) => ReturnType<typeof resolveInterfaceMessage>
  readonly onOpenRoom: (roomId: Phase1RoomId) => void
  readonly selectedLevel: Phase1Level
  readonly selectedLevelId: string
  readonly setSelectedLevelId: (id: string) => void
  readonly levelTitle: (level: Phase1Level) => ReturnType<
    typeof resolveInterfaceMessage
  >
}

function LevelsHub({
  compact,
  message,
  onOpenRoom,
  selectedLevel,
  selectedLevelId,
  setSelectedLevelId,
  levelTitle,
}: LevelsHubProps) {
  const selectedTitle = levelTitle(selectedLevel)

  return (
    <section
      className={compact ? 'phase1-levels-hub phase1-levels-hub--compact' : 'phase1-levels-hub'}
      aria-label={message('demo.phase1.nav.levels').text}
    >
      {compact ? (
        <h2 className="phase1-levels-hub__title">
          <span lang={selectedTitle.resolvedLocale}>{selectedTitle.text}</span>
          {' · '}
          {selectedLevel.progressPercent}%
        </h2>
      ) : (
        <div className="phase1-levels">
          {phase1Levels.map((level) => {
            const title = levelTitle(level)
            return (
              <button
                key={level.id}
                type="button"
                className="phase1-level"
                data-status={level.status}
                aria-current={selectedLevelId === level.id ? 'true' : undefined}
                disabled={level.status === 'locked'}
                onClick={() => setSelectedLevelId(level.id)}
              >
                <strong lang={title.resolvedLocale}>{title.text}</strong>
                <span>
                  <LocalizedMessage
                    resolution={message(levelStatusKeys[level.status])}
                  />
                </span>
              </button>
            )
          })}
        </div>
      )}
      <div className="phase1-rooms">
        {compact ? null : (
          <h2>
            <span lang={selectedTitle.resolvedLocale}>{selectedTitle.text}</span>
            {': '}
            <LocalizedMessage
              resolution={message('demo.phase1.levels.rooms_title')}
            />
          </h2>
        )}
        <ul>
          {phase1LevelRooms.map((room) => (
            <li key={room.id} data-available={room.available}>
              {compact || !room.available ? (
                <span className="phase1-rooms__row">
                  <strong>
                    <LocalizedMessage
                      resolution={message(roomTitleKeys[room.id])}
                    />
                  </strong>
                  <span className="phase1-rooms__meta">
                    <LocalizedMessage
                      resolution={message(
                        room.available
                          ? 'demo.phase1.rooms.open'
                          : 'demo.phase1.levels.status.locked',
                      )}
                    />
                  </span>
                </span>
              ) : (
                <button
                  type="button"
                  className="phase1-rooms__row phase1-rooms__row--action"
                  onClick={() => onOpenRoom(room.id)}
                >
                  <strong>
                    <LocalizedMessage
                      resolution={message(roomTitleKeys[room.id])}
                    />
                  </strong>
                  <span className="phase1-rooms__meta">
                    <LocalizedMessage
                      resolution={message('demo.phase1.rooms.open')}
                    />
                  </span>
                </button>
              )}
            </li>
          ))}
        </ul>
      </div>
    </section>
  )
}
