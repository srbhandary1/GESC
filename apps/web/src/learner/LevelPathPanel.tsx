import { type AudioControlLabels } from '@global-english/design-system'
import {
  type MessageKey,
  type ResolutionPolicy,
  type SupportedLocale,
} from '@global-english/localization'
import { useEffect, useRef, useState } from 'react'
import { ConversationJourney } from '../conversation/ConversationJourney'
import type {
  ConversationJourneyApi,
  FriendSession,
} from '../conversation/ConversationJourneyApi'
import { DemoSpeechPlayer } from '../demo/DemoSpeechPlayer'
import { isDemoSpeechUrl } from '../demo/browserSpeech'
import { TutorPresence } from '../tutor/TutorPresence'
import {
  INTERFACE_RESOLUTION_POLICY,
  resolveInterfaceMessage,
} from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import type {
  LevelSessionDetail,
  LevelSessionsApi,
  LevelSummary,
  SessionPartId,
  CheckSubmissionResult,
} from './LevelSessionsApi'
import { ModelAudioPlayer } from './ModelAudioPlayer'
import './LevelSessionJourney.css'

const PARTS: SessionPartId[] = [
  'introduction',
  'practice',
  'conversation',
  'interaction',
]

const PART_MESSAGE_KEYS: Record<SessionPartId, MessageKey> = {
  introduction: 'level.path.part.introduction',
  practice: 'level.path.part.practice',
  conversation: 'level.path.part.conversation',
  interaction: 'level.path.part.interaction',
}

const PART_HINT_KEYS: Record<SessionPartId, MessageKey> = {
  introduction: 'level.path.part.introduction.hint',
  practice: 'level.path.part.practice.hint',
  conversation: 'level.path.part.conversation.hint',
  interaction: 'level.path.part.interaction.hint',
}

const PART_PREREQUISITE: Record<SessionPartId, SessionPartId | null> = {
  introduction: null,
  practice: 'introduction',
  conversation: 'practice',
  interaction: 'conversation',
}

function isPartUnlocked(
  part: SessionPartId,
  completions: Record<SessionPartId, boolean>,
): boolean {
  const required = PART_PREREQUISITE[part]
  return required === null || completions[required] === true
}

const CONCEPT_LABELS: Record<string, string> = {
  can: 'Can — ability and permission',
}

function firstNonEmpty(
  values: readonly string[] | undefined,
): string | undefined {
  return values?.map((value) => value.trim()).find((value) => value !== '')
}

function tutorOpeningLine(detail: LevelSessionDetail): string | undefined {
  const fallbacks = detail.talk?.fallback_turns ?? []
  const spokenQuestion = fallbacks
    .map((turn) => turn.trim())
    .find((turn) => turn.includes('?'))

  if (spokenQuestion !== undefined) {
    return spokenQuestion
  }

  return (
    firstNonEmpty(fallbacks) ??
    firstNonEmpty(detail.target_patterns) ??
    firstNonEmpty(detail.talk?.elicitation_goals)
  )
}

function conceptLabel(conceptKey: string | null): string | null {
  if (conceptKey === null || conceptKey === '') {
    return null
  }

  return CONCEPT_LABELS[conceptKey] ?? conceptKey.replaceAll('_', ' ')
}

function isAbortError(error: unknown): boolean {
  return (
    (error instanceof DOMException && error.name === 'AbortError') ||
    (error instanceof Error && error.name === 'AbortError')
  )
}

function lessonAudioLabels(
  message: (key: MessageKey, parameters?: Record<string, string>) => {
    readonly text: string
  },
): AudioControlLabels {
  return {
    endedStatus: message('learner.audio.ended_status').text,
    error: message('learner.audio.error').text,
    errorStatus: message('learner.audio.error_status').text,
    loading: message('learner.audio.loading').text,
    loadingStatus: message('learner.audio.loading_status').text,
    pause: message('learner.audio.pause').text,
    pausedStatus: message('learner.audio.paused_status').text,
    play: message('learner.audio.play').text,
    playingStatus: message('learner.audio.playing_status').text,
    readyStatus: message('learner.audio.ready_status').text,
    region: message('learner.audio.region').text,
    replay: message('learner.audio.replay').text,
    resume: message('learner.audio.resume').text,
    unavailable: message('learner.audio.unavailable').text,
    unavailableStatus: message('learner.audio.unavailable_status').text,
  }
}

export interface LevelPathPanelProps {
  api: LevelSessionsApi
  locale?: SupportedLocale
  localizationPolicy?: ResolutionPolicy
  onBack?: () => void
  onOpenSpeakingPractice?: (session: FriendSession) => void
  onOpenRoleplay?: () => void
  conversationApi?: ConversationJourneyApi
  createClientId?: () => string
  demoMode?: boolean
}

export function LevelPathPanel({
  api,
  locale = 'kn-IN',
  localizationPolicy = INTERFACE_RESOLUTION_POLICY,
  onBack,
  onOpenSpeakingPractice,
  onOpenRoleplay,
  conversationApi,
  createClientId = createRandomClientId,
  demoMode = false,
}: LevelPathPanelProps) {
  const message = (key: MessageKey, parameters = {}) =>
    resolveInterfaceMessage(key, locale, localizationPolicy, parameters)
  const [levels, setLevels] = useState<LevelSummary[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [selectedLevel, setSelectedLevel] = useState<number | null>(null)

  useEffect(() => {
    const controller = new AbortController()
    api
      .listLevels(controller.signal)
      .then((payload) => {
        if (controller.signal.aborted) {
          return
        }
        setLevels(payload.levels)
      })
      .catch((cause: unknown) => {
        if (controller.signal.aborted || isAbortError(cause)) {
          return
        }
        setError('levels_unavailable')
      })
    return () => {
      controller.abort()
    }
  }, [api])

  if (selectedLevel !== null) {
    return (
      <LevelSessionJourney
        api={api}
        levelNumber={selectedLevel}
        locale={locale}
        localizationPolicy={localizationPolicy}
        onBack={() => setSelectedLevel(null)}
        onOpenSpeakingPractice={onOpenSpeakingPractice}
        onOpenRoleplay={onOpenRoleplay}
        conversationApi={conversationApi}
        createClientId={createClientId}
        demoMode={demoMode}
      />
    )
  }

  const ready = (levels ?? []).filter(
    (level) =>
      level.availability === 'available' &&
      level.entitlement_access !== 'upgrade_required',
  )
  const upgradeRequired = (levels ?? []).filter(
    (level) =>
      level.availability === 'available' &&
      level.entitlement_access === 'upgrade_required',
  )
  const pending = (levels ?? []).filter(
    (level) => level.availability !== 'available',
  )

  return (
    <section className="level-path" aria-labelledby="level-path-title" lang={locale}>
      <header className="level-path__intro">
        {onBack ? (
          <button type="button" className="level-path__back" onClick={onBack}>
            <LocalizedMessage resolution={message('level.path.back')} />
          </button>
        ) : null}
        <p className="level-path__eyebrow">
          <LocalizedMessage resolution={message('level.path.eyebrow')} />
        </p>
        <h2 id="level-path-title">
          <LocalizedMessage resolution={message('level.path.title')} />
        </h2>
        <p>
          <LocalizedMessage resolution={message('level.path.lead')} />
        </p>
      </header>

      {error ? (
        <p className="level-path__status" role="alert">
          <LocalizedMessage resolution={message('level.path.error')} />
        </p>
      ) : null}

      {levels === null && error === null ? (
        <p className="level-path__status">
          <LocalizedMessage resolution={message('level.path.loading')} />
        </p>
      ) : null}

      {ready.length > 0 ? (
        <div className="level-path__ready">
          <h3>
            <LocalizedMessage resolution={message('level.path.ready_heading')} />
          </h3>
          <ol className="level-path__list">
            {ready.map((level) => {
              const concept =
                conceptLabel(level.concept_key) ??
                message('level.path.ready').text
              return (
                <li key={level.level_number}>
                  <button
                    type="button"
                    className="level-path__item"
                    onClick={() => setSelectedLevel(level.level_number)}
                  >
                    <span className="level-path__item-index" aria-hidden="true">
                      {level.level_number}
                    </span>
                    <span className="level-path__item-copy">
                      <strong>
                        <LocalizedMessage
                          resolution={message('level.path.level_label', {
                            number: String(level.level_number),
                          })}
                        />
                      </strong>
                      <span>{concept}</span>
                    </span>
                    <span className="level-path__item-action">
                      <LocalizedMessage
                        resolution={message('level.path.open_short')}
                      />
                    </span>
                  </button>
                </li>
              )
            })}
          </ol>
        </div>
      ) : levels !== null && error === null ? (
        <p className="level-path__empty">
          <LocalizedMessage resolution={message('level.path.empty')} />
        </p>
      ) : null}

      {upgradeRequired.length > 0 ? (
        <div className="level-path__later">
          <h3>
            <LocalizedMessage
              resolution={message('subscription.level_locked')}
            />
          </h3>
          <ul className="level-path__pending">
            {upgradeRequired.map((level) => (
              <li
                key={level.level_number}
                className="level-path__pending-item"
              >
                <LocalizedMessage
                  resolution={message('level.path.level_label', {
                    number: String(level.level_number),
                  })}
                />
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {pending.length > 0 ? (
        <details className="level-path__later">
          <summary>
            <LocalizedMessage
              resolution={message('level.path.coming_next', {
                count: String(pending.length),
              })}
            />
          </summary>
          <p className="level-path__status">
            <LocalizedMessage
              resolution={message('level.path.coming_next_body')}
            />
          </p>
          <ul className="level-path__pending">
            {pending.map((level) => (
              <li
                key={level.level_number}
                className="level-path__pending-item"
              >
                <LocalizedMessage
                  resolution={message('level.path.level_label', {
                    number: String(level.level_number),
                  })}
                />
              </li>
            ))}
          </ul>
        </details>
      ) : null}
    </section>
  )
}

interface LevelSessionJourneyProps {
  api: LevelSessionsApi
  levelNumber: number
  locale: SupportedLocale
  localizationPolicy: ResolutionPolicy
  onBack: () => void
  onOpenSpeakingPractice?: (session: FriendSession) => void
  onOpenRoleplay?: () => void
  conversationApi?: ConversationJourneyApi
  createClientId: () => string
  demoMode: boolean
}

function LevelSessionJourney({
  api,
  levelNumber,
  locale,
  localizationPolicy,
  onBack,
  onOpenSpeakingPractice,
  onOpenRoleplay,
  conversationApi,
  createClientId,
  demoMode,
}: LevelSessionJourneyProps) {
  const message = (key: MessageKey, parameters = {}) =>
    resolveInterfaceMessage(key, locale, localizationPolicy, parameters)
  const [partId, setPartId] = useState<SessionPartId>('introduction')
  const [detail, setDetail] = useState<LevelSessionDetail | null>(null)
  const [completions, setCompletions] = useState<Record<SessionPartId, boolean>>(
    {
      introduction: false,
      practice: false,
      conversation: false,
      interaction: false,
    },
  )
  const [error, setError] = useState<string | null>(null)
  const [checkResult, setCheckResult] = useState<CheckSubmissionResult | null>(
    null,
  )
  const [answers, setAnswers] = useState<Record<string, number>>({})
  const [busy, setBusy] = useState(false)
  const [conversationNote, setConversationNote] = useState<string | null>(null)
  const [audioHeard, setAudioHeard] = useState(false)
  const [speakingOpened, setSpeakingOpened] = useState(false)
  const [boundSession, setBoundSession] = useState<FriendSession | null>(null)
  const [lessonPlaying, setLessonPlaying] = useState(false)
  const autoStarted = useRef(false)

  useEffect(() => {
    let cancelled = false
    api
      .listSessions(levelNumber)
      .then((payload) => {
        if (cancelled) {
          return
        }
        const next = {
          introduction: false,
          practice: false,
          conversation: false,
          interaction: false,
        }
        for (const session of payload.sessions) {
          if (session.part_id in next) {
            next[session.part_id as SessionPartId] = session.completed
          }
        }
        setCompletions(next)
      })
      .catch(() => {
        /* detail fetch remains authoritative for the open session */
      })
    return () => {
      cancelled = true
    }
  }, [api, levelNumber])

  useEffect(() => {
    const controller = new AbortController()
    api
      .sessionDetail(levelNumber, partId, controller.signal)
      .then((payload) => {
        if (controller.signal.aborted) {
          return
        }
        setDetail(payload)
        setCompletions((current) => ({
          ...current,
          [payload.part_id]: payload.completed,
        }))
        setError(null)
      })
      .catch((cause: unknown) => {
        if (controller.signal.aborted || isAbortError(cause)) {
          return
        }
        setError('session_unavailable')
      })
    return () => {
      controller.abort()
    }
  }, [api, levelNumber, partId])

  const visibleDetail = detail?.part_id === partId ? detail : null

  function selectPart(part: SessionPartId) {
    if (!isPartUnlocked(part, completions)) {
      setError('session_locked')
      return
    }
    setPartId(part)
    setCheckResult(null)
    setConversationNote(null)
    setAnswers({})
    setError(null)
    setAudioHeard(false)
    setSpeakingOpened(false)
    setBoundSession(null)
    setLessonPlaying(false)
    autoStarted.current = false
  }

  async function complete() {
    if (partId === 'introduction' && visibleDetail?.audio_stream_path && !audioHeard) {
      setError('listen_required')
      return
    }
    if (
      partId === 'practice' &&
      visibleDetail?.check !== null &&
      checkResult?.passed !== true
    ) {
      setError('check_required')
      return
    }
    if (
      (partId === 'conversation' || partId === 'interaction') &&
      !speakingOpened
    ) {
      setError('speak_required')
      return
    }
    setBusy(true)
    try {
      await api.completeSession(levelNumber, partId, createClientId())
      const refreshed = await api.sessionDetail(levelNumber, partId)
      setDetail(refreshed)
      setCompletions((current) => ({
        ...current,
        [partId]: true,
      }))
    } catch {
      setError('complete_failed')
    } finally {
      setBusy(false)
    }
  }

  async function submitCheck() {
    setBusy(true)
    try {
      const result = await api.submitCheck(
        levelNumber,
        partId,
        answers,
        createClientId(),
      )
      setCheckResult(result)
    } catch {
      setError('check_failed')
    } finally {
      setBusy(false)
    }
  }

  async function startTalk() {
    setBusy(true)
    try {
      const session = await api.startConversation(
        levelNumber,
        partId,
        locale,
        createClientId(),
      )
      setSpeakingOpened(true)
      setBoundSession(session)
      setConversationNote(
        visibleDetail?.part_id === 'introduction' ||
          visibleDetail?.part_id === 'practice'
          ? message('level.path.tutor_ready_note').text
          : message('level.path.free_speech_ready_note').text,
      )
      onOpenSpeakingPractice?.(session)
    } catch {
      setConversationNote(message('level.path.unavailable').text)
    } finally {
      setBusy(false)
    }
  }

  function markLessonAudioHeard() {
    setAudioHeard(true)
    setLessonPlaying(true)
  }

  function handleLessonAudioEnded() {
    setAudioHeard(true)
    setLessonPlaying(false)
    if (!autoStarted.current) {
      autoStarted.current = true
      void startTalk()
    }
  }

  return (
    <section
      className="level-session"
      aria-labelledby="level-session-title"
      lang={locale}
    >
      <header className="level-session__hero">
        <button type="button" className="level-session__back" onClick={onBack}>
          <LocalizedMessage resolution={message('level.path.all_levels')} />
        </button>
        <p className="level-session__eyebrow">
          <LocalizedMessage
            resolution={message('level.path.level_label', {
              number: String(levelNumber),
            })}
          />
        </p>
        <h2 id="level-session-title">
          {detail?.objective ?? message('level.path.objective_default').text}
        </h2>
        <p>
          <LocalizedMessage resolution={message('level.path.session_lead')} />
        </p>
      </header>

      <nav
        aria-label={message('level.path.sessions_nav').text}
        className="level-session__nav"
      >
        {PARTS.map((part) => {
          const unlocked = isPartUnlocked(part, completions)
          return (
            <button
              key={part}
              type="button"
              aria-current={part === partId ? 'true' : undefined}
              aria-disabled={unlocked ? undefined : 'true'}
              className={[
                'level-session__tab',
                part === partId ? 'level-session__tab--active' : '',
                completions[part] ? 'level-session__tab--done' : '',
                unlocked ? '' : 'level-session__tab--locked',
              ]
                .filter(Boolean)
                .join(' ')}
              onClick={() => selectPart(part)}
            >
              <LocalizedMessage resolution={message(PART_MESSAGE_KEYS[part])} />
            </button>
          )
        })}
      </nav>

      {error === 'session_locked' ? (
        <p className="level-session__status" role="alert">
          <LocalizedMessage resolution={message('level.path.locked')} />
        </p>
      ) : null}

      {error === 'session_unavailable' && visibleDetail === null ? (
        <p className="level-session__status" role="alert">
          <LocalizedMessage resolution={message('level.path.session_error')} />
        </p>
      ) : null}

      {error === 'complete_failed' ||
      error === 'check_failed' ||
      error === 'check_required' ||
      error === 'listen_required' ||
      error === 'speak_required' ? (
        <p className="level-session__status" role="alert">
          <LocalizedMessage
            resolution={message(
              error === 'check_required'
                ? 'level.path.check_required'
                : error === 'listen_required'
                  ? 'level.path.listen_required'
                  : error === 'speak_required'
                    ? 'level.path.speak_required'
                    : 'level.path.action_error',
            )}
          />
        </p>
      ) : null}

      {visibleDetail ? (
        <>
          <div className="level-session__panel">
            <h3>
              <LocalizedMessage
                resolution={message(PART_MESSAGE_KEYS[visibleDetail.part_id])}
              />
            </h3>
            <p className="level-session__part-hint">
              <LocalizedMessage
                resolution={message(PART_HINT_KEYS[visibleDetail.part_id])}
              />
            </p>
            {locale === 'kn-IN' ? (
              visibleDetail.kannada_guidance.summary ? (
                <p className="level-session__kannada" lang="kn">
                  {visibleDetail.kannada_guidance.summary}
                </p>
              ) : null
            ) : (
              <>
                {visibleDetail.target_patterns.length > 0 ? (
                  <ul className="level-session__patterns">
                    {visibleDetail.target_patterns.map((pattern) => (
                      <li key={pattern}>{pattern}</li>
                    ))}
                  </ul>
                ) : null}
                {visibleDetail.kannada_guidance.summary ? (
                  <details className="level-session__kannada-help">
                    <summary>
                      <LocalizedMessage
                        resolution={message('level.path.kannada_help')}
                      />
                    </summary>
                    <p lang="kn">{visibleDetail.kannada_guidance.summary}</p>
                  </details>
                ) : null}
              </>
            )}
          </div>

          {visibleDetail.audio_stream_path ? (
            <div
              className={
                lessonPlaying
                  ? 'level-session__panel level-session__audio is-playing'
                  : 'level-session__panel level-session__audio'
              }
            >
              <h3>
                <LocalizedMessage resolution={message('level.path.listen')} />
              </h3>
              <p>
                <LocalizedMessage
                  resolution={message('level.path.talk_after_listen')}
                />
              </p>
              {isDemoSpeechUrl(visibleDetail.audio_stream_path) ? (
                <DemoSpeechPlayer
                  labels={lessonAudioLabels(message)}
                  source={visibleDetail.audio_stream_path}
                />
              ) : (
                <ModelAudioPlayer
                  labels={lessonAudioLabels(message)}
                  loadSource={api.downloadAudio}
                  mimeType="audio/mpeg"
                  onEnded={handleLessonAudioEnded}
                  onPause={() => setLessonPlaying(false)}
                  onPlaying={markLessonAudioHeard}
                  source={visibleDetail.audio_stream_path}
                />
              )}
            </div>
          ) : null}

          {visibleDetail.activities.length > 0 ? (
            <div className="level-session__panel">
              <h3>
                <LocalizedMessage resolution={message('level.path.do_this')} />
              </h3>
              <ul className="level-session__activities">
                {visibleDetail.activities.map((activity) => (
                  <li key={activity.id}>{activity.prompt}</li>
                ))}
              </ul>
            </div>
          ) : null}

          {visibleDetail.check ? (
            <details className="level-session__panel">
              <summary>
                <LocalizedMessage
                  resolution={message('level.path.quick_check')}
                />
              </summary>
            <div>
              <h3>
                <LocalizedMessage resolution={message('level.path.check')} />
              </h3>
              <form
                className="level-session__check"
                onSubmit={(event) => {
                  event.preventDefault()
                  void submitCheck()
                }}
              >
                {visibleDetail.check.items.map((item) => (
                  <fieldset key={item.id}>
                    <legend>{item.prompt}</legend>
                    {item.options.map((option, index) => (
                      <label key={`${item.id}-${index}`}>
                        <input
                          type="radio"
                          name={item.id}
                          checked={answers[item.id] === index}
                          onChange={() =>
                            setAnswers((current) => ({
                              ...current,
                              [item.id]: index,
                            }))
                          }
                        />
                        <span>{option}</span>
                      </label>
                    ))}
                  </fieldset>
                ))}
                <button
                  type="submit"
                  className="level-session__primary"
                  disabled={busy}
                >
                  <LocalizedMessage
                    resolution={message('level.path.submit_check')}
                  />
                </button>
              </form>
              {checkResult ? (
                <p className="level-session__score">
                  <LocalizedMessage
                    resolution={message('level.path.score', {
                      score: String(checkResult.score),
                      result: checkResult.passed
                        ? message('level.path.passed').text
                        : message('level.path.try_again').text,
                    })}
                  />
                </p>
              ) : null}
            </div>
            </details>
          ) : null}

          <div className="level-session__panel level-session__teacher-stage">
            <TutorPresence
              locale={locale}
              localizationPolicy={localizationPolicy}
              mood={
                lessonPlaying
                  ? 'listening'
                  : boundSession
                    ? 'speaking'
                    : 'idle'
              }
              size="lg"
            />
            <div className="level-session__actions">
              <button
                type="button"
                className="level-session__primary"
                disabled={busy}
                onClick={() => void startTalk()}
              >
                <LocalizedMessage
                  resolution={message(
                    visibleDetail.part_id === 'interaction' ||
                      visibleDetail.part_id === 'conversation'
                      ? 'level.path.open_room'
                      : 'level.path.ask_tutor',
                  )}
                />
              </button>
              {onOpenRoleplay && visibleDetail.part_id === 'interaction' ? (
                <button
                  type="button"
                  className="level-session__secondary"
                  onClick={onOpenRoleplay}
                >
                  <LocalizedMessage
                    resolution={message('level.path.try_roleplay')}
                  />
                </button>
              ) : null}
              <button
                type="button"
                className="level-session__secondary"
                disabled={busy || visibleDetail.completed}
                onClick={() => void complete()}
              >
                <LocalizedMessage
                  resolution={message(
                    visibleDetail.completed
                      ? 'level.path.completed'
                      : 'level.path.complete',
                  )}
                />
              </button>
            </div>
            {conversationNote ? (
              <p className="level-session__note">{conversationNote}</p>
            ) : null}
            {boundSession && conversationApi ? (
              <ConversationJourney
                api={conversationApi}
                createClientId={createClientId}
                demoMode={demoMode}
                embedded
                initialSession={boundSession}
                key={boundSession.id}
                locale={locale}
                localizationPolicy={localizationPolicy}
                openingLine={tutorOpeningLine(visibleDetail)}
              />
            ) : null}
          </div>
        </>
      ) : error === null ? (
        <p className="level-session__status">
          <LocalizedMessage resolution={message('level.path.session_loading')} />
        </p>
      ) : null}
    </section>
  )
}
