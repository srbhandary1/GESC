import { MixedLanguageText } from '@global-english/design-system'
import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type FormEvent,
} from 'react'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import type {
  OfflineLearningStore,
  OfflineProfileBinding,
  StoredOfflineBundle,
} from '../offline/OfflineLearningStore'
import { browserOfflineLearningStore } from '../offline/BrowserOfflineLearningStore'
import {
  defaultAudioRecorder,
  type AudioRecorder,
} from './BrowserAudioRecorder'
import type {
  AttemptResponseInput,
  GovernedContent,
  LearnerGoal,
  LearnerHome,
  LearnerJourneyApi,
  LearnerMission,
  LearnerProgress,
  LocalRecordingMetadata,
  MissionResult,
  OfflineBundle,
  OfflineCompletionInput,
  OnboardingInput,
} from './LearnerJourneyApi'
import {
  LEARNER_GOALS,
  LearnerJourneyUnavailableError,
} from './LearnerJourneyApi'
import { DemoSpeechPlayer } from '../demo/DemoSpeechPlayer'
import { isDemoSpeechUrl } from '../demo/browserSpeech'
import { createClientId as createRandomClientId } from '../shared/createClientId'
import { ModelAudioPlayer } from './ModelAudioPlayer'
import { RecordingTurn } from './RecordingTurn'
import './LearnerJourney.css'

export interface LearnerJourneyProps {
  readonly api: LearnerJourneyApi
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly audioRecorder?: AudioRecorder
  readonly createClientId?: () => string
  readonly offlineBinding?: OfflineProfileBinding | null
  readonly offlineStore?: OfflineLearningStore
  readonly resumeBundle?: StoredOfflineBundle
}

interface OfflineMissionSession {
  readonly bundle: OfflineBundle
}

type LoadState =
  | { readonly kind: 'loading' }
  | { readonly kind: 'error' }
  | { readonly kind: 'ready'; readonly home: LearnerHome }
  | {
      readonly kind: 'mission'
      readonly home: LearnerHome | null
      readonly attemptId: string
      readonly mission: LearnerMission
      readonly offline?: OfflineMissionSession
    }
  | {
      readonly kind: 'result'
      readonly home: LearnerHome | null
      readonly mission: LearnerMission
      readonly result: MissionResult
      readonly progress: LearnerProgress | null
    }
  | {
      readonly kind: 'offline-saved'
      readonly home: LearnerHome | null
      readonly mission: LearnerMission
      readonly commandId: string
      readonly correct: boolean
    }

type OfflineStatus =
  | 'available'
  | 'confirmation'
  | 'downloading'
  | 'expired'
  | 'failed'
  | 'saved'
  | 'synced'
  | 'syncing'
  | 'unavailable'

export function LearnerJourney({
  api,
  locale,
  localizationPolicy,
  audioRecorder = defaultAudioRecorder,
  createClientId = createRandomClientId,
  offlineBinding = null,
  offlineStore = browserOfflineLearningStore,
  resumeBundle,
}: LearnerJourneyProps) {
  const [state, setState] = useState<LoadState>({ kind: 'loading' })
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState(false)
  const [offlineStatus, setOfflineStatus] =
    useState<OfflineStatus>('unavailable')
  const [personalDeviceConfirmed, setPersonalDeviceConfirmed] =
    useState(false)
  const [profileEnabled, setProfileEnabled] = useState(false)
  const [online, setOnline] = useState(
    () => globalThis.navigator?.onLine !== false,
  )
  const stateTitle = useRef<HTMLHeadingElement>(null)
  const startAttemptCommandId = useRef(createClientId())
  const mounted = useRef(true)
  const offlineAudioUrl = useRef<string | null>(null)
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  const activateOfflineBundle = useCallback(
    (
      home: LearnerHome | null,
      stored: StoredOfflineBundle,
    ) => {
      const expiresAt = new Date(
        stored.bundle.manifest.expires_at,
      ).getTime()

      if (!Number.isFinite(expiresAt) || expiresAt <= Date.now()) {
        setOfflineStatus('expired')
        throw new Error('Offline authorization expired.')
      }

      if (offlineAudioUrl.current !== null) {
        URL.revokeObjectURL(offlineAudioUrl.current)
      }

      const audioBytes = new Uint8Array(stored.audio)
      const source = URL.createObjectURL(
        new Blob([audioBytes.buffer], {
          type: stored.bundle.manifest.mission.model_audio.mime_type,
        }),
      )
      offlineAudioUrl.current = source
      const {
        offline_check: _offlineCheck,
        ...downloadedMission
      } = stored.bundle.manifest.mission
      void _offlineCheck

      setOfflineStatus('available')
      setState({
        attemptId: createClientId(),
        home,
        kind: 'mission',
        mission: {
          ...downloadedMission,
          model_audio: {
            ...downloadedMission.model_audio,
            stream_url: source,
          },
        },
        offline: { bundle: stored.bundle },
      })
    },
    [createClientId],
  )

  const loadHome = () => {
    const controller = new AbortController()
    setState({ kind: 'loading' })
    setActionError(false)
    api
      .home(controller.signal)
      .then((home) => setState({ home, kind: 'ready' }))
      .catch(() => {
        if (!controller.signal.aborted) {
          setState({ kind: 'error' })
        }
      })

    return controller
  }

  useEffect(() => {
    let active = true

    if (
      resumeBundle !== undefined &&
      globalThis.navigator?.onLine === false
    ) {
      queueMicrotask(() => {
        if (!active) {
          return
        }

        try {
          activateOfflineBundle(null, resumeBundle)
        } catch {
          setState({ kind: 'error' })
        }
      })

      return () => {
        active = false
      }
    }

    const controller = new AbortController()
    api
      .home(controller.signal)
      .then((home) => setState({ home, kind: 'ready' }))
      .catch(() => {
        if (!controller.signal.aborted) {
          setState({ kind: 'error' })
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [activateOfflineBundle, api, resumeBundle])

  useEffect(() => {
    let active = true

    if (offlineBinding === null || !offlineStore.available()) {
      queueMicrotask(() => {
        if (active) {
          setOfflineStatus('unavailable')
          setProfileEnabled(false)
        }
      })

      return () => {
        active = false
      }
    }

    offlineStore
      .hasProfile(offlineBinding)
      .then((enabled) => {
        if (active) {
          setProfileEnabled(enabled)
          setOfflineStatus(enabled ? 'available' : 'confirmation')
        }
      })
      .catch(() => {
        if (active) {
          setOfflineStatus('unavailable')
        }
      })

    return () => {
      active = false
    }
  }, [offlineBinding, offlineStore])

  useEffect(() => {
    mounted.current = true

    return () => {
      mounted.current = false
      if (offlineAudioUrl.current !== null) {
        URL.revokeObjectURL(offlineAudioUrl.current)
        offlineAudioUrl.current = null
      }
    }
  }, [])

  useEffect(() => {
    const updateNetworkState = () => {
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
      state.kind === 'ready' ||
      state.kind === 'mission' ||
      state.kind === 'result' ||
      state.kind === 'offline-saved'
    ) {
      stateTitle.current?.focus()
    }
  }, [state.kind])

  const synchronizeOfflineCommands = useCallback(async () => {
    if (
      offlineBinding === null ||
      api.completeOffline === undefined ||
      globalThis.navigator?.onLine === false
    ) {
      return
    }

    const synchronize = async () => {
      const commands = await offlineStore.pendingCommands(offlineBinding)
      const pending = commands.filter(
        ({ state: commandState }) => commandState === 'pending',
      )

      if (commands.length === 0) {
        return
      }

      if (pending.length === 0) {
        setOfflineStatus('failed')
        return
      }

      setOfflineStatus('syncing')

      for (const command of pending) {
        try {
          const result = await api.completeOffline!(
            command.body,
            command.commandId,
          )
          await offlineStore.acceptReceipt(
            offlineBinding,
            command.commandId,
            result,
          )
          setOfflineStatus('synced')
          setState((current) =>
            current.kind === 'offline-saved' &&
            current.commandId === command.commandId
              ? {
                  home: current.home,
                  kind: 'result',
                  mission: current.mission,
                  progress: null,
                  result,
                }
              : current,
          )
        } catch (error) {
          if (
            error instanceof LearnerJourneyUnavailableError &&
            error.status !== undefined &&
            error.status >= 400 &&
            error.status < 500
          ) {
            await offlineStore.markNeedsAttention(
              offlineBinding,
              command.commandId,
            )
          }
          setOfflineStatus('failed')
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
  }, [api, offlineBinding, offlineStore])

  useEffect(() => {
    if (online) {
      void synchronizeOfflineCommands()
    }
  }, [online, synchronizeOfflineCommands])

  const beginMission = async (home: LearnerHome) => {
    const enrollment = home.enrollment
    const summary = home.next_mission

    if (enrollment === null || summary === null) {
      return
    }

    setBusy(true)
    setActionError(false)

    try {
      if (globalThis.navigator?.onLine === false) {
        if (offlineBinding === null) {
          throw new Error('No offline profile binding.')
        }

        const stored = await offlineStore.loadBundle(
          offlineBinding,
          enrollment.id,
          summary.id,
        )

        if (stored === null) {
          setOfflineStatus('expired')
          throw new Error('No valid offline bundle.')
        }

        activateOfflineBundle(home, stored)
        return
      }

      const mission = await api.mission(summary.id, enrollment.id)
      const attempt = await api.startAttempt(
        summary.id,
        enrollment.id,
        startAttemptCommandId.current,
      )
      startAttemptCommandId.current = createClientId()
      setState({
        attemptId: attempt.id,
        home,
        kind: 'mission',
        mission,
      })
    } catch {
      setActionError(true)
    } finally {
      setBusy(false)
    }
  }

  const downloadOfflineMission = async (home: LearnerHome) => {
    const enrollment = home.enrollment
    const mission = home.next_mission

    if (
      enrollment === null ||
      mission === null ||
      offlineBinding === null ||
      api.offlineBundle === undefined ||
      api.downloadMedia === undefined
    ) {
      setOfflineStatus('unavailable')
      return
    }

    setOfflineStatus('downloading')
    setActionError(false)

    try {
      if (!profileEnabled) {
        if (!personalDeviceConfirmed) {
          setOfflineStatus('confirmation')
          return
        }

        await offlineStore.enablePersonalDevice(offlineBinding)
        setProfileEnabled(true)
      }

      const bundle = await api.offlineBundle(
        mission.id,
        enrollment.id,
      )
      const audio = await api.downloadMedia(
        bundle.manifest.mission.model_audio.stream_url,
      )
      await offlineStore.saveBundle(offlineBinding, bundle, audio)
      setOfflineStatus('available')
    } catch {
      setActionError(true)
      setOfflineStatus(profileEnabled ? 'available' : 'confirmation')
    }
  }

  const completeMission = (
    home: LearnerHome | null,
    mission: LearnerMission,
    result: MissionResult,
  ) => {
    setState({ home, kind: 'result', mission, progress: null, result })

    if (home?.enrollment === null || home === null) {
      return
    }

    void api
      .progress(home.enrollment.id)
      .then((progress) => {
        if (!mounted.current) {
          return
        }

        setState((current) =>
          current.kind === 'result' &&
          current.result.attempt_id === result.attempt_id
            ? { ...current, progress }
            : current,
        )
      })
      .catch(() => {
        // The authoritative submit result is already visible. Progress is
        // optional enrichment from a separate read model.
      })
  }

  if (state.kind === 'loading') {
    return (
      <p
        className="learner-status"
        role="status"
      >
        <LocalizedMessage resolution={message('learner.loading')} />
      </p>
    )
  }

  if (state.kind === 'error') {
    return (
      <section
        aria-labelledby="learner-error-title"
        className="learner-card"
      >
        <h2 id="learner-error-title">
          <LocalizedMessage resolution={message('learner.error.title')} />
        </h2>
        <p role="alert">
          <LocalizedMessage resolution={message('learner.error.message')} />
        </p>
        <button
          className="ge-button ge-button--primary"
          onClick={loadHome}
          type="button"
        >
          <LocalizedMessage resolution={message('learner.retry')} />
        </button>
      </section>
    )
  }

  if (state.kind === 'mission') {
    return (
      <MissionExperience
        api={api}
        attemptId={state.attemptId}
        audioRecorder={audioRecorder}
        createClientId={createClientId}
        locale={locale}
        localizationPolicy={localizationPolicy}
        mission={state.mission}
        offline={
          state.offline === undefined || offlineBinding === null
            ? undefined
            : {
                binding: offlineBinding,
                bundle: state.offline.bundle,
                store: offlineStore,
              }
        }
        onComplete={(result) =>
          completeMission(state.home, state.mission, result)
        }
        onOfflineSaved={(commandId, correct) => {
          setOfflineStatus('saved')
          setState({
            commandId,
            correct,
            home: state.home,
            kind: 'offline-saved',
            mission: state.mission,
          })
        }}
        titleRef={stateTitle}
      />
    )
  }

  if (state.kind === 'offline-saved') {
    return (
      <section
        aria-labelledby="offline-saved-title"
        className="learner-card learner-offline-result"
      >
        <h2
          id="offline-saved-title"
          ref={stateTitle}
          tabIndex={-1}
        >
          <LocalizedMessage resolution={message('learner.offline.saved')} />
        </h2>
        <p role="status">
          <LocalizedMessage
            resolution={message(
              state.correct
                ? 'learner.offline.saved_correct'
                : 'learner.offline.saved_incorrect',
            )}
          />
        </p>
        <p className="learner-notice">
          <LocalizedMessage
            resolution={message('learner.offline.pending_authority')}
          />
        </p>
        {online ? (
          <button
            className="ge-button ge-button--primary"
            disabled={offlineStatus === 'syncing'}
            onClick={() => void synchronizeOfflineCommands()}
            type="button"
          >
            <LocalizedMessage
              resolution={message(
                offlineStatus === 'syncing'
                  ? 'learner.offline.syncing'
                  : 'learner.offline.retry_sync',
              )}
            />
          </button>
        ) : (
          <p role="status">
            <LocalizedMessage
              resolution={message('learner.offline.offline')}
            />
          </p>
        )}
      </section>
    )
  }

  if (state.kind === 'result') {
    return (
      <MissionResultView
        locale={locale}
        localizationPolicy={localizationPolicy}
        onRetry={() => {
          if (state.home === null) {
            loadHome()
            return
          }

          void beginMission(state.home)
        }}
        progress={state.progress}
        result={state.result}
        titleRef={stateTitle}
      />
    )
  }

  if (!state.home.onboarding.completed) {
    return (
      <OnboardingForm
        api={api}
        createClientId={createClientId}
        locale={locale}
        localizationPolicy={localizationPolicy}
        onComplete={(home) => setState({ home, kind: 'ready' })}
        titleRef={stateTitle}
      />
    )
  }

  return (
    <section
      aria-labelledby="learner-home-title"
      className="learner-stack"
    >
      <h2
        className="learner-section-title"
        id="learner-home-title"
        ref={stateTitle}
        tabIndex={-1}
      >
        <LocalizedMessage resolution={message('learner.home.title')} />
      </h2>
      {state.home.enrollment === null ||
      state.home.next_mission === null ? (
        <p className="learner-card">
          <LocalizedMessage
            resolution={message('learner.home.no_mission')}
          />
        </p>
      ) : (
        <section
          aria-labelledby="next-mission-title"
          className="learner-card"
        >
          <p className="learner-eyebrow">
            <LocalizedMessage
              resolution={message('learner.home.next_mission')}
            />
          </p>
          <h3
            className="learner-card__title"
            id="next-mission-title"
          >
            <GovernedText content={state.home.next_mission.title} />
          </h3>
          <p>
            <LocalizedMessage
              resolution={resolveInterfaceMessage(
                'learner.home.estimated_minutes',
                locale,
                localizationPolicy,
                {
                  minutes: state.home.next_mission.estimated_minutes,
                },
              )}
            />
          </p>
          {actionError ? (
            <p role="alert">
              <LocalizedMessage
                resolution={message('learner.error.message')}
              />
            </p>
          ) : null}
          <button
            className="ge-button ge-button--primary"
            disabled={busy}
            onClick={() => void beginMission(state.home)}
            type="button"
          >
            <LocalizedMessage
              resolution={message(
                busy
                  ? 'learner.home.starting'
                  : 'learner.home.start_mission',
              )}
            />
          </button>
        </section>
      )}
      {state.home.enrollment === null ||
      state.home.next_mission === null ? null : (
        <section
          aria-labelledby="offline-access-title"
          className="learner-card learner-offline"
        >
          <h3
            className="learner-card__title"
            id="offline-access-title"
          >
            <LocalizedMessage
              resolution={message('learner.offline.title')}
            />
          </h3>
          <p className="learner-notice">
            <LocalizedMessage
              resolution={message('learner.offline.warning')}
            />
          </p>
          {!profileEnabled &&
          offlineStatus !== 'unavailable' ? (
            <label className="learner-choice">
              <input
                checked={personalDeviceConfirmed}
                onChange={(event) =>
                  setPersonalDeviceConfirmed(
                    event.currentTarget.checked,
                  )
                }
                type="checkbox"
              />
              <LocalizedMessage
                resolution={message(
                  'learner.offline.personal_confirmation',
                )}
              />
            </label>
          ) : null}
          <p
            aria-live="polite"
            role="status"
          >
            <LocalizedMessage
              resolution={message(
                offlineStatusMessageKey(offlineStatus, online),
              )}
            />
          </p>
          <button
            className="ge-button"
            disabled={
              !online ||
              offlineStatus === 'downloading' ||
              offlineStatus === 'unavailable' ||
              (!profileEnabled && !personalDeviceConfirmed)
            }
            onClick={() => void downloadOfflineMission(state.home)}
            type="button"
          >
            <LocalizedMessage
              resolution={message(
                offlineStatus === 'downloading'
                  ? 'learner.offline.downloading'
                  : 'learner.offline.download',
              )}
            />
          </button>
        </section>
      )}
    </section>
  )
}

interface OnboardingFormProps {
  readonly api: LearnerJourneyApi
  readonly createClientId: () => string
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly onComplete: (home: LearnerHome) => void
  readonly titleRef: React.RefObject<HTMLHeadingElement | null>
}

function OnboardingForm({
  api,
  createClientId,
  locale,
  localizationPolicy,
  onComplete,
  titleRef,
}: OnboardingFormProps) {
  const [adultConfirmed, setAdultConfirmed] = useState(false)
  const [goal, setGoal] = useState<LearnerGoal | null>(null)
  const [busy, setBusy] = useState(false)
  const [commandLocked, setCommandLocked] = useState(false)
  const [error, setError] = useState(false)
  const commandId = useRef(createClientId())
  const pendingInput = useRef<OnboardingInput | null>(null)
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    let input = pendingInput.current

    if (input === null) {
      if (!adultConfirmed || goal === null) {
        setError(true)
        return
      }

      input = {
        adult_confirmed: true,
        goal_key: goal,
      } satisfies OnboardingInput
      pendingInput.current = input
      setCommandLocked(true)
    }
    setBusy(true)
    setError(false)

    try {
      onComplete(
        await api.updateOnboarding(input, commandId.current),
      )
      pendingInput.current = null
      setCommandLocked(false)
      commandId.current = createClientId()
    } catch {
      setError(true)
    } finally {
      setBusy(false)
    }
  }

  return (
    <section
      aria-labelledby="onboarding-title"
      className="learner-card learner-onboarding"
    >
      <h2
        id="onboarding-title"
        ref={titleRef}
        tabIndex={-1}
      >
        <LocalizedMessage
          resolution={message('learner.onboarding.title')}
        />
      </h2>
      <p>
        <LocalizedMessage
          resolution={message('learner.onboarding.introduction')}
        />
      </p>
      <form onSubmit={(event) => void submit(event)}>
        <label className="learner-choice learner-choice--confirmation">
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

        <fieldset className="learner-fieldset">
          <legend>
            <LocalizedMessage
              resolution={message('learner.onboarding.goal_legend')}
            />
          </legend>
          {LEARNER_GOALS.map((candidate) => (
            <label
              className="learner-choice"
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
              <LocalizedMessage
                resolution={message(goalMessageKey(candidate))}
              />
            </label>
          ))}
        </fieldset>

        {error ? (
          <p role="alert">
            <LocalizedMessage
              resolution={message('learner.onboarding.error')}
            />
          </p>
        ) : null}
        <button
          className="ge-button ge-button--primary"
          disabled={busy}
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
      </form>
    </section>
  )
}

interface MissionExperienceProps {
  readonly api: LearnerJourneyApi
  readonly attemptId: string
  readonly audioRecorder: AudioRecorder
  readonly createClientId: () => string
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly mission: LearnerMission
  readonly offline?: {
    readonly binding: OfflineProfileBinding
    readonly bundle: OfflineBundle
    readonly store: OfflineLearningStore
  }
  readonly onComplete: (result: MissionResult) => void
  readonly onOfflineSaved: (
    commandId: string,
    correct: boolean,
  ) => void
  readonly titleRef: React.RefObject<HTMLHeadingElement | null>
}

function MissionExperience({
  api,
  attemptId,
  audioRecorder,
  createClientId,
  locale,
  localizationPolicy,
  mission,
  offline,
  onComplete,
  onOfflineSaved,
  titleRef,
}: MissionExperienceProps) {
  const [step, setStep] = useState<'learn' | 'speak' | 'check'>('learn')
  const [recording, setRecording] =
    useState<LocalRecordingMetadata | null>(null)
  const [selectedOption, setSelectedOption] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [responseLocked, setResponseLocked] = useState(false)
  const [error, setError] = useState(false)
  const pendingResponse = useRef<AttemptResponseInput | null>(null)
  const responseCommandId = useRef(createClientId())
  const submitCommandId = useRef(createClientId())
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const text = (key: MessageKey) => message(key).text
  const stepTitle = useRef<HTMLHeadingElement>(null)

  useEffect(() => {
    stepTitle.current?.focus()
  }, [step])

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    let response = pendingResponse.current

    if (response === null) {
      if (selectedOption === null) {
        setError(true)
        return
      }

      response = {
        check_id: mission.check.id,
        local_recording: recording,
        selected_option_id: selectedOption,
      } satisfies AttemptResponseInput
      pendingResponse.current = response
      setResponseLocked(true)
    }
    setBusy(true)
    setError(false)

    try {
      if (offline !== undefined) {
        const commandId = submitCommandId.current
        const body = {
          bundle_id: offline.bundle.manifest.bundle_id,
          check_id: response.check_id,
          client_attempt_id: attemptId,
          lease: offline.bundle.lease,
          local_recording: response.local_recording,
          schema: 'global-english.offline-completion.v1',
          selected_option_id: response.selected_option_id,
        } satisfies OfflineCompletionInput
        await offline.store.queueCompletion(offline.binding, {
          body,
          commandId,
          createdAt: new Date().toISOString(),
          state: 'pending',
        })
        onOfflineSaved(
          commandId,
          response.selected_option_id ===
            offline.bundle.manifest.mission.offline_check
              .correct_option_id,
        )
        return
      }

      await api.recordResponse(
        attemptId,
        response,
        responseCommandId.current,
      )
      const result = await api.submitAttempt(
        attemptId,
        submitCommandId.current,
      )
      pendingResponse.current = null
      setResponseLocked(false)
      responseCommandId.current = createClientId()
      submitCommandId.current = createClientId()
      onComplete(result)
    } catch {
      setError(true)
    } finally {
      setBusy(false)
    }
  }

  return (
    <article
      aria-labelledby="mission-title"
      className="learner-stack"
    >
      <header>
        <p className="learner-eyebrow">
          <LocalizedMessage resolution={message('learner.mission.eyebrow')} />
        </p>
        <h2
          id="mission-title"
          ref={titleRef}
          tabIndex={-1}
        >
          <GovernedText content={mission.title} />
        </h2>
      </header>

      {step === 'learn' ? (
        <section
          aria-labelledby="mission-learn-title"
          className="learner-card"
        >
          <h3
            className="learner-card__title"
            id="mission-learn-title"
            ref={stepTitle}
            tabIndex={-1}
          >
            <LocalizedMessage
              resolution={message('learner.mission.learn_title')}
            />
          </h3>
          <p className="learner-guidance">
            <GovernedText content={mission.kannada_guidance} />
          </p>
          <blockquote className="learner-model-text">
            <GovernedText content={mission.model_text} />
          </blockquote>
          {isDemoSpeechUrl(mission.model_audio.stream_url) ? (
            <DemoSpeechPlayer
              labels={{
                region: text('learner.audio.region'),
                play: text('learner.audio.play'),
                pause: text('learner.audio.pause'),
                resume: text('learner.audio.resume'),
                replay: text('learner.audio.replay'),
                loading: text('learner.audio.loading'),
                unavailable: text('learner.audio.unavailable'),
                error: text('learner.audio.error'),
                readyStatus: text('learner.audio.ready_status'),
                loadingStatus: text('learner.audio.loading_status'),
                playingStatus: text('learner.audio.playing_status'),
                pausedStatus: text('learner.audio.paused_status'),
                endedStatus: text('learner.audio.ended_status'),
                unavailableStatus: text(
                  'learner.audio.unavailable_status',
                ),
                errorStatus: text('learner.audio.error_status'),
              }}
              source={mission.model_audio.stream_url}
            />
          ) : (
            <ModelAudioPlayer
              labels={{
                region: text('learner.audio.region'),
                play: text('learner.audio.play'),
                pause: text('learner.audio.pause'),
                resume: text('learner.audio.resume'),
                replay: text('learner.audio.replay'),
                loading: text('learner.audio.loading'),
                unavailable: text('learner.audio.unavailable'),
                error: text('learner.audio.error'),
                readyStatus: text('learner.audio.ready_status'),
                loadingStatus: text('learner.audio.loading_status'),
                playingStatus: text('learner.audio.playing_status'),
                pausedStatus: text('learner.audio.paused_status'),
                endedStatus: text('learner.audio.ended_status'),
                unavailableStatus: text(
                  'learner.audio.unavailable_status',
                ),
                errorStatus: text('learner.audio.error_status'),
              }}
              mimeType={mission.model_audio.mime_type}
              source={mission.model_audio.stream_url}
            />
          )}
          <section
            aria-labelledby="mission-transcript-title"
            className="learner-transcript"
          >
            <h4 id="mission-transcript-title">
              <LocalizedMessage
                resolution={message('learner.mission.transcript')}
              />
            </h4>
            <p>
              <GovernedText content={mission.transcript} />
            </p>
          </section>
          <button
            className="ge-button ge-button--primary"
            onClick={() => setStep('speak')}
            type="button"
          >
            <LocalizedMessage
              resolution={message('learner.mission.continue_to_speak')}
            />
          </button>
        </section>
      ) : null}

      {step === 'speak' ? (
        <section
          aria-labelledby="mission-speak-title"
          className="learner-stack"
        >
          <h3
            id="mission-speak-title"
            ref={stepTitle}
            tabIndex={-1}
          >
            <LocalizedMessage
              resolution={message('learner.mission.speak_title')}
            />
          </h3>
          <p>
            <LocalizedMessage
              resolution={message('learner.mission.speak_instruction')}
            />
          </p>
          <RecordingTurn
            createClientId={createClientId}
            labels={{
              controls: {
                region: text('learner.recording.region'),
                start: text('learner.recording.start'),
                pause: text('learner.recording.pause'),
                resume: text('learner.recording.resume'),
                stop: text('learner.recording.stop'),
                cancel: text('learner.recording.cancel'),
                idle: text('learner.recording.idle'),
                ready: text('learner.recording.ready'),
                requestingPermission: text(
                  'learner.recording.requesting_permission',
                ),
                recording: text('learner.recording.recording'),
                paused: text('learner.recording.paused'),
                processing: text('learner.recording.processing'),
                blocked: text('learner.recording.blocked'),
                error: text('learner.recording.error'),
              },
              playbackLabel: text('learner.recording.playback_label'),
              playbackHelp: text('learner.recording.playback_help'),
              retry: text('learner.recording.retry'),
              permissionExplanation: text(
                'learner.recording.permission_explanation',
              ),
              continueWithout: text(
                'learner.recording.continue_without',
              ),
              continueWithoutExplanation: text(
                'learner.recording.continue_without_explanation',
              ),
            }}
            onCaptureChange={setRecording}
            onContinueWithoutRecording={() => {
              setRecording(null)
              setStep('check')
            }}
            recorder={audioRecorder}
          />
          <button
            className="ge-button ge-button--primary"
            disabled={recording === null}
            onClick={() => setStep('check')}
            type="button"
          >
            <LocalizedMessage
              resolution={message('learner.mission.continue_to_check')}
            />
          </button>
        </section>
      ) : null}

      {step === 'check' ? (
        <section
          aria-labelledby="mission-check-title"
          className="learner-card"
        >
          <h3
            className="learner-card__title"
            id="mission-check-title"
            ref={stepTitle}
            tabIndex={-1}
          >
            <LocalizedMessage
              resolution={message('learner.mission.check_title')}
            />
          </h3>
          <form onSubmit={(event) => void submit(event)}>
            <fieldset className="learner-fieldset">
              <legend>
                <GovernedText content={mission.check.prompt} />
              </legend>
              {mission.check.options.map((option) => (
                <label
                  className="learner-choice"
                  key={option.id}
                >
                  <input
                    checked={selectedOption === option.id}
                    disabled={busy || responseLocked}
                    name="mission-check"
                    onChange={() => setSelectedOption(option.id)}
                    type="radio"
                    value={option.id}
                  />
                  <GovernedText content={option.label} />
                </label>
              ))}
            </fieldset>
            {recording === null ? (
              <p className="learner-notice">
                <LocalizedMessage
                  resolution={message(
                    'learner.mission.no_recording_notice',
                  )}
                />
              </p>
            ) : null}
            {error ? (
              <p role="alert">
                <LocalizedMessage
                  resolution={message('learner.mission.submit_error')}
                />
              </p>
            ) : null}
            <button
              className="ge-button ge-button--primary"
              disabled={busy}
              type="submit"
            >
              <LocalizedMessage
                resolution={message(
                  busy
                    ? 'learner.mission.submitting'
                    : 'learner.mission.submit',
                )}
              />
            </button>
          </form>
        </section>
      ) : null}
    </article>
  )
}

interface MissionResultViewProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly onRetry: () => void
  readonly progress: LearnerProgress | null
  readonly result: MissionResult
  readonly titleRef: React.RefObject<HTMLHeadingElement | null>
}

function MissionResultView({
  locale,
  localizationPolicy,
  onRetry,
  progress,
  result,
  titleRef,
}: MissionResultViewProps) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  return (
    <section
      aria-labelledby="mission-result-title"
      className="learner-stack"
    >
      <h2
        id="mission-result-title"
        ref={titleRef}
        tabIndex={-1}
      >
        <LocalizedMessage resolution={message('learner.result.title')} />
      </h2>
      <section
        aria-label={message('learner.result.summary_label').text}
        className="learner-card learner-result"
      >
        <p className="learner-result__can-do">
          <GovernedText content={result.can_do} />
        </p>
        <dl className="learner-definition-list">
          <div>
            <dt>
              <LocalizedMessage
                resolution={message('learner.result.check_label')}
              />
            </dt>
            <dd>
              <LocalizedMessage
                resolution={message(
                  result.check_state === 'correct'
                    ? 'learner.result.check_correct'
                    : 'learner.result.check_incorrect',
                )}
              />
            </dd>
          </div>
          <div>
            <dt>
              <LocalizedMessage
                resolution={message('learner.result.knowledge_label')}
              />
            </dt>
            <dd>
              <LocalizedMessage
                resolution={message(
                  result.knowledge_state === 'mastered'
                    ? 'learner.result.knowledge_mastered'
                    : 'learner.result.knowledge_developing',
                )}
              />
            </dd>
          </div>
          <div>
            <dt>
              <LocalizedMessage
                resolution={message('learner.result.spoken_label')}
              />
            </dt>
            <dd>
              <LocalizedMessage
                resolution={message('learner.result.spoken_unassessed')}
              />
            </dd>
          </div>
          <div>
            <dt>
              <LocalizedMessage
                resolution={message(
                  'learner.result.local_capture_label',
                )}
              />
            </dt>
            <dd>
              <LocalizedMessage
                resolution={message(
                  result.local_capture_receipt_present
                    ? 'learner.result.local_capture_completed'
                    : 'learner.result.local_capture_not_recorded',
                )}
              />
            </dd>
          </div>
        </dl>
        <p className="learner-notice">
          <LocalizedMessage
            resolution={message('learner.result.spoken_boundary')}
          />
        </p>
      </section>

      <EvidenceSummary
        evidence={result.evidence}
        locale={locale}
        localizationPolicy={localizationPolicy}
      />

      <section
        aria-labelledby="review-title"
        className="learner-card"
      >
        <h3
          className="learner-card__title"
          id="review-title"
        >
          <LocalizedMessage
            resolution={message('learner.result.review_title')}
          />
        </h3>
        <p>
          <time dateTime={result.review.due_at}>
            {formatReviewDate(
              result.review.due_at,
              locale,
              result.review.timezone,
            )}
          </time>
        </p>
      </section>

      {progress === null ? null : (
        <ProgressSummary
          locale={locale}
          localizationPolicy={localizationPolicy}
          progress={progress}
        />
      )}
      <button
        className="ge-button"
        onClick={onRetry}
        type="button"
      >
        <LocalizedMessage resolution={message('learner.result.retry')} />
      </button>
    </section>
  )
}

interface EvidenceSummaryProps {
  readonly evidence: MissionResult['evidence']
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}

function EvidenceSummary({
  evidence,
  locale,
  localizationPolicy,
}: EvidenceSummaryProps) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  return (
    <section
      aria-labelledby="evidence-title"
      className="learner-card"
    >
      <h3
        className="learner-card__title"
        id="evidence-title"
      >
        <LocalizedMessage
          resolution={message('learner.result.evidence_title')}
        />
      </h3>
      <dl className="learner-definition-list">
        {(
          [
            ['learner.result.first', evidence.first_attempt_number],
            ['learner.result.latest', evidence.latest_attempt_number],
            ['learner.result.best', evidence.best_attempt_number],
          ] as const
        ).map(([key, value]) => (
          <div key={key}>
            <dt>
              <LocalizedMessage resolution={message(key)} />
            </dt>
            <dd>
              <LocalizedMessage
                resolution={resolveInterfaceMessage(
                  'learner.result.attempt_number',
                  locale,
                  localizationPolicy,
                  { number: value },
                )}
              />
            </dd>
          </div>
        ))}
      </dl>
    </section>
  )
}

interface ProgressSummaryProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly progress: LearnerProgress
}

function ProgressSummary({
  locale,
  localizationPolicy,
  progress,
}: ProgressSummaryProps) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  return (
    <section
      aria-labelledby="progress-summary-title"
      className="learner-card"
    >
      <h3
        className="learner-card__title"
        id="progress-summary-title"
      >
        <LocalizedMessage
          resolution={message('learner.progress.title')}
        />
      </h3>
      {progress.objectives.map((objective) => (
        <article
          className="learner-progress-objective"
          key={objective.objective_id}
        >
          <h4>
            <GovernedText content={objective.label} />
          </h4>
          <p>
            <LocalizedMessage
              resolution={message('learner.result.spoken_unassessed')}
            />
          </p>
          <p>
            <LocalizedMessage
              resolution={message(
                objective.local_capture_receipt_present
                  ? 'learner.result.local_capture_completed'
                  : 'learner.result.local_capture_not_recorded',
              )}
            />
          </p>
          <time dateTime={objective.review_due_at}>
            {formatReviewDate(
              objective.review_due_at,
              locale,
              objective.review_timezone,
            )}
          </time>
        </article>
      ))}
    </section>
  )
}

function GovernedText({ content }: { readonly content: GovernedContent }) {
  return (
    <MixedLanguageText
      data-content-hash={content.content_hash}
      data-content-revision={content.revision_id}
      segments={[{ locale: content.locale, text: content.text }]}
    />
  )
}

function goalMessageKey(goal: LearnerGoal): MessageKey {
  return {
    'everyday-conversation': 'learner.onboarding.goal_everyday',
    'travel-confidence': 'learner.onboarding.goal_travel',
    'workplace-confidence': 'learner.onboarding.goal_workplace',
  }[goal] as MessageKey
}

function offlineStatusMessageKey(
  status: OfflineStatus,
  online: boolean,
): MessageKey {
  if (!online) {
    return 'learner.offline.offline'
  }

  return {
    available: 'learner.offline.available',
    confirmation: 'learner.offline.warning',
    downloading: 'learner.offline.downloading',
    expired: 'learner.offline.expired',
    failed: 'learner.offline.failed',
    saved: 'learner.offline.pending_authority',
    synced: 'learner.offline.synced',
    syncing: 'learner.offline.syncing',
    unavailable: 'learner.offline.unavailable',
  }[status] as MessageKey
}

function formatReviewDate(
  dueAt: string,
  locale: SupportedLocale,
  timezone: string,
): string {
  try {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: 'medium',
      timeStyle: 'short',
      timeZone: timezone,
    }).format(new Date(dueAt))
  } catch {
    return dueAt
  }
}
