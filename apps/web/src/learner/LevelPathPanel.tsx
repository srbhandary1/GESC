import { type AudioControlLabels } from '@global-english/design-system'
import {
  type MessageKey,
  type ResolutionPolicy,
  type SupportedLocale,
} from '@global-english/localization'
import { useCallback, useEffect, useRef, useState } from 'react'
import { ConversationJourney } from '../conversation/ConversationJourney'
import { lessonDrillOpening } from '../conversation/lessonDrillStepper'
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
  LevelCheckpointDetail,
  LevelCheckpointSubmissionResult,
  LevelSessionDetail,
  LevelSessionsApi,
  LevelSummary,
  SessionPartId,
  CheckSubmissionResult,
} from './LevelSessionsApi'
import { browserOfflineLearningStore } from '../offline/BrowserOfflineLearningStore'
import {
  type CachedLevelCheckpointDetail,
  type OfflineLearningStore,
  type OfflineProfileBinding,
} from '../offline/OfflineLearningStore'
import { conceptLabel } from './conceptLabels'
import { ModelAudioPlayer } from './ModelAudioPlayer'
import './LevelSessionJourney.css'

const PARTS: SessionPartId[] = [
  'introduction',
  'practice',
  'conversation',
  'interaction',
]

function checkpointDetailFromCache(
  detail: CachedLevelCheckpointDetail,
): LevelCheckpointDetail {
  return {
    ...detail,
    items: detail.items.map((item) => ({
      ...item,
      options: [...item.options],
    })),
  }
}

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

const PART_LEAD_KEYS: Record<SessionPartId, MessageKey> = {
  introduction: 'level.path.part.introduction.lead',
  practice: 'level.path.part.practice.lead',
  conversation: 'level.path.part.conversation.lead',
  interaction: 'level.path.part.interaction.lead',
}

const PART_TUTOR_STAGE_KEYS: Record<SessionPartId, MessageKey> = {
  introduction: 'level.path.tutor_stage.introduction',
  practice: 'level.path.tutor_stage.practice',
  conversation: 'level.path.tutor_stage.conversation',
  interaction: 'level.path.tutor_stage.interaction',
}

const PART_STEP: Record<SessionPartId, number> = {
  introduction: 1,
  practice: 2,
  conversation: 3,
  interaction: 4,
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
  allowedSessionParts?: readonly SessionPartId[],
): boolean {
  if (
    allowedSessionParts !== undefined &&
    !allowedSessionParts.includes(part)
  ) {
    return false
  }
  const required = PART_PREREQUISITE[part]
  return required === null || completions[required] === true
}

function firstNonEmpty(
  values: readonly string[] | undefined,
): string | undefined {
  return values?.map((value) => value.trim()).find((value) => value !== '')
}

function tutorOpeningLine(detail: LevelSessionDetail): string | undefined {
  if (detail.part_id === 'introduction' || detail.part_id === 'practice') {
    const patterns = detail.target_patterns
      .map((pattern) => pattern.trim())
      .filter((pattern) => pattern !== '')
    const opening = lessonDrillOpening(
      detail.part_id,
      patterns,
      detail.talk?.fallback_turns,
    )
    if (opening !== '') {
      return opening
    }
  }

  if (detail.part_id === 'conversation') {
    const scenario = detail.talk?.scenario?.trim() ?? ''
    if (scenario !== '') {
      return scenario
    }
  }

  if (detail.part_id === 'interaction') {
    const task = detail.apply?.task?.trim() ?? ''
    if (task !== '') {
      return task
    }
  }

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
  allowedSessionParts?: readonly SessionPartId[]
  offlineBinding?: OfflineProfileBinding | null
  offlineStore?: OfflineLearningStore
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
  allowedSessionParts,
  offlineBinding = null,
  offlineStore = browserOfflineLearningStore,
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
        allowedSessionParts={allowedSessionParts}
        checkpointStatus={
          levels?.find((level) => level.level_number === selectedLevel)
            ?.checkpoint_status
        }
        offlineBinding={offlineBinding}
        offlineStore={offlineStore}
      />
    )
  }

  const ready = (levels ?? []).filter(
    (level) =>
      level.availability === 'available' &&
      level.entitlement_access !== 'upgrade_required' &&
      (level.progress_access === undefined ||
        level.progress_access === 'allowed'),
  )
  const progressLocked = (levels ?? []).filter(
    (level) =>
      level.availability === 'available' &&
      level.entitlement_access !== 'upgrade_required' &&
      level.progress_access === 'locked',
  )
  const checkpointReady = (levels ?? []).filter(
    (level) =>
      level.availability === 'available' &&
      level.entitlement_access !== 'upgrade_required' &&
      level.checkpoint_status === 'ready',
  )
  const reviewDueBlocked = (levels ?? []).filter(
    (level) =>
      level.availability === 'available' &&
      level.entitlement_access !== 'upgrade_required' &&
      level.checkpoint_status === 'review_due_blocked',
  )
  const checkpointExhausted = (levels ?? []).filter(
    (level) =>
      level.availability === 'available' &&
      level.checkpoint_status === 'attempts_exhausted',
  )
  const upgradeRequired = (levels ?? []).filter(
    (level) =>
      level.availability === 'available' &&
      level.entitlement_access === 'upgrade_required',
  )
  const pending = (levels ?? []).filter(
    (level) => level.availability !== 'available',
  )

  const currentLevel = ready[0] ?? checkpointReady[0] ?? null
  const currentConcept =
    currentLevel === null
      ? null
      : conceptLabel(currentLevel.concept_key) ??
        message('level.path.ready').text

  return (
    <section
      className="level-path level-path--variant-a"
      aria-labelledby="level-path-title"
      lang={locale}
    >
      <header className="level-path__intro">
        {onBack ? (
          <button type="button" className="level-path__back" onClick={onBack}>
            <LocalizedMessage resolution={message('level.path.back')} />
          </button>
        ) : null}
        <h2 id="level-path-title">
          <LocalizedMessage resolution={message('level.path.title')} />
        </h2>
      </header>

      {currentLevel ? (
        <article className="ge-continue-hero">
          <p className="ge-continue-hero__eyebrow">
            <LocalizedMessage
              resolution={message('demo.phase1.home.hero_label')}
            />
          </p>
          <h3 className="ge-continue-hero__title">{currentConcept}</h3>
          {currentLevel.checkpoint_status === 'ready' ? (
            <p className="ge-continue-hero__meta">
              <LocalizedMessage
                resolution={message('level.path.checkpoint_ready_heading')}
              />
            </p>
          ) : null}
          <div className="ge-continue-hero__actions">
            <button
              className="ge-button ge-button--primary"
              type="button"
              onClick={() => setSelectedLevel(currentLevel.level_number)}
            >
              <LocalizedMessage resolution={message('level.path.open')} />
            </button>
            <a className="ge-button ge-button--ghost" href="#speak">
              <LocalizedMessage resolution={message('conversation.start')} />
            </a>
          </div>
        </article>
      ) : null}

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

      {levels !== null && levels.length > 0 ? (
        <section
          className="ge-chip-section"
          aria-label={message('level.path.ready_heading').text}
        >
          <ul className="ge-chip-row">
            {levels.map((level) => {
              const unlocked =
                level.availability === 'available' &&
                level.entitlement_access !== 'upgrade_required' &&
                (level.progress_access === undefined ||
                  level.progress_access === 'allowed')
              const status =
                level.checkpoint_status === 'passed'
                  ? 'completed'
                  : currentLevel?.level_number === level.level_number
                    ? 'current'
                    : unlocked
                      ? 'completed'
                      : 'locked'
              const label = (
                <LocalizedMessage
                  resolution={message('level.path.level_label', {
                    number: String(level.level_number),
                  })}
                />
              )
              const concept = conceptLabel(level.concept_key)
              return (
                <li key={level.level_number}>
                  {unlocked ? (
                    <button
                      type="button"
                      className="ge-level-chip ge-level-chip--rich"
                      data-status={status}
                      onClick={() => setSelectedLevel(level.level_number)}
                    >
                      <span className="ge-level-chip__label">{label}</span>
                      {concept ? (
                        <span className="ge-level-chip__concept">{concept}</span>
                      ) : null}
                    </button>
                  ) : (
                    <span
                      className="ge-level-chip ge-level-chip--rich"
                      data-status="locked"
                    >
                      <span className="ge-level-chip__label">{label}</span>
                      {concept ? (
                        <span className="ge-level-chip__concept">{concept}</span>
                      ) : null}
                    </span>
                  )}
                </li>
              )
            })}
          </ul>
        </section>
      ) : levels !== null && error === null ? (
        <p className="level-path__empty">
          <LocalizedMessage resolution={message('level.path.empty')} />
        </p>
      ) : null}

      {currentLevel ? (
        <section
          className="ge-chip-section"
          aria-label={message('level.path.sessions_nav').text}
        >
          <h3 className="ge-chip-section__title">
            <LocalizedMessage
              resolution={message('level.path.sessions_nav')}
            />
          </h3>
          <ul className="ge-chip-row">
            {PARTS.filter(
              (part) =>
                allowedSessionParts === undefined ||
                allowedSessionParts.includes(part),
            ).map((part) => (
              <li key={part}>
                <button
                  type="button"
                  className="ge-room-chip"
                  data-status={part === 'introduction' ? 'active' : 'available'}
                  onClick={() => setSelectedLevel(currentLevel.level_number)}
                >
                  <LocalizedMessage
                    resolution={message(PART_MESSAGE_KEYS[part])}
                  />
                </button>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {reviewDueBlocked.length > 0 ? (
        <div className="level-path__later level-path__review-due-blocked">
          <h3>
            <LocalizedMessage
              resolution={message('level.path.review_due_blocked_heading')}
            />
          </h3>
          <p className="level-path__status">
            <LocalizedMessage
              resolution={message('level.path.review_due_blocked_body')}
            />
          </p>
        </div>
      ) : null}

      {checkpointReady.length > 0 ? (
        <div className="level-path__ready level-path__checkpoint-ready">
          <h3>
            <LocalizedMessage
              resolution={message('level.path.checkpoint_ready_heading')}
            />
          </h3>
          <p className="level-path__status">
            <LocalizedMessage
              resolution={message('level.path.checkpoint_ready_body')}
            />
          </p>
          <ol className="level-path__list">
            {checkpointReady.map((level) => (
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
                        resolution={message('level.path.checkpoint_open', {
                          number: String(level.level_number),
                        })}
                      />
                    </strong>
                  </span>
                </button>
              </li>
            ))}
          </ol>
        </div>
      ) : null}

      {checkpointExhausted.length > 0 ? (
        <div className="level-path__later">
          <h3>
            <LocalizedMessage
              resolution={message('level.path.checkpoint_exhausted_heading')}
            />
          </h3>
          <p className="level-path__status">
            <LocalizedMessage
              resolution={message('level.path.checkpoint_exhausted_body')}
            />
          </p>
        </div>
      ) : null}

      {progressLocked.length > 0 ? (
        <div className="level-path__later">
          <h3>
            <LocalizedMessage
              resolution={message('level.path.progress_locked_heading')}
            />
          </h3>
          <p className="level-path__status">
            <LocalizedMessage
              resolution={message('level.path.progress_locked_body', {
                number: String(
                  progressLocked[0]?.unlock_after_level_number ?? 1,
                ),
              })}
            />
          </p>
        </div>
      ) : null}

      {upgradeRequired.length > 0 ? (
        <div className="level-path__later">
          <h3>
            <LocalizedMessage
              resolution={message('subscription.level_locked')}
            />
          </h3>
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
  allowedSessionParts?: readonly SessionPartId[]
  checkpointStatus?: LevelSummary['checkpoint_status']
  offlineBinding?: OfflineProfileBinding | null
  offlineStore?: OfflineLearningStore
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
  allowedSessionParts,
  checkpointStatus,
  offlineBinding = null,
  offlineStore = browserOfflineLearningStore,
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
  const [checkpointDetail, setCheckpointDetail] =
    useState<LevelCheckpointDetail | null>(null)
  const [checkpointResult, setCheckpointResult] =
    useState<LevelCheckpointSubmissionResult | null>(null)
  const [checkpointAnswers, setCheckpointAnswers] = useState<
    Record<string, number>
  >({})
  const [checkpointOfflinePending, setCheckpointOfflinePending] = useState<{
    readonly commandId: string
  } | null>(null)
  const [blueprintContentHash, setBlueprintContentHash] = useState<
    string | null
  >(null)
  const [online, setOnline] = useState(
    () => globalThis.navigator?.onLine !== false,
  )
  const autoStarted = useRef(false)

  const allSessionsComplete = PARTS.every((part) => completions[part])

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
        setBlueprintContentHash(payload.blueprint_content_hash)
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

  useEffect(() => {
    function updateNetworkState() {
      setOnline(globalThis.navigator?.onLine !== false)
    }

    globalThis.addEventListener('online', updateNetworkState)
    globalThis.addEventListener('offline', updateNetworkState)

    return () => {
      globalThis.removeEventListener('online', updateNetworkState)
      globalThis.removeEventListener('offline', updateNetworkState)
    }
  }, [])

  useEffect(() => {
    if (
      offlineBinding === null ||
      offlineStore.pendingCheckpointCommands === undefined ||
      checkpointStatus !== 'ready'
    ) {
      return
    }

    let cancelled = false

    void offlineStore
      .pendingCheckpointCommands(offlineBinding)
      .then((commands) => {
        if (cancelled) {
          return
        }

        const pending = commands.find(
          (command) =>
            command.levelNumber === levelNumber &&
            command.state === 'pending',
        )

        if (pending !== undefined) {
          setCheckpointOfflinePending({ commandId: pending.commandId })
        }
      })

    return () => {
      cancelled = true
    }
  }, [checkpointStatus, levelNumber, offlineBinding, offlineStore])

  const synchronizeCheckpointCommands = useCallback(async () => {
    if (
      offlineBinding === null ||
      offlineStore.pendingCheckpointCommands === undefined ||
      offlineStore.acceptCheckpointReceipt === undefined ||
      globalThis.navigator?.onLine === false
    ) {
      return
    }

    const synchronize = async () => {
      const commands =
        await offlineStore.pendingCheckpointCommands!(offlineBinding)
      const pending = commands.filter(
        ({ state: commandState }) => commandState === 'pending',
      )

      for (const command of pending) {
        try {
          let result: LevelCheckpointSubmissionResult
          if (
            command.bundleId !== '' &&
            command.lease !== '' &&
            api.submitOfflineLevelCheckpoint !== undefined
          ) {
            result = await api.submitOfflineLevelCheckpoint(
              command.bundleId,
              command.lease,
              command.levelNumber,
              command.answers,
              command.idempotencyKey,
            )
          } else if (api.submitCheckpoint === undefined) {
            throw new Error('checkpoint_sync_unavailable')
          } else {
            result = await api.submitCheckpoint(
              command.levelNumber,
              command.answers,
              command.idempotencyKey,
            )
          }
          await offlineStore.acceptCheckpointReceipt!(
            offlineBinding,
            command.commandId,
          )

          if (command.levelNumber === levelNumber) {
            setCheckpointResult(result)
            setCheckpointOfflinePending(null)
            setCheckpointDetail(null)
          }
        } catch (error) {
          if (
            error instanceof Error &&
            /^level_sessions_http_[45]\d\d$/u.test(error.message)
          ) {
            await offlineStore.markCheckpointNeedsAttention?.(
              offlineBinding,
              command.commandId,
            )

            if (command.levelNumber === levelNumber) {
              setCheckpointOfflinePending(null)
              setError('check_failed')
            }
          }

          break
        }
      }
    }

    const locks = globalThis.navigator?.locks

    if (locks === undefined) {
      await synchronize()
      return
    }

    await locks.request(
      'global-english-offline-sync-v1',
      { ifAvailable: true, mode: 'exclusive' },
      async (lock) => {
        if (lock !== null) {
          await synchronize()
        }
      },
    )
  }, [api, levelNumber, offlineBinding, offlineStore])

  useEffect(() => {
    if (online) {
      void synchronizeCheckpointCommands()
    }
  }, [online, synchronizeCheckpointCommands])

  useEffect(() => {
    let cancelled = false

    if (
      !allSessionsComplete ||
      api.checkpointDetail === undefined ||
      checkpointStatus !== 'ready'
    ) {
      queueMicrotask(() => {
        if (!cancelled) {
          setCheckpointDetail(null)
        }
      })

      return () => {
        cancelled = true
      }
    }
    if (checkpointResult?.passed === true || checkpointOfflinePending !== null) {
      return () => {
        cancelled = true
      }
    }

    const controller = new AbortController()

    async function loadCheckpoint() {
      if (online) {
        try {
          const payload = await api.checkpointDetail!(
            levelNumber,
            controller.signal,
          )

          if (cancelled || controller.signal.aborted) {
            return
          }

          setCheckpointDetail(payload)

          if (
            offlineBinding !== null &&
            (await offlineStore.hasProfile(offlineBinding))
          ) {
            if (
              api.offlineLevelBundle !== undefined &&
              offlineStore.saveLevelBundle !== undefined
            ) {
              try {
                const bundle = await api.offlineLevelBundle(
                  levelNumber,
                  controller.signal,
                )
                if (!cancelled && !controller.signal.aborted) {
                  await offlineStore.saveLevelBundle(offlineBinding, bundle)
                }
              } catch {
                // Unsigned checkpoint cache remains a degraded fallback only.
              }
            }

            if (offlineStore.saveCheckpointDetail !== undefined) {
              await offlineStore.saveCheckpointDetail(offlineBinding, payload)
            }
          }

          return
        } catch {
          if (cancelled || controller.signal.aborted) {
            return
          }
        }
      }

      if (offlineBinding !== null && blueprintContentHash !== null) {
        if (offlineStore.loadLevelBundle !== undefined) {
          const stored = await offlineStore.loadLevelBundle(
            offlineBinding,
            levelNumber,
            blueprintContentHash,
          )

          if (!cancelled && stored !== null) {
            setCheckpointDetail(
              checkpointDetailFromCache(stored.bundle.manifest.checkpoint),
            )
            return
          }
        }

        if (offlineStore.loadCheckpointDetail !== undefined) {
          const cached = await offlineStore.loadCheckpointDetail(
            offlineBinding,
            levelNumber,
            blueprintContentHash,
          )

          if (!cancelled && cached !== null) {
            setCheckpointDetail(checkpointDetailFromCache(cached))
            return
          }
        }
      }

      if (!cancelled) {
        setCheckpointDetail(null)
      }
    }

    void loadCheckpoint()

    return () => {
      cancelled = true
      controller.abort()
    }
  }, [
    allSessionsComplete,
    api,
    blueprintContentHash,
    checkpointOfflinePending,
    checkpointResult?.passed,
    checkpointStatus,
    levelNumber,
    offlineBinding,
    offlineStore,
    online,
  ])

  const visibleDetail = detail?.part_id === partId ? detail : null

  function selectPart(part: SessionPartId) {
    if (
      allowedSessionParts !== undefined &&
      !allowedSessionParts.includes(part)
    ) {
      setError('session_plan_locked')
      return
    }
    if (!isPartUnlocked(part, completions, allowedSessionParts)) {
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

  async function submitCheckpoint() {
    if (api.submitCheckpoint === undefined || checkpointDetail === null) {
      return
    }

    const unanswered = checkpointDetail.items.some(
      (item) => checkpointAnswers[item.id] === undefined,
    )

    if (unanswered) {
      setError('check_required')
      return
    }

    setBusy(true)
    try {
      if (globalThis.navigator?.onLine !== false) {
        const result = await api.submitCheckpoint(
          levelNumber,
          checkpointAnswers,
          createClientId(),
        )
        setCheckpointResult(result)
        setCheckpointDetail(null)
        setCheckpointOfflinePending(null)
        return
      }

      if (
        offlineBinding === null ||
        offlineStore.queueCheckpointSubmission === undefined ||
        !(await offlineStore.hasProfile(offlineBinding))
      ) {
        setError('check_failed')
        return
      }

      const storedBundle =
        offlineStore.loadLevelBundle === undefined
          ? null
          : await offlineStore.loadLevelBundle(
              offlineBinding,
              levelNumber,
              checkpointDetail.blueprint_content_hash,
            )

      if (storedBundle === null) {
        setError('check_failed')
        return
      }

      const commandId = createClientId()
      const idempotencyKey = createClientId()

      await offlineStore.queueCheckpointSubmission(offlineBinding, {
        commandId,
        levelNumber,
        blueprintContentHash: checkpointDetail.blueprint_content_hash,
        bundleId: storedBundle.bundle.manifest.bundle_id,
        lease: storedBundle.bundle.lease,
        answers: checkpointAnswers,
        idempotencyKey,
        createdAt: new Date().toISOString(),
        state: 'pending',
      })
      setCheckpointOfflinePending({ commandId })
      setCheckpointDetail(null)
      setCheckpointAnswers({})
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
        visibleDetail?.part_id === 'introduction'
          ? message('level.path.part.introduction.hint').text
          : visibleDetail?.part_id === 'practice'
            ? message('level.path.part.practice.hint').text
            : visibleDetail?.part_id === 'conversation' ||
                visibleDetail?.part_id === 'interaction'
              ? message('level.path.free_speech_ready_note').text
              : message('level.path.tutor_ready_note').text,
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
      className={`level-session level-session--${partId}`}
      aria-labelledby="level-session-title"
      data-level-part={partId}
      lang={locale}
    >
      <header className={`level-session__hero level-session__hero--${partId}`}>
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
          <LocalizedMessage resolution={message(PART_MESSAGE_KEYS[partId])} />
        </h2>
        {detail?.objective ? (
          <p className="level-session__objective" lang="en-IN">
            {detail.objective}
          </p>
        ) : null}
        <p className="level-session__lead">
          <LocalizedMessage resolution={message(PART_LEAD_KEYS[partId])} />
        </p>
      </header>

      <nav
        aria-label={message('level.path.sessions_nav').text}
        className="level-session__nav"
      >
        {PARTS.map((part) => {
          const unlocked = isPartUnlocked(part, completions, allowedSessionParts)
          return (
            <button
              key={part}
              type="button"
              aria-label={message(PART_MESSAGE_KEYS[part]).text}
              aria-current={part === partId ? 'true' : undefined}
              aria-disabled={unlocked ? undefined : 'true'}
              className={[
                'level-session__tab',
                `level-session__tab--${part}`,
                part === partId ? 'level-session__tab--active' : '',
                completions[part] ? 'level-session__tab--done' : '',
                unlocked ? '' : 'level-session__tab--locked',
              ]
                .filter(Boolean)
                .join(' ')}
              onClick={() => selectPart(part)}
            >
              <span aria-hidden className="level-session__tab-step">
                {PART_STEP[part]}
              </span>
              <span className="level-session__tab-copy">
                <span className="level-session__tab-label">
                  <LocalizedMessage
                    resolution={message(PART_MESSAGE_KEYS[part])}
                  />
                </span>
                <span className="level-session__tab-hint">
                  <LocalizedMessage resolution={message(PART_HINT_KEYS[part])} />
                </span>
              </span>
            </button>
          )
        })}
      </nav>

      {error === 'session_locked' ? (
        <p className="level-session__status" role="alert">
          <LocalizedMessage resolution={message('level.path.locked')} />
        </p>
      ) : null}

      {error === 'session_plan_locked' ? (
        <p className="level-session__status" role="alert">
          <LocalizedMessage
            resolution={message('entitlement.session_locked')}
          />
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
        <div className="level-session__content">
          <div
            className={`level-session__panel level-session__part-focus level-session__part-focus--${visibleDetail.part_id}`}
          >
            <div className="level-session__part-focus-header">
              <p className="level-session__part-focus-step">
                <LocalizedMessage
                  resolution={message('level.path.part.step_label', {
                    step: String(PART_STEP[visibleDetail.part_id]),
                    total: String(PARTS.length),
                  })}
                />
              </p>
              <p className="level-session__part-hint">
                <LocalizedMessage
                  resolution={message(PART_HINT_KEYS[visibleDetail.part_id])}
                />
              </p>
            </div>

            {visibleDetail.part_id === 'conversation' ? (
              visibleDetail.talk?.scenario ? (
                <div className="level-session__scenario">
                  <p className="level-session__focus-label">
                    <LocalizedMessage
                      resolution={message('level.path.part.scenario_label')}
                    />
                  </p>
                  <p lang="en-IN">{visibleDetail.talk.scenario}</p>
                </div>
              ) : null
            ) : null}

            {visibleDetail.part_id === 'conversation' &&
            (visibleDetail.talk?.elicitation_goals?.length ?? 0) > 0 ? (
              <div className="level-session__goals">
                <p className="level-session__focus-label">
                  <LocalizedMessage
                    resolution={message('level.path.part.goals_label')}
                  />
                </p>
                <ul className="level-session__goal-list">
                  {visibleDetail.talk?.elicitation_goals.map((goal) => (
                    <li key={goal} lang="en-IN">
                      {goal}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}

            {visibleDetail.part_id === 'interaction' ? (
              visibleDetail.apply?.task ? (
                <div className="level-session__task">
                  <p className="level-session__focus-label">
                    <LocalizedMessage
                      resolution={message('level.path.part.task_label')}
                    />
                  </p>
                  <p lang="en-IN">{visibleDetail.apply.task}</p>
                </div>
              ) : null
            ) : null}

            {visibleDetail.part_id === 'introduction' ||
            visibleDetail.part_id === 'practice' ? (
              locale === 'kn-IN' ? (
                visibleDetail.kannada_guidance.summary ? (
                  <p className="level-session__kannada" lang="kn">
                    {visibleDetail.kannada_guidance.summary}
                  </p>
                ) : null
              ) : (
                <>
                  {visibleDetail.target_patterns.length > 0 ? (
                    <>
                      <p className="level-session__focus-label">
                        <LocalizedMessage
                          resolution={message('level.path.part.patterns_label')}
                        />
                      </p>
                      <ul className="level-session__patterns">
                        {visibleDetail.target_patterns.map((pattern) => (
                          <li key={pattern}>{pattern}</li>
                        ))}
                      </ul>
                    </>
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
              )
            ) : null}
          </div>

          {visibleDetail.audio_stream_path &&
          (visibleDetail.part_id === 'introduction' ||
            visibleDetail.part_id === 'practice') ? (
            <div
              className={
                lessonPlaying
                  ? 'level-session__panel level-session__audio is-playing'
                  : 'level-session__panel level-session__audio'
              }
            >
              <h3>
                <LocalizedMessage
                  resolution={message(
                    visibleDetail.part_id === 'practice'
                      ? 'level.path.practice_listen'
                      : 'level.path.listen',
                  )}
                />
              </h3>
              <p>
                <LocalizedMessage
                  resolution={message(
                    visibleDetail.part_id === 'practice'
                      ? 'level.path.practice_listen_lead'
                      : 'level.path.talk_after_listen',
                  )}
                />
              </p>
              {isDemoSpeechUrl(visibleDetail.audio_stream_path) ? (
                <DemoSpeechPlayer
                  labels={lessonAudioLabels(message)}
                  onEnded={handleLessonAudioEnded}
                  onPause={() => setLessonPlaying(false)}
                  onPlaying={markLessonAudioHeard}
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
                  slowLabel={message('learner.audio.slow').text}
                  source={visibleDetail.audio_stream_path}
                />
              )}
            </div>
          ) : null}

          {visibleDetail.activities.length > 0 &&
          (visibleDetail.part_id === 'introduction' ||
            visibleDetail.part_id === 'practice') ? (
            <div
              className={`level-session__panel level-session__panel--activities level-session__panel--activities-${visibleDetail.part_id}`}
            >
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
            <div className="level-session__panel level-session__panel--check">
              <h3>
                <LocalizedMessage resolution={message('level.path.check')} />
              </h3>
              <p className="level-session__check-lead">
                <LocalizedMessage
                  resolution={message('level.path.quick_check')}
                />
              </p>
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
          ) : null}

          <div
            className={`level-session__panel level-session__teacher-stage level-session__teacher-stage--${visibleDetail.part_id}`}
          >
            <p className="level-session__teacher-stage-label">
              <LocalizedMessage
                resolution={message(PART_TUTOR_STAGE_KEYS[visibleDetail.part_id])}
              />
            </p>
            {boundSession && conversationApi ? null : (
              <TutorPresence
                locale={locale}
                localizationPolicy={localizationPolicy}
                mood={
                  lessonPlaying
                    ? 'speaking'
                    : boundSession
                      ? 'idle'
                      : 'idle'
                }
                size="lg"
                enableAvatar3d
              />
            )}
            <div className="level-session__actions">
              {boundSession ? null : (
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
              )}
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
                lessonRoom={visibleDetail.part_id}
                grammarDrill={
                  visibleDetail.part_id === 'introduction' ||
                  visibleDetail.part_id === 'practice'
                }
              />
            ) : null}
          </div>

          {allSessionsComplete && checkpointOfflinePending ? (
            <div
              className="level-session__panel level-session__checkpoint-offline"
              role="status"
            >
              <h3>
                <LocalizedMessage
                  resolution={message('learner.offline.saved')}
                />
              </h3>
              <p>
                <LocalizedMessage
                  resolution={message('learner.offline.pending_authority')}
                />
              </p>
            </div>
          ) : null}

          {allSessionsComplete &&
          checkpointDetail &&
          checkpointOfflinePending === null &&
          checkpointResult?.passed !== true ? (
            <div className="level-session__panel level-session__checkpoint">
              <h3>
                <LocalizedMessage
                  resolution={message('level.path.checkpoint_heading')}
                />
              </h3>
              <p>
                <LocalizedMessage
                  resolution={message('level.path.checkpoint_lead', {
                    remaining: String(
                      checkpointDetail.attempts_remaining ?? 0,
                    ),
                  })}
                />
              </p>
              <form
                className="level-session__check"
                onSubmit={(event) => {
                  event.preventDefault()
                  void submitCheckpoint()
                }}
              >
                {checkpointDetail.items.map((item) => (
                  <fieldset key={item.id}>
                    <legend>{item.prompt}</legend>
                    {item.options.map((option, index) => (
                      <label key={`${item.id}-${index}`}>
                        <input
                          type="radio"
                          name={item.id}
                          checked={checkpointAnswers[item.id] === index}
                          onChange={() =>
                            setCheckpointAnswers((current) => ({
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
                    resolution={message('level.path.checkpoint_submit')}
                  />
                </button>
              </form>
              {checkpointResult ? (
                <>
                  <p className="level-session__score">
                    <LocalizedMessage
                      resolution={message('level.path.score', {
                        score: String(checkpointResult.score),
                        result: checkpointResult.passed
                          ? message('level.path.checkpoint_passed').text
                          : message('level.path.try_again').text,
                      })}
                    />
                  </p>
                  {checkpointResult.spoken_evidence ? (
                    <div className="level-session__spoken-evidence">
                      <h4>
                        <LocalizedMessage
                          resolution={message(
                            'level.path.spoken_evidence_heading',
                          )}
                        />
                      </h4>
                      <p>
                        <LocalizedMessage
                          resolution={message(
                            checkpointResult.spoken_evidence
                              .evidence_state === 'recorded_unassessed'
                              ? 'level.path.spoken_evidence.recorded_unassessed'
                              : 'level.path.spoken_evidence.not_recorded',
                          )}
                        />
                      </p>
                      <p className="level-session__note">
                        <LocalizedMessage
                          resolution={message(
                            'level.path.spoken_evidence.no_spoken_progress_claim',
                          )}
                        />
                      </p>
                    </div>
                  ) : null}
                </>
              ) : null}
            </div>
          ) : null}

          {checkpointResult?.passed ? (
            <p className="level-session__status">
              <LocalizedMessage
                resolution={message('level.path.checkpoint_unlocked', {
                  number: String(checkpointResult.level_unlock?.level_number ?? levelNumber + 1),
                })}
              />
            </p>
          ) : null}

          {allSessionsComplete &&
          checkpointStatus === 'review_due_blocked' ? (
            <p className="level-session__status" role="status">
              <LocalizedMessage
                resolution={message('level.path.review_due_blocked_body')}
              />
            </p>
          ) : null}

          {allSessionsComplete &&
          checkpointStatus === 'attempts_exhausted' &&
          checkpointResult?.passed !== true ? (
            <p className="level-session__status" role="alert">
              <LocalizedMessage
                resolution={message('level.path.checkpoint_exhausted_body')}
              />
            </p>
          ) : null}
        </div>
      ) : error === null ? (
        <p className="level-session__status">
          <LocalizedMessage resolution={message('level.path.session_loading')} />
        </p>
      ) : null}
    </section>
  )
}
