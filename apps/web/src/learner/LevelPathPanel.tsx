import { lessonInstruction } from './content/lessonInstruction'
import { CONCEPT_MESSAGE_KEYS } from '../localization/learnerLabelKeys'
import { type AudioControlLabels } from '@global-english/design-system'
import {
  type MessageKey,
  type ResolutionPolicy,
  type SupportedLocale,
} from '@global-english/localization'
import { type ReactNode, useCallback, useEffect, useRef, useState } from 'react'
import { QuestionHelpJourney } from '../conversation/QuestionHelpJourney'
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
  LEVEL_QUIZ_DRAFT_TTL_MILLISECONDS,
  type LevelQuizDraft,
  type LevelQuizDraftPart,
  type OfflineLearningStore,
  type OfflineProfileBinding,
  type StoredOfflineLevelBundle,
} from '../offline/OfflineLearningStore'
import { ModelAudioPlayer } from './ModelAudioPlayer'
import type { SubscriptionEntryContext } from '../subscriptions/SubscriptionPanel'
import {
  openLearnerLevel,
  openLearnerLevelList,
  openLearnerQuestion,
  openLearnerSessionPart,
  readLearnerNavigationState,
  subscribeToLearnerNavigation,
  type LearnerAssessmentKind,
} from './LearnerNavigationHistory'
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

function conceptLabel(conceptKey: string | null, message: (key: MessageKey) => { text: string }): string | null {
  if (conceptKey === null || conceptKey === '') {
    return null
  }

  const key = CONCEPT_MESSAGE_KEYS[conceptKey]
  return key ? message(key).text : conceptKey.replaceAll('_', ' ')
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

type AssessmentItem = LevelCheckpointDetail['items'][number]
const EMPTY_ASSESSMENT_ITEMS: readonly AssessmentItem[] = []
type InterfaceMessageResolver = (
  key: MessageKey,
  parameters?: Record<string, string>,
) => ReturnType<typeof resolveInterfaceMessage>

function newLevelQuizDraft(
  levelNumber: number,
  partId: LevelQuizDraftPart,
  blueprintContentHash: string,
  itemIds: readonly string[],
  attemptId: string,
  activeItemIds: readonly string[] = itemIds,
  selectedOptionIds: Readonly<Record<string, number>> = {},
): LevelQuizDraft {
  const createdAt = new Date()

  return {
    activeItemIds: [...activeItemIds],
    attemptId,
    blueprintContentHash,
    createdAt: createdAt.toISOString(),
    currentItemIndex: 0,
    expiresAt: new Date(
      createdAt.getTime() + LEVEL_QUIZ_DRAFT_TTL_MILLISECONDS,
    ).toISOString(),
    itemIds: [...itemIds],
    levelNumber,
    partId,
    selectedOptionIds: { ...selectedOptionIds },
    updatedAt: createdAt.toISOString(),
  }
}

function validRestoredLevelQuizDraft(
  draft: LevelQuizDraft,
  levelNumber: number,
  partId: LevelQuizDraftPart,
  blueprintContentHash: string,
  items: readonly AssessmentItem[],
): boolean {
  const expectedIds = new Set(items.map(({ id }) => id))
  const storedIds = new Set(draft.itemIds)
  const optionsByItem = new Map(
    items.map((item) => [item.id, item.options.length]),
  )

  return (
    draft.levelNumber === levelNumber &&
    draft.partId === partId &&
    draft.blueprintContentHash === blueprintContentHash &&
    draft.attemptId !== '' &&
    new Date(draft.expiresAt).getTime() > Date.now() &&
    expectedIds.size === storedIds.size &&
    [...expectedIds].every((itemId) => storedIds.has(itemId)) &&
    draft.activeItemIds.length > 0 &&
    draft.activeItemIds.every((itemId) => expectedIds.has(itemId)) &&
    draft.currentItemIndex >= 0 &&
    draft.currentItemIndex < draft.activeItemIds.length &&
    Object.entries(draft.selectedOptionIds).every(
      ([itemId, optionId]) =>
        expectedIds.has(itemId) &&
        Number.isInteger(optionId) &&
        optionId >= 0 &&
        optionId < (optionsByItem.get(itemId) ?? 0),
    )
  )
}

interface LevelQuizDraftController {
  readonly draft: LevelQuizDraft | null
  clear(attemptId: string): Promise<void>
  moveTo(currentItemIndex: number): void
  restart(
    activeItemIds: readonly string[],
    selectedOptionIds?: Readonly<Record<string, number>>,
  ): void
  select(itemId: string, optionId: number): void
}

function useLevelQuizDraft({
  active,
  binding,
  blueprintContentHash,
  createClientId,
  items,
  levelNumber,
  partId,
  store,
}: {
  readonly active: boolean
  readonly binding: OfflineProfileBinding | null
  readonly blueprintContentHash: string | null
  readonly createClientId: () => string
  readonly items: readonly AssessmentItem[]
  readonly levelNumber: number
  readonly partId: LevelQuizDraftPart
  readonly store: OfflineLearningStore
}): LevelQuizDraftController {
  const [draft, setDraft] = useState<LevelQuizDraft | null>(null)
  const loadedScope = useRef<string | null>(null)
  const persistenceEnabled = useRef(false)
  const saveQueue = useRef<Promise<void>>(Promise.resolve())
  const itemSignature = items.map(({ id }) => id).join('\u0000')
  const scope = `${levelNumber}:${partId}:${blueprintContentHash ?? ''}:${itemSignature}`

  const persist = useCallback(
    (next: LevelQuizDraft) => {
      if (
        !persistenceEnabled.current ||
        binding === null ||
        store.saveLevelQuizDraft === undefined
      ) {
        return
      }

      saveQueue.current = saveQueue.current
        .catch(() => undefined)
        .then(() => store.saveLevelQuizDraft!(binding, next))
    },
    [binding, store],
  )

  useEffect(() => {
    const contentRevision = blueprintContentHash
    if (
      !active ||
      contentRevision === null ||
      items.length === 0 ||
      loadedScope.current === scope
    ) {
      return
    }

    const activeContentRevision: string = contentRevision
    loadedScope.current = scope
    persistenceEnabled.current = false
    let cancelled = false
    let initialized = false

    async function restore() {
      let restored: LevelQuizDraft | null = null
      let canPersist = false

      if (
        binding !== null &&
        store.loadLevelQuizDraft !== undefined &&
        store.saveLevelQuizDraft !== undefined
      ) {
        try {
          canPersist = await store.hasProfile(binding)
          if (canPersist) {
            restored = await store.loadLevelQuizDraft(
              binding,
              levelNumber,
              partId,
            )
          }
        } catch {
          canPersist = false
        }
      }

      if (cancelled) {
        return
      }

      initialized = true
      persistenceEnabled.current = canPersist
      const next =
        restored !== null &&
        validRestoredLevelQuizDraft(
          restored,
          levelNumber,
          partId,
          activeContentRevision,
          items,
        )
          ? restored
          : newLevelQuizDraft(
              levelNumber,
              partId,
              activeContentRevision,
              items.map(({ id }) => id),
              createClientId(),
            )
      setDraft(next)
      persist(next)
    }

    void restore()

    return () => {
      cancelled = true
      if (!initialized && loadedScope.current === scope) {
        loadedScope.current = null
      }
    }
  }, [
    active,
    binding,
    blueprintContentHash,
    createClientId,
    itemSignature,
    items,
    levelNumber,
    partId,
    persist,
    scope,
    store,
  ])

  const update = useCallback(
    (transform: (current: LevelQuizDraft) => LevelQuizDraft) => {
      setDraft((current) => {
        if (
          current === null ||
          current.levelNumber !== levelNumber ||
          current.partId !== partId
        ) {
          return current
        }

        const next = transform(current)
        persist(next)
        return next
      })
    },
    [levelNumber, partId, persist],
  )

  return {
    draft,
    async clear(attemptId) {
      await saveQueue.current.catch(() => undefined)
      if (
        persistenceEnabled.current &&
        binding !== null &&
        store.deleteLevelQuizDraft !== undefined
      ) {
        await store.deleteLevelQuizDraft(
          binding,
          levelNumber,
          partId,
          attemptId,
        )
      }
    },
    moveTo(currentItemIndex) {
      update((current) => ({
        ...current,
        currentItemIndex,
        updatedAt: new Date().toISOString(),
      }))
    },
    restart(activeItemIds, selectedOptionIds = {}) {
      const next = newLevelQuizDraft(
        levelNumber,
        partId,
        blueprintContentHash ?? '',
        items.map(({ id }) => id),
        createClientId(),
        activeItemIds,
        selectedOptionIds,
      )
      setDraft(next)
      persist(next)
    },
    select(itemId, optionId) {
      update((current) => ({
        ...current,
        selectedOptionIds: {
          ...current.selectedOptionIds,
          [itemId]: optionId,
        },
        updatedAt: new Date().toISOString(),
      }))
    },
  }
}

function OneQuestionAssessment({
  busy,
  canRetry,
  draft,
  feedbackIndex,
  items,
  message,
  onDone,
  onFeedbackIndexChange,
  onItemIndexChange,
  onRetryIncorrect,
  onSelect,
  onSubmit,
  result,
  submitLabel,
  assessmentKind,
  instruction,
}: {
  readonly instruction: (source: string, rationale?: boolean) => ReactNode
  readonly assessmentKind: LearnerAssessmentKind
  readonly busy: boolean
  readonly canRetry: boolean
  readonly draft: LevelQuizDraft
  readonly feedbackIndex: number
  readonly items: readonly AssessmentItem[]
  readonly message: InterfaceMessageResolver
  readonly onDone: () => void
  readonly onFeedbackIndexChange: (index: number) => void
  readonly onItemIndexChange: (index: number) => void
  readonly onRetryIncorrect: () => void
  readonly onSelect: (itemId: string, optionId: number) => void
  readonly onSubmit: () => void
  readonly result: CheckSubmissionResult | null
  readonly submitLabel: MessageKey
}) {
  const itemById = new Map(items.map((item) => [item.id, item]))

  if (result !== null) {
    const itemResult = result.item_results[feedbackIndex]
    const item = itemResult === undefined ? undefined : itemById.get(itemResult.id)

    if (item === undefined || itemResult === undefined) {
      return null
    }

    const hasMoreFeedback = feedbackIndex < result.item_results.length - 1
    const hasIncorrect = result.item_results.some(({ correct }) => !correct)

    return (
      <div
        className={`level-session__assessment-feedback ${
          itemResult.correct ? 'is-correct' : 'is-not-yet'
        }`}
        id={`level-${draft.levelNumber}-${assessmentKind}-feedback-${feedbackIndex}`}
        role="status"
        tabIndex={-1}
      >
        <p className="level-session__question-progress">
          <LocalizedMessage
            resolution={message('level.path.question_progress', {
              current: String(feedbackIndex + 1),
              total: String(result.item_results.length),
            })}
          />
        </p>
        <h4>{instruction(item.prompt)}</h4>
        <p className="level-session__feedback-state">
          <span aria-hidden="true">{itemResult.correct ? '✓' : '!'}</span>
          <LocalizedMessage
            resolution={message(
              itemResult.correct
                ? 'level.path.answer_correct'
                : 'level.path.answer_not_yet',
            )}
          />
        </p>
        <p>
          <LocalizedMessage
            resolution={message('level.path.selected_answer', {
              answer: item.options[itemResult.selected_option] ?? '',
            })}
          />
        </p>
        {itemResult.rationale ? (
          <p className="level-session__rationale">
            {instruction(itemResult.rationale, true)}
          </p>
        ) : null}
        {hasIncorrect && canRetry && !hasMoreFeedback ? (
          <p className="level-session__remediation">
            <LocalizedMessage
              resolution={message('level.path.review_remediation')}
            />
          </p>
        ) : null}
        <div className="level-session__assessment-actions">
          <button
            className="level-session__primary"
            disabled={busy}
            id={`level-${draft.levelNumber}-${assessmentKind}-feedback-${feedbackIndex}-continue`}
            onClick={() => {
              if (hasMoreFeedback) {
                onFeedbackIndexChange(feedbackIndex + 1)
              } else if (hasIncorrect && canRetry) {
                onRetryIncorrect()
              } else {
                onDone()
              }
            }}
            type="button"
          >
            <LocalizedMessage
              resolution={message(
                hasMoreFeedback
                  ? 'level.path.next_result'
                  : hasIncorrect && canRetry
                    ? 'level.path.retry_incorrect'
                    : 'level.path.done',
              )}
            />
          </button>
        </div>
      </div>
    )
  }

  const itemId = draft.activeItemIds[draft.currentItemIndex]
  const item = itemId === undefined ? undefined : itemById.get(itemId)
  if (item === undefined) {
    return null
  }

  const selectedOptionId = draft.selectedOptionIds[item.id]
  const isLast = draft.currentItemIndex === draft.activeItemIds.length - 1

  return (
    <form
      className="level-session__check level-session__assessment"
      onSubmit={(event) => {
        event.preventDefault()
        if (isLast) {
          onSubmit()
        } else {
          onItemIndexChange(draft.currentItemIndex + 1)
        }
      }}
    >
      <p
        className="level-session__question-progress"
        id={`level-${draft.levelNumber}-${assessmentKind}-question-${draft.currentItemIndex}`}
        tabIndex={-1}
      >
        <LocalizedMessage
          resolution={message('level.path.question_progress', {
            current: String(draft.currentItemIndex + 1),
            total: String(draft.activeItemIds.length),
          })}
        />
      </p>
      <fieldset>
        <legend>{instruction(item.prompt)}</legend>
        <div className="level-session__answer-list">
          {item.options.map((option, optionId) => {
            const selected = selectedOptionId === optionId

            return (
              <label
                className={selected ? 'is-selected' : undefined}
                key={`${item.id}-${optionId}`}
              >
                <input
                  checked={selected}
                  name={item.id}
                  onChange={() => onSelect(item.id, optionId)}
                  type="radio"
                />
                <span className="level-session__answer-icon" aria-hidden="true">
                  {selected ? '✓' : ''}
                </span>
                <span className="level-session__answer-copy">{option}</span>
              </label>
            )
          })}
        </div>
      </fieldset>
      <div className="level-session__assessment-actions">
        {draft.currentItemIndex > 0 ? (
          <button
            className="level-session__secondary"
            disabled={busy}
            onClick={() => onItemIndexChange(draft.currentItemIndex - 1)}
            type="button"
          >
            <LocalizedMessage resolution={message('level.path.previous_question')} />
          </button>
        ) : null}
        <button
          className="level-session__primary"
          disabled={busy || selectedOptionId === undefined}
          id={`level-${draft.levelNumber}-${assessmentKind}-question-${draft.currentItemIndex}-continue`}
          type="submit"
        >
          <LocalizedMessage
            resolution={message(
              isLast ? submitLabel : 'level.path.continue_question',
            )}
          />
        </button>
      </div>
    </form>
  )
}

export interface LevelPathPanelProps {
  api: LevelSessionsApi
  locale?: SupportedLocale
  localizationPolicy?: ResolutionPolicy
  onBack?: () => void
  onOpenSpeakingPractice?: (session: FriendSession) => void
  onOpenRoleplay?: () => void
  onOpenPlans?: (context: SubscriptionEntryContext) => void
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
  onOpenPlans,
  conversationApi,
  createClientId = createRandomClientId,
  demoMode = false,
  allowedSessionParts,
  offlineBinding = null,
  offlineStore = browserOfflineLearningStore,
}: LevelPathPanelProps) {
  const message = useCallback(
    (key: MessageKey, parameters = {}) =>
      resolveInterfaceMessage(key, locale, localizationPolicy, parameters),
    [locale, localizationPolicy],
  )
  const [levels, setLevels] = useState<LevelSummary[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [selectedLevel, setSelectedLevel] = useState<number | null>(() => {
    const navigation = readLearnerNavigationState()
    return navigation?.destination === 'learn'
      ? navigation.levelNumber ?? null
      : null
  })

  useEffect(
    () =>
      subscribeToLearnerNavigation((navigation) => {
        if (navigation.destination === 'learn') {
          setSelectedLevel(navigation.levelNumber ?? null)
        }
      }),
    [],
  )

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
        onBack={() => {
          openLearnerLevelList('level-session-all-levels')
        }}
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
                conceptLabel(level.concept_key, message) ??
                message('level.path.ready').text
              return (
                <li key={level.level_number}>
                  <button
                    type="button"
                    className="level-path__item"
                    id={`level-path-level-${level.level_number}`}
                    onClick={() =>
                      openLearnerLevel(
                        level.level_number,
                        `level-path-level-${level.level_number}`,
                      )
                    }
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
          <a className="ge-button ge-button--primary" href="#home">
            <LocalizedMessage
              resolution={message('level.path.review_due_blocked_action')}
            />
          </a>
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
                  id={`level-path-checkpoint-${level.level_number}`}
                  onClick={() =>
                    openLearnerLevel(
                      level.level_number,
                      `level-path-checkpoint-${level.level_number}`,
                    )
                  }
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
          <ul className="level-path__pending">
            {progressLocked.map((level) => (
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
                {onOpenPlans ? (
                  <button
                    className="ge-button ge-button--primary level-path__upgrade"
                    id={`level-upgrade-${level.level_number}`}
                    onClick={() => {
                      const completedLevelNumber = (levels ?? [])
                        .filter(
                          (candidate) =>
                            candidate.level_number < level.level_number &&
                            candidate.checkpoint_status === 'passed',
                        )
                        .reduce<number | null>(
                          (highest, candidate) =>
                            highest === null || candidate.level_number > highest
                              ? candidate.level_number
                              : highest,
                          null,
                        )
                      onOpenPlans({
                        completedLevelNumber,
                        lockedLevelNumber: level.level_number,
                        returnTargetId: `level-upgrade-${level.level_number}`,
                      })
                    }}
                    type="button"
                  >
                    <LocalizedMessage
                      resolution={message('subscription.view_plans_for_level', {
                        level: String(level.level_number),
                      })}
                    />
                  </button>
                ) : null}
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
  const message = useCallback(
    (key: MessageKey, parameters = {}) =>
      resolveInterfaceMessage(key, locale, localizationPolicy, parameters),
    [locale, localizationPolicy],
  )
  const [partId, setPartId] = useState<SessionPartId>(() => {
    const navigation = readLearnerNavigationState()
    return navigation?.destination === 'learn' &&
      navigation.levelNumber === levelNumber &&
      navigation.partId !== undefined
      ? navigation.partId
      : 'introduction'
  })
  const [navigationAnnouncement, setNavigationAnnouncement] = useState('')
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
  const [checkFeedbackIndex, setCheckFeedbackIndex] = useState(0)
  const [checkFeedbackDone, setCheckFeedbackDone] = useState(false)
  const [busy, setBusy] = useState(false)
  const [conversationNote, setConversationNote] = useState<string | null>(null)
  const [audioHeard, setAudioHeard] = useState(false)
  const [speakingOpened, setSpeakingOpened] = useState(false)
  const [tutorHelpOpen, setTutorHelpOpen] = useState(false)
  const [boundSession, setBoundSession] = useState<FriendSession | null>(null)
  const [lessonPlaying, setLessonPlaying] = useState(false)
  const [checkpointDetail, setCheckpointDetail] =
    useState<LevelCheckpointDetail | null>(null)
  const [checkpointResult, setCheckpointResult] =
    useState<LevelCheckpointSubmissionResult | null>(null)
  const [checkpointFeedbackIndex, setCheckpointFeedbackIndex] = useState(0)
  const [checkpointFeedbackDone, setCheckpointFeedbackDone] = useState(false)
  const [checkpointOfflinePending, setCheckpointOfflinePending] = useState<{
    readonly commandId: string
  } | null>(null)
  const [checkpointOfflineState, setCheckpointOfflineState] = useState<
    'pending' | 'syncing' | 'synced' | 'needs-attention' | null
  >(null)
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
          setCheckpointOfflineState('pending')
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
          if (command.levelNumber === levelNumber) {
            setCheckpointOfflineState('syncing')
          }
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
            let storedLevel: StoredOfflineLevelBundle | null = null
            try {
              storedLevel =
                offlineStore.loadLevelBundle === undefined
                  ? null
                  : await offlineStore.loadLevelBundle(
                      offlineBinding,
                      command.levelNumber,
                      command.blueprintContentHash,
                    )
            } catch {
              // The accepted server receipt remains authoritative even if the
              // optional local feedback source can no longer be decrypted.
            }
            setCheckpointResult(result)
            setCheckpointFeedbackIndex(0)
            setCheckpointFeedbackDone(false)
            setCheckpointOfflinePending(null)
            setCheckpointOfflineState('synced')
            setCheckpointDetail((current) => {
              const base =
                current ??
                (storedLevel === null
                  ? null
                  : checkpointDetailFromCache(
                      storedLevel.bundle.manifest.checkpoint,
                    ))

              return base === null
                ? null
                : {
                    ...base,
                    attempts_remaining: result.attempts_remaining,
                  }
            })
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
              setCheckpointOfflineState('needs-attention')
              setError('check_failed')
            }
          } else if (command.levelNumber === levelNumber) {
            setCheckpointOfflineState('pending')
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
  const checkItems = visibleDetail?.check?.items ?? EMPTY_ASSESSMENT_ITEMS
  const checkpointItems =
    checkpointDetail?.items ?? EMPTY_ASSESSMENT_ITEMS
  const checkDraftController = useLevelQuizDraft({
    active: visibleDetail?.check !== null && visibleDetail?.check !== undefined,
    binding: offlineBinding,
    blueprintContentHash,
    createClientId,
    items: checkItems,
    levelNumber,
    partId,
    store: offlineStore,
  })
  const checkpointDraftController = useLevelQuizDraft({
    active: checkpointDetail !== null,
    binding: offlineBinding,
    blueprintContentHash:
      checkpointDetail?.blueprint_content_hash ?? null,
    createClientId,
    items: checkpointItems,
    levelNumber,
    partId: 'checkpoint',
    store: offlineStore,
  })
  const activeNavigation = readLearnerNavigationState()
  const checkDraftForView =
    checkDraftController.draft !== null &&
    activeNavigation?.destination === 'learn' &&
    activeNavigation.levelNumber === levelNumber &&
    activeNavigation.assessment?.kind === 'quick-check' &&
    activeNavigation.assessment.phase === 'question'
      ? {
          ...checkDraftController.draft,
          currentItemIndex: activeNavigation.assessment.questionIndex,
        }
      : checkDraftController.draft
  const checkpointDraftForView =
    checkpointDraftController.draft !== null &&
    activeNavigation?.destination === 'learn' &&
    activeNavigation.levelNumber === levelNumber &&
    activeNavigation.assessment?.kind === 'checkpoint' &&
    activeNavigation.assessment.phase === 'question'
      ? {
          ...checkpointDraftController.draft,
          currentItemIndex: activeNavigation.assessment.questionIndex,
        }
      : checkpointDraftController.draft

  useEffect(
    () =>
      subscribeToLearnerNavigation((navigation) => {
        if (
          navigation.destination !== 'learn' ||
          navigation.levelNumber !== levelNumber
        ) {
          return
        }

        const nextPart = navigation.partId ?? 'introduction'
        setPartId(nextPart)

        const assessment = navigation.assessment
        if (assessment?.kind === 'quick-check') {
          if (assessment.phase === 'feedback') {
            setCheckFeedbackIndex(assessment.questionIndex)
          } else {
            checkDraftController.moveTo(assessment.questionIndex)
          }
        }
        if (assessment?.kind === 'checkpoint') {
          if (assessment.phase === 'feedback') {
            setCheckpointFeedbackIndex(assessment.questionIndex)
          } else {
            checkpointDraftController.moveTo(assessment.questionIndex)
          }
        }

        const partLabel = message(PART_MESSAGE_KEYS[nextPart]).text
        const announcement =
          assessment === undefined
            ? `${message('level.path.level_label', { number: String(levelNumber) }).text}. ${partLabel}.`
            : `${message('level.path.level_label', { number: String(levelNumber) }).text}. ${partLabel}. ${message('level.path.question_progress', {
                current: String(assessment.questionIndex + 1),
                total: String(assessment.totalQuestions),
              }).text}`
        setNavigationAnnouncement(announcement)

        if (navigation.restoreFocusId !== null) {
          return
        }
        globalThis.requestAnimationFrame(() => {
          const targetId =
            assessment === undefined
              ? 'level-session-title'
              : `level-${levelNumber}-${assessment.kind}-${assessment.phase}-${assessment.questionIndex}`
          document.getElementById(targetId)?.focus()
        })
      }),
    [
      checkDraftController,
      checkpointDraftController,
      levelNumber,
      message,
    ],
  )

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
    openLearnerSessionPart(
      levelNumber,
      part,
      `level-${levelNumber}-part-${partId}`,
    )
    setPartId(part)
    setConversationNote(null)
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
    // The server retains passed Checks across navigation/reloads. A missing
    // in-memory result must not prevent completing previously passed Practice.
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
      setDetail((current) => current?.part_id === partId ? { ...current, completed: true } : current)
      setCompletions((current) => ({ ...current, [partId]: true }))
      setError(null)
    } catch {
      setError('complete_failed')
    } finally {
      setBusy(false)
    }
  }

  async function submitCheck() {
    const draft = checkDraftController.draft
    if (
      draft === null ||
      checkItems.some(
        (item) => draft.selectedOptionIds[item.id] === undefined,
      )
    ) {
      setError('check_required')
      return
    }

    setBusy(true)
    try {
      const result = await api.submitCheck(
        levelNumber,
        partId,
        { ...draft.selectedOptionIds },
        draft.attemptId,
      )
      await checkDraftController.clear(draft.attemptId)
      setCheckResult(result)
      if (result.passed) {
        setCompletions((current) => ({ ...current, practice: true }))
        setDetail((current) => current?.part_id === 'practice' ? { ...current, completed: true } : current)
      }
      setCheckFeedbackIndex(0)
      setCheckFeedbackDone(false)
      setError(null)
      openLearnerQuestion(
        levelNumber,
        partId,
        'quick-check',
        'feedback',
        0,
        result.item_results.length,
        `level-${levelNumber}-quick-check-question-${draft.currentItemIndex}-continue`,
      )
    } catch {
      setError('check_failed')
    } finally {
      setBusy(false)
    }
  }

  async function submitCheckpoint() {
    const draft = checkpointDraftController.draft
    if (
      api.submitCheckpoint === undefined ||
      checkpointDetail === null ||
      draft === null
    ) {
      return
    }

    const unanswered = checkpointDetail.items.some(
      (item) => draft.selectedOptionIds[item.id] === undefined,
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
          { ...draft.selectedOptionIds },
          draft.attemptId,
        )
        await checkpointDraftController.clear(draft.attemptId)
        setCheckpointResult(result)
        setCheckpointDetail((current) =>
          current === null
            ? current
            : {
                ...current,
                attempts_remaining: result.attempts_remaining,
              },
        )
        setCheckpointFeedbackIndex(0)
        setCheckpointFeedbackDone(false)
        setCheckpointOfflinePending(null)
        setCheckpointOfflineState(null)
        setError(null)
        openLearnerQuestion(
          levelNumber,
          partId,
          'checkpoint',
          'feedback',
          0,
          result.item_results.length,
          `level-${levelNumber}-checkpoint-question-${draft.currentItemIndex}-continue`,
        )
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

      await offlineStore.queueCheckpointSubmission(offlineBinding, {
        commandId,
        levelNumber,
        blueprintContentHash: checkpointDetail.blueprint_content_hash,
        bundleId: storedBundle.bundle.manifest.bundle_id,
        lease: storedBundle.bundle.lease,
        answers: { ...draft.selectedOptionIds },
        idempotencyKey: draft.attemptId,
        createdAt: new Date().toISOString(),
        state: 'pending',
      })
      await checkpointDraftController.clear(draft.attemptId)
      setCheckpointOfflinePending({ commandId })
      setCheckpointOfflineState('pending')
      setError(null)
    } catch {
      setError('check_failed')
    } finally {
      setBusy(false)
    }
  }

  function retryIncorrectCheckItems() {
    if (checkResult === null) {
      return
    }

    const incorrectItemIds = checkResult.item_results
      .filter(({ correct }) => !correct)
      .map(({ id }) => id)
    const retainedOptionIds = Object.fromEntries(
      checkResult.item_results
        .filter(({ correct }) => correct)
        .map(({ id, selected_option }) => [id, selected_option]),
    )

    if (incorrectItemIds.length === 0) {
      return
    }

    checkDraftController.restart(incorrectItemIds, retainedOptionIds)
    setCheckResult(null)
    setCheckFeedbackIndex(0)
    setCheckFeedbackDone(false)
    setError(null)
  }

  function retryIncorrectCheckpointItems() {
    if (
      checkpointResult === null ||
      (checkpointResult.attempts_remaining ?? 0) <= 0
    ) {
      return
    }

    const incorrectItemIds = checkpointResult.item_results
      .filter(({ correct }) => !correct)
      .map(({ id }) => id)
    const retainedOptionIds = Object.fromEntries(
      checkpointResult.item_results
        .filter(({ correct }) => correct)
        .map(({ id, selected_option }) => [id, selected_option]),
    )

    if (incorrectItemIds.length === 0) {
      return
    }

    checkpointDraftController.restart(
      incorrectItemIds,
      retainedOptionIds,
    )
    setCheckpointResult(null)
    setCheckpointFeedbackIndex(0)
    setCheckpointFeedbackDone(false)
    setError(null)
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
          ? null
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

  const instruction = (source: string, rationale = false) => {
    const resolved = lessonInstruction(source, locale, localizationPolicy)
    return <span lang={resolved.lang}>{rationale ? message('level.path.answer_rationale', { rationale: resolved.text }).text : resolved.text}</span>
  }
  const lessonGuidance = locale === 'kn-IN' ? visibleDetail?.kannada_guidance.summary?.trim() : undefined
  const lessonHeadingText = lessonGuidance || visibleDetail?.objective
  const lessonHeadingLocale = lessonGuidance ? 'kn-IN' : 'en-IN'

  if (tutorHelpOpen && conversationApi) {
    return <section className="level-session" data-level-part={partId} lang={locale}>
      <button className="level-session__back" type="button" onClick={() => setTutorHelpOpen(false)}><LocalizedMessage resolution={message('level.path.back')} /></button>
      <p lang={lessonHeadingLocale}>{lessonHeadingText}</p>
      {api.startTutorHelp ? <QuestionHelpJourney api={conversationApi} createClientId={createClientId} locale={locale} localizationPolicy={localizationPolicy}
        startLessonSession={(command, signal) => api.startTutorHelp!(levelNumber, partId, locale, command, signal)} />
        : <p role="status"><LocalizedMessage resolution={message('level.path.unavailable')} /></p>}
    </section>
  }

  return (
    <section
      className={[
        'level-session',
        `level-session--${partId}`,
        (checkDraftForView !== null && checkResult === null && !checkFeedbackDone) ||
        (checkpointDraftForView !== null &&
          checkpointResult === null &&
          !checkpointFeedbackDone &&
          checkpointOfflinePending === null)
          ? 'level-session--question-phase'
          : '',
      ]
        .filter(Boolean)
        .join(' ')}
      aria-labelledby="level-session-title"
      data-level-part={partId}
      lang={locale}
    >
      <header className={`level-session__hero level-session__hero--${partId}`}>
        <button
          type="button"
          className="level-session__back"
          id="level-session-all-levels"
          onClick={onBack}
        >
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
        {lessonHeadingText ? (
          <p className="level-session__objective" lang={lessonHeadingLocale}>
            {lessonHeadingText}
          </p>
        ) : null}
        <p className="level-session__lead">
          <LocalizedMessage resolution={message(PART_LEAD_KEYS[partId])} />
        </p>
      </header>

      <p aria-live="polite" className="visually-hidden" role="status">
        {navigationAnnouncement}
      </p>

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
              aria-label={
                completions[part]
                  ? message('level.path.session_completed', {
                      session: message(PART_MESSAGE_KEYS[part]).text,
                    }).text
                  : message(PART_MESSAGE_KEYS[part]).text
              }
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
              id={`level-${levelNumber}-part-${part}`}
            >
              <span aria-hidden className="level-session__tab-step">
                {completions[part] ? '✓' : PART_STEP[part]}
              </span>
              <span className="level-session__tab-copy">
                <span className="level-session__tab-label">
                  <LocalizedMessage
                    resolution={message(PART_MESSAGE_KEYS[part])}
                  />
                </span>
                <span className="level-session__tab-hint">
                  <LocalizedMessage
                    resolution={message(
                      completions[part]
                        ? 'level.path.completed'
                        : PART_HINT_KEYS[part],
                    )}
                  />
                </span>
              </span>
            </button>
          )
        })}
      </nav>
      <p className="level-session__progress" role="status">
        <LocalizedMessage
          resolution={message('level.path.sessions_progress', {
            completed: String(PARTS.filter((part) => completions[part]).length),
            total: String(PARTS.length),
          })}
        />
      </p>

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
                  <p>{instruction(visibleDetail.talk.scenario)}</p>
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
                    <li key={goal}>
                      {instruction(goal)}
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
                  <p>{instruction(visibleDetail.apply.task)}</p>
                </div>
              ) : null
            ) : null}

            {visibleDetail.part_id === 'introduction' ||
            visibleDetail.part_id === 'practice' ? (
              locale === 'kn-IN' ? null : (
                <>
                  {visibleDetail.target_patterns.length > 0 ? (
                    <details className="level-session__patterns-help">
                      <summary>
                        <LocalizedMessage
                          resolution={message('level.path.part.patterns_label')}
                        />
                      </summary>
                      <ul className="level-session__patterns">
                        {visibleDetail.target_patterns.map((pattern) => (
                          <li key={pattern}>{pattern}</li>
                        ))}
                      </ul>
                    </details>
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
                  seekLabel={message('learner.audio.seek').text}
                  slowLabel={message('learner.audio.slow').text}
                  stopLabel={message('learner.audio.stop').text}
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
              <details>
                <summary>
                  <LocalizedMessage resolution={message('level.path.do_this')} />
                </summary>
              <ul className="level-session__activities">
                {visibleDetail.activities.map((activity) => (
                  <li key={activity.id}>{instruction(activity.prompt)}</li>
                ))}
              </ul>
              </details>
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
              {checkDraftForView !== null && !checkFeedbackDone ? (
                <OneQuestionAssessment
                  instruction={instruction}
                  assessmentKind="quick-check"
                  busy={busy}
                  canRetry
                  draft={checkDraftForView}
                  feedbackIndex={checkFeedbackIndex}
                  items={visibleDetail.check.items}
                  message={message}
                  onDone={() => setCheckFeedbackDone(true)}
                  onFeedbackIndexChange={(index) => {
                    setCheckFeedbackIndex(index)
                    openLearnerQuestion(
                      levelNumber,
                      partId,
                      'quick-check',
                      'feedback',
                      index,
                      checkResult?.item_results.length ?? 0,
                      `level-${levelNumber}-quick-check-feedback-${checkFeedbackIndex}-continue`,
                    )
                  }}
                  onItemIndexChange={(index) => {
                    checkDraftController.moveTo(index)
                    openLearnerQuestion(
                      levelNumber,
                      partId,
                      'quick-check',
                      'question',
                      index,
                      checkDraftForView.activeItemIds.length,
                      `level-${levelNumber}-quick-check-question-${checkDraftForView.currentItemIndex}-continue`,
                    )
                  }}
                  onRetryIncorrect={retryIncorrectCheckItems}
                  onSelect={(itemId, optionId) => {
                    const questionIndex =
                      checkDraftForView.currentItemIndex
                    openLearnerQuestion(
                      levelNumber,
                      partId,
                      'quick-check',
                      'question',
                      questionIndex,
                      checkDraftForView.activeItemIds.length,
                      `level-${levelNumber}-quick-check-question-${questionIndex}`,
                    )
                    checkDraftController.select(itemId, optionId)
                    setError(null)
                  }}
                  onSubmit={() => void submitCheck()}
                  result={checkResult}
                  submitLabel="level.path.submit_check"
                />
              ) : null}
              {checkResult && checkFeedbackDone ? (
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
            <p className="level-session__teacher-stage-label visually-hidden">
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
                onClick={() => (visibleDetail.part_id === 'introduction' || visibleDetail.part_id === 'practice') && conversationApi ? setTutorHelpOpen(true) : void startTalk()}
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
              {!boundSession && conversationApi && (visibleDetail.part_id === 'introduction' || visibleDetail.part_id === 'practice') ? (
                <button className="level-session__secondary" disabled={busy} type="button" onClick={() => void startTalk()}><LocalizedMessage resolution={message('conversation.start.skill_focus')} /></button>
              ) : null}
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
            {visibleDetail.completed && PARTS[PARTS.indexOf(partId) + 1] ? (
              <button className="level-session__primary" type="button" onClick={() => selectPart(PARTS[PARTS.indexOf(partId) + 1]!)}>
                <LocalizedMessage resolution={message('level.path.continue_question')} />
                {' · '}<LocalizedMessage resolution={message(PART_MESSAGE_KEYS[PARTS[PARTS.indexOf(partId) + 1]!])} />
              </button>
            ) : null}
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

          {allSessionsComplete && checkpointOfflineState !== null ? (
            <div
              className="level-session__panel level-session__checkpoint-offline"
              role="status"
            >
              <h3>
                <LocalizedMessage
                  resolution={message(
                    checkpointOfflineState === 'pending'
                      ? 'learner.offline.saved'
                      : checkpointOfflineState === 'syncing'
                        ? 'learner.offline.syncing'
                        : checkpointOfflineState === 'synced'
                          ? 'learner.offline.synced'
                          : 'learner.offline.failed',
                  )}
                />
              </h3>
              {checkpointOfflineState === 'pending' ? (
                <p>
                  <LocalizedMessage
                    resolution={message('learner.offline.pending_authority')}
                  />
                </p>
              ) : null}
            </div>
          ) : null}

          {allSessionsComplete &&
          checkpointDetail &&
          checkpointOfflinePending === null ? (
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
              {checkpointDraftForView !== null &&
              !checkpointFeedbackDone ? (
                <OneQuestionAssessment
                  instruction={instruction}
                  assessmentKind="checkpoint"
                  busy={busy}
                  canRetry={
                    (checkpointResult?.attempts_remaining ??
                      checkpointDetail.attempts_remaining ??
                      0) > 0
                  }
                  draft={checkpointDraftForView}
                  feedbackIndex={checkpointFeedbackIndex}
                  items={checkpointDetail.items}
                  message={message}
                  onDone={() => setCheckpointFeedbackDone(true)}
                  onFeedbackIndexChange={(index) => {
                    setCheckpointFeedbackIndex(index)
                    openLearnerQuestion(
                      levelNumber,
                      partId,
                      'checkpoint',
                      'feedback',
                      index,
                      checkpointResult?.item_results.length ?? 0,
                      `level-${levelNumber}-checkpoint-feedback-${checkpointFeedbackIndex}-continue`,
                    )
                  }}
                  onItemIndexChange={(index) => {
                    checkpointDraftController.moveTo(index)
                    openLearnerQuestion(
                      levelNumber,
                      partId,
                      'checkpoint',
                      'question',
                      index,
                      checkpointDraftForView.activeItemIds.length,
                      `level-${levelNumber}-checkpoint-question-${checkpointDraftForView.currentItemIndex}-continue`,
                    )
                  }}
                  onRetryIncorrect={retryIncorrectCheckpointItems}
                  onSelect={(itemId, optionId) => {
                    const questionIndex =
                      checkpointDraftForView.currentItemIndex
                    openLearnerQuestion(
                      levelNumber,
                      partId,
                      'checkpoint',
                      'question',
                      questionIndex,
                      checkpointDraftForView.activeItemIds.length,
                      `level-${levelNumber}-checkpoint-question-${questionIndex}`,
                    )
                    checkpointDraftController.select(itemId, optionId)
                    setError(null)
                  }}
                  onSubmit={() => void submitCheckpoint()}
                  result={checkpointResult}
                  submitLabel="level.path.checkpoint_submit"
                />
              ) : null}
              {checkpointResult && checkpointFeedbackDone ? (
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
            <div className="level-session__status" role="status">
              <p>
                <LocalizedMessage
                  resolution={message('level.path.review_due_blocked_body')}
                />
              </p>
              <a className="ge-button ge-button--primary" href="#home">
                <LocalizedMessage
                  resolution={message('level.path.review_due_blocked_action')}
                />
              </a>
            </div>
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
