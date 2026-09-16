import {
  INTERFACE_LOCALES,
  type MessageKey,
  type MessageParameters,
  type ResolutionPolicy,
  type SupportedLocale,
} from '@global-english/localization'
import { useEffect, useRef, useState, type ReactNode } from 'react'
import type {
  ConversationJourneyApi,
  VoiceConsents,
} from '../conversation/ConversationJourneyApi'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { OnboardingStepper } from '../ui/OnboardingStepper'
import type { AudioRecorder } from './BrowserAudioRecorder'
import type {
  LearnerGoal,
  LearnerHome,
  LearnerJourneyApi,
  OnboardingInput,
} from './LearnerJourneyApi'
import { LEARNER_GOALS, LearnerJourneyUnavailableError } from './LearnerJourneyApi'
import { RecordingTurn } from './RecordingTurn'
import {
  clearFirstUseResume,
  FIRST_USE_STEPS,
  readFirstUseResume,
  writeFirstUseResume,
  type FirstUseStep,
  type PlacementChoice,
} from './FirstUseOnboardingResume'
type ConsentPurpose =
  | 'voice_upload_and_transcription'
  | 'conversation_provider_processing'
type OnboardingErrorKind = 'validation' | 'offline' | 'timeout' | 'server'

const ONBOARDING_TIMEOUT_MS = 15_000

const goalKeys: Readonly<Record<LearnerGoal, MessageKey>> = {
  'everyday-conversation': 'learner.onboarding.goal_everyday',
  'travel-confidence': 'learner.onboarding.goal_travel',
  'workplace-confidence': 'learner.onboarding.goal_workplace',
}

const localeKeys: Readonly<Record<(typeof INTERFACE_LOCALES)[number], MessageKey>> = {
  'en-IN': 'locale.en_IN',
  'kn-IN': 'locale.kn_IN',
}

const titles: Readonly<Record<FirstUseStep, MessageKey>> = {
  acknowledgement: 'learner.onboarding.acknowledgement_title',
  adult: 'learner.onboarding.adult_title',
  'audio-output': 'learner.onboarding.audio_title',
  consent: 'learner.onboarding.consent_title',
  goal: 'learner.onboarding.goal_title',
  microphone: 'learner.onboarding.microphone_title',
  placement: 'learner.onboarding.placement_title',
  practice: 'learner.onboarding.practice_title',
  preferences: 'learner.onboarding.preferences_title',
  welcome: 'learner.onboarding.welcome_title',
}

const descriptions: Readonly<Record<FirstUseStep, MessageKey>> = {
  acknowledgement: 'learner.onboarding.acknowledgement_hint',
  adult: 'learner.onboarding.adult_hint',
  'audio-output': 'learner.onboarding.audio_hint',
  consent: 'learner.onboarding.consent_hint',
  goal: 'learner.onboarding.introduction',
  microphone: 'learner.onboarding.microphone_hint',
  placement: 'learner.onboarding.placement_hint',
  practice: 'learner.onboarding.practice_hint',
  preferences: 'learner.onboarding.preferences_hint',
  welcome: 'learner.onboarding.welcome_hint',
}

export interface FirstUseOnboardingProps {
  readonly api: LearnerJourneyApi
  readonly audioRecorder: AudioRecorder
  readonly conversationApi?: Pick<
    ConversationJourneyApi,
    'voiceConsents' | 'grantVoiceConsent'
  >
  readonly createClientId: () => string
  readonly initialHome: LearnerHome
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly onComplete: (home: LearnerHome) => void
  readonly onLocaleChange?: (locale: SupportedLocale) => void
  readonly playAudioOutput?: () => Promise<void>
  readonly titleRef: React.RefObject<HTMLHeadingElement | null>
}

export function FirstUseOnboarding({
  api,
  audioRecorder,
  conversationApi,
  createClientId,
  initialHome,
  locale,
  localizationPolicy,
  onComplete,
  onLocaleChange,
  playAudioOutput = playOutputTone,
  titleRef,
}: FirstUseOnboardingProps) {
  const restored = useRef(readFirstUseResume()).current
  const [step, setStep] = useState<FirstUseStep>(
    restored?.step ?? 'welcome',
  )
  const [adultConfirmed, setAdultConfirmed] = useState(
    restored?.adultConfirmed ?? initialHome.onboarding.adult_confirmed,
  )
  const [goal, setGoal] = useState<LearnerGoal | null>(
    restored?.goal ?? initialHome.onboarding.goal_key,
  )
  const [placement, setPlacement] = useState<PlacementChoice | null>(
    restored?.placement ?? null,
  )
  const [largerText, setLargerText] = useState(false)
  const [reducedMotion, setReducedMotion] = useState(false)
  const [busy, setBusy] = useState(false)
  const [commandLocked, setCommandLocked] = useState(false)
  const [error, setError] = useState<MessageKey | null>(null)
  const [savedHome, setSavedHome] = useState<LearnerHome | null>(
    initialHome.onboarding.completed || restored?.onboardingSaved === true
      ? initialHome
      : null,
  )
  const [audioState, setAudioState] = useState<
    'idle' | 'playing' | 'heard' | 'deferred' | 'error'
  >('idle')
  const [consentListing, setConsentListing] = useState<VoiceConsents | null>(null)
  const [consentLoading, setConsentLoading] = useState(false)
  const [consentLoadFailed, setConsentLoadFailed] = useState(false)
  const [selectedConsents, setSelectedConsents] = useState<
    ReadonlySet<ConsentPurpose>
  >(new Set())
  const [microphoneOutcome, setMicrophoneOutcome] = useState<
    'tested' | 'typed' | null
  >(null)
  const [practiceMode, setPracticeMode] = useState<'spoken' | 'typed' | null>(null)
  const [practiceText, setPracticeText] = useState('')
  const [practiceSpoken, setPracticeSpoken] = useState(false)
  const commandId = useRef<string | null>(null)
  const pendingInput = useRef<OnboardingInput | null>(null)
  const consentCommands = useRef<Record<ConsentPurpose, string> | null>(null)
  commandId.current ??= createClientId()
  consentCommands.current ??= {
    conversation_provider_processing: createClientId(),
    voice_upload_and_transcription: createClientId(),
  }
  const message = (key: MessageKey, parameters?: MessageParameters) =>
    resolveInterfaceMessage(key, locale, localizationPolicy, parameters)

  useEffect(() => {
    writeFirstUseResume({
      adultConfirmed,
      goal,
      onboardingSaved: savedHome !== null,
      placement,
      step,
      version: 1,
    })
  }, [adultConfirmed, goal, placement, savedHome, step])

  useEffect(() => {
    if (step !== 'consent') {
      return
    }

    void loadConsents()
    // The API object is stable for the mounted journey; locale changes reload.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [locale, step])

  const moveTo = (next: FirstUseStep) => {
    setError(null)
    setStep(next)
  }

  const submitAuthoritativeOnboarding = async () => {
    let input = pendingInput.current
    if (input === null) {
      if (!adultConfirmed || goal === null) {
        setError('learner.onboarding.error_validation')
        return
      }
      input = { adult_confirmed: true, goal_key: goal }
      pendingInput.current = input
      setCommandLocked(true)
    }

    if (globalThis.navigator?.onLine === false) {
      setError('learner.onboarding.error_offline')
      return
    }

    setBusy(true)
    setError(null)
    const controller = new AbortController()
    let timedOut = false
    const timeout = globalThis.setTimeout(() => {
      timedOut = true
      controller.abort()
    }, ONBOARDING_TIMEOUT_MS)

    try {
      const submissionCommandId = commandId.current ?? createClientId()
      commandId.current = submissionCommandId
      const home = await api.updateOnboarding(
        input,
        submissionCommandId,
        controller.signal,
      )
      pendingInput.current = null
      setCommandLocked(false)
      setSavedHome(home)
      moveTo('placement')
    } catch (submissionError) {
      setError(onboardingErrorMessageKey(
        classifyOnboardingError(submissionError, timedOut),
      ))
    } finally {
      globalThis.clearTimeout(timeout)
      setBusy(false)
    }
  }

  const editChoices = () => {
    pendingInput.current = null
    commandId.current = createClientId()
    setCommandLocked(false)
    setError(null)
  }

  async function loadConsents() {
    if (conversationApi?.voiceConsents === undefined) {
      setConsentLoadFailed(true)
      return
    }

    setConsentLoading(true)
    setConsentLoadFailed(false)
    try {
      setConsentListing(await conversationApi.voiceConsents(locale))
    } catch {
      setConsentLoadFailed(true)
    } finally {
      setConsentLoading(false)
    }
  }

  const grantSelectedConsents = async () => {
    if (selectedConsents.size === 0) {
      moveTo('microphone')
      return
    }
    if (globalThis.navigator?.onLine === false) {
      setError('learner.onboarding.consent_error_offline')
      return
    }
    if (conversationApi?.grantVoiceConsent === undefined) {
      setError('learner.onboarding.consent_error_server')
      return
    }

    setBusy(true)
    setError(null)
    try {
      const purposeCommands = consentCommands.current ?? {
        conversation_provider_processing: createClientId(),
        voice_upload_and_transcription: createClientId(),
      }
      consentCommands.current = purposeCommands
      for (const purpose of selectedConsents) {
        if (isPurposeGranted(consentListing, purpose)) {
          continue
        }
        await conversationApi.grantVoiceConsent(
          { purpose, requested_locale: locale },
          purposeCommands[purpose],
        )
      }
      moveTo('microphone')
    } catch {
      setError('learner.onboarding.consent_error_server')
    } finally {
      setBusy(false)
    }
  }

  const continueFlow = () => {
    if (step === 'welcome') {
      moveTo('adult')
    } else if (step === 'adult') {
      if (!adultConfirmed) {
        setError('learner.onboarding.error_validation')
      } else {
        moveTo('preferences')
      }
    } else if (step === 'preferences') {
      moveTo('goal')
    } else if (step === 'goal') {
      void submitAuthoritativeOnboarding()
    } else if (step === 'placement') {
      if (placement === null) {
        setError('learner.onboarding.placement_error')
      } else {
        moveTo('audio-output')
      }
    } else if (step === 'audio-output') {
      if (audioState !== 'heard' && audioState !== 'deferred') {
        setError('learner.onboarding.audio_error_choice')
      } else {
        moveTo('consent')
      }
    } else if (step === 'consent') {
      void grantSelectedConsents()
    } else if (step === 'microphone') {
      if (microphoneOutcome === null) {
        setError('learner.onboarding.microphone_error_choice')
      } else {
        moveTo('practice')
      }
    } else if (step === 'practice') {
      if (
        (practiceMode === 'typed' && practiceText.trim().length > 0) ||
        (practiceMode === 'spoken' && practiceSpoken)
      ) {
        moveTo('acknowledgement')
      } else {
        setError('learner.onboarding.practice_error')
      }
    } else {
      clearFirstUseResume()
      onComplete(savedHome ?? initialHome)
    }
  }

  const back = () => {
    const index = FIRST_USE_STEPS.indexOf(step)
    const minimumIndex = savedHome === null ? 0 : FIRST_USE_STEPS.indexOf('placement')
    if (index > minimumIndex) {
      moveTo(FIRST_USE_STEPS[index - 1]!)
    }
  }

  const playOutput = async () => {
    setAudioState('playing')
    setError(null)
    try {
      await playAudioOutput()
      setAudioState('heard')
    } catch {
      setAudioState('error')
      setError('learner.onboarding.audio_error')
    }
  }

  const content = renderStepContent({
    adultConfirmed,
    audioRecorder,
    audioState,
    busy,
    commandLocked,
    createClientId,
    consentListing,
    consentLoadFailed,
    consentLoading,
    editChoices,
    goal,
    largerText,
    locale,
    message,
    microphoneOutcome,
    onLocaleChange,
    placement,
    playOutput,
    practiceMode,
    practiceSpoken,
    practiceText,
    reducedMotion,
    retryConsentLoad: () => void loadConsents(),
    selectedConsents,
    setAdultConfirmed,
    setAudioState,
    setGoal,
    setLargerText,
    setMicrophoneOutcome,
    setPlacement,
    setPracticeMode,
    setPracticeSpoken,
    setPracticeText,
    setReducedMotion,
    setSelectedConsents,
    step,
  })

  return (
    <div
      className="ge-onboarding-screen"
      data-larger-text={largerText ? 'true' : undefined}
      data-reduced-motion={reducedMotion ? 'true' : undefined}
    >
      <OnboardingStepper
        activeStep={step}
        backAvailable={
          FIRST_USE_STEPS.indexOf(step) >
          (savedHome === null ? 0 : FIRST_USE_STEPS.indexOf('placement'))
        }
        busy={busy}
        canAdvance
        commandLocked={commandLocked}
        content={content}
        continueLabel={
          commandLocked
            ? message('learner.onboarding.retry_same')
            : step === 'acknowledgement'
              ? message('learner.onboarding.open_home')
              : undefined
        }
        description={message(descriptions[step])}
        error={error === null ? null : <LocalizedMessage resolution={message(error)} />}
        locale={locale}
        message={message}
        onBack={back}
        onContinue={continueFlow}
        selection={{ adultConfirmed, goal, level: null }}
        showCoach={false}
        steps={FIRST_USE_STEPS}
        title={message(titles[step])}
        titleRef={titleRef}
      />
    </div>
  )
}

interface StepContentInput {
  readonly adultConfirmed: boolean
  readonly audioRecorder: AudioRecorder
  readonly audioState: 'idle' | 'playing' | 'heard' | 'deferred' | 'error'
  readonly busy: boolean
  readonly commandLocked: boolean
  readonly createClientId: () => string
  readonly consentListing: VoiceConsents | null
  readonly consentLoadFailed: boolean
  readonly consentLoading: boolean
  readonly editChoices: () => void
  readonly goal: LearnerGoal | null
  readonly largerText: boolean
  readonly locale: SupportedLocale
  readonly message: (
    key: MessageKey,
    parameters?: MessageParameters,
  ) => ReturnType<typeof resolveInterfaceMessage>
  readonly microphoneOutcome: 'tested' | 'typed' | null
  readonly onLocaleChange?: (locale: SupportedLocale) => void
  readonly placement: PlacementChoice | null
  readonly playOutput: () => Promise<void>
  readonly practiceMode: 'spoken' | 'typed' | null
  readonly practiceSpoken: boolean
  readonly practiceText: string
  readonly reducedMotion: boolean
  readonly retryConsentLoad: () => void
  readonly selectedConsents: ReadonlySet<ConsentPurpose>
  readonly setAdultConfirmed: (value: boolean) => void
  readonly setAudioState: (value: 'idle' | 'playing' | 'heard' | 'deferred' | 'error') => void
  readonly setGoal: (value: LearnerGoal) => void
  readonly setLargerText: (value: boolean) => void
  readonly setMicrophoneOutcome: (value: 'tested' | 'typed' | null) => void
  readonly setPlacement: (value: PlacementChoice) => void
  readonly setPracticeMode: (value: 'spoken' | 'typed') => void
  readonly setPracticeSpoken: (value: boolean) => void
  readonly setPracticeText: (value: string) => void
  readonly setReducedMotion: (value: boolean) => void
  readonly setSelectedConsents: (value: ReadonlySet<ConsentPurpose>) => void
  readonly step: FirstUseStep
}

function renderStepContent(input: StepContentInput): ReactNode {
  const { message, step } = input

  if (step === 'welcome') {
    return <ul className="ge-onboarding__value-list">
      <li><LocalizedMessage resolution={message('learner.onboarding.welcome_value_plan')} /></li>
      <li><LocalizedMessage resolution={message('learner.onboarding.welcome_value_practice')} /></li>
      <li><LocalizedMessage resolution={message('learner.onboarding.welcome_value_control')} /></li>
    </ul>
  }

  if (step === 'adult') {
    return <label className="ge-select-card ge-select-card--confirm" data-selected={input.adultConfirmed ? 'true' : undefined}>
      <span><LocalizedMessage resolution={message('learner.onboarding.adult_confirmation')} /></span>
      <input checked={input.adultConfirmed} disabled={input.busy || input.commandLocked} onChange={(event) => input.setAdultConfirmed(event.currentTarget.checked)} type="checkbox" />
    </label>
  }

  if (step === 'preferences') {
    return <>
      <fieldset className="ge-select-grid">
        <legend><LocalizedMessage resolution={message('learner.onboarding.language_legend')} /></legend>
        {INTERFACE_LOCALES.map((candidate) => <label className="ge-select-card" data-selected={input.locale === candidate ? 'true' : undefined} key={candidate}>
          <span lang={candidate}><LocalizedMessage resolution={message(localeKeys[candidate])} /></span>
          <input checked={input.locale === candidate} name="onboarding-language" onChange={() => input.onLocaleChange?.(candidate)} type="radio" />
        </label>)}
      </fieldset>
      <fieldset className="ge-select-grid">
        <legend><LocalizedMessage resolution={message('learner.onboarding.accessibility_legend')} /></legend>
        <label className="ge-select-card" data-selected={input.largerText ? 'true' : undefined}>
          <span><LocalizedMessage resolution={message('learner.onboarding.accessibility_larger_text')} /></span>
          <input checked={input.largerText} onChange={(event) => input.setLargerText(event.currentTarget.checked)} type="checkbox" />
        </label>
        <label className="ge-select-card" data-selected={input.reducedMotion ? 'true' : undefined}>
          <span><LocalizedMessage resolution={message('learner.onboarding.accessibility_reduce_motion')} /></span>
          <input checked={input.reducedMotion} onChange={(event) => input.setReducedMotion(event.currentTarget.checked)} type="checkbox" />
        </label>
      </fieldset>
      <p><LocalizedMessage resolution={message('learner.onboarding.preferences_ephemeral')} /></p>
    </>
  }

  if (step === 'goal') {
    return <>
      <fieldset className="ge-select-grid">
        <legend><LocalizedMessage resolution={message('learner.onboarding.goal_legend')} /></legend>
        {LEARNER_GOALS.map((candidate) => <label className="ge-select-card" data-selected={input.goal === candidate ? 'true' : undefined} key={candidate}>
          <span><LocalizedMessage resolution={message(goalKeys[candidate])} /></span>
          <input checked={input.goal === candidate} disabled={input.busy || input.commandLocked} name="learner-goal" onChange={() => input.setGoal(candidate)} type="radio" value={candidate} />
        </label>)}
      </fieldset>
      {input.commandLocked ? <button className="ge-button" disabled={input.busy} onClick={input.editChoices} type="button"><LocalizedMessage resolution={message('learner.onboarding.edit_choices')} /></button> : null}
    </>
  }

  if (step === 'placement') {
    return <fieldset className="ge-select-grid">
      <legend><LocalizedMessage resolution={message('learner.onboarding.placement_legend')} /></legend>
      {(['beginning', 'deferred'] as const).map((candidate) => <label className="ge-select-card" data-selected={input.placement === candidate ? 'true' : undefined} key={candidate}>
        <span><strong><LocalizedMessage resolution={message(candidate === 'beginning' ? 'learner.onboarding.placement_beginning' : 'learner.onboarding.placement_defer')} /></strong><span className="ge-select-card__hint"><LocalizedMessage resolution={message(candidate === 'beginning' ? 'learner.onboarding.placement_beginning_hint' : 'learner.onboarding.placement_defer_hint')} /></span></span>
        <input checked={input.placement === candidate} name="onboarding-placement" onChange={() => input.setPlacement(candidate)} type="radio" value={candidate} />
      </label>)}
    </fieldset>
  }

  if (step === 'audio-output') {
    return <div className="ge-onboarding__action-stack">
      <button className="ge-button" disabled={input.audioState === 'playing'} onClick={() => void input.playOutput()} type="button"><LocalizedMessage resolution={message(input.audioState === 'playing' ? 'learner.onboarding.audio_playing' : 'learner.onboarding.audio_play')} /></button>
      {input.audioState === 'heard' ? <p role="status"><LocalizedMessage resolution={message('learner.onboarding.audio_heard')} /></p> : null}
      <button className="ge-button" onClick={() => input.setAudioState('deferred')} type="button"><LocalizedMessage resolution={message('learner.onboarding.audio_defer')} /></button>
    </div>
  }

  if (step === 'consent') {
    return <div className="ge-onboarding__action-stack">
      {input.consentLoading ? <p role="status"><LocalizedMessage resolution={message('learner.onboarding.consent_loading')} /></p> : null}
      {input.consentListing === null ? null : <aside className="ge-onboarding__notice" lang={input.consentListing.notice.resolved_locale}><p>{input.consentListing.notice.notice}</p></aside>}
      {input.consentLoadFailed ? <div role="alert"><p><LocalizedMessage resolution={message('learner.onboarding.consent_notice_unavailable')} /></p><button className="ge-button" onClick={input.retryConsentLoad} type="button"><LocalizedMessage resolution={message('learner.onboarding.retry')} /></button></div> : null}
      <ConsentChoice input={input} purpose="voice_upload_and_transcription" />
      <ConsentChoice input={input} purpose="conversation_provider_processing" />
      <p><LocalizedMessage resolution={message('learner.onboarding.consent_optional')} /></p>
    </div>
  }

  if (step === 'microphone') {
    return <div className="ge-onboarding__action-stack">
      <p lang="en-IN">“Testing, one, two.”</p>
      <RecordingTurn createClientId={input.createClientId} labels={recordingLabels(message, 'microphone')} onCaptureChange={(capture) => input.setMicrophoneOutcome(capture === null ? null : 'tested')} onContinueWithoutRecording={() => input.setMicrophoneOutcome('typed')} recorder={input.audioRecorder} />
      <button className="ge-button" onClick={() => input.setMicrophoneOutcome('typed')} type="button"><LocalizedMessage resolution={message('learner.onboarding.microphone_type_instead')} /></button>
      {input.microphoneOutcome === null ? null : <p role="status"><LocalizedMessage resolution={message(input.microphoneOutcome === 'tested' ? 'learner.onboarding.microphone_tested' : 'learner.onboarding.microphone_deferred')} /></p>}
    </div>
  }

  if (step === 'practice') {
    return <div className="ge-onboarding__action-stack">
      <p><strong><LocalizedMessage resolution={message('learner.onboarding.practice_model_label')} /></strong></p>
      <p lang="en-IN">“Good morning. I’m ready to practise English.”</p>
      <fieldset className="ge-select-grid">
        <legend><LocalizedMessage resolution={message('learner.onboarding.practice_choice')} /></legend>
        <label className="ge-select-card" data-selected={input.practiceMode === 'typed' ? 'true' : undefined}><span><LocalizedMessage resolution={message('learner.onboarding.practice_typed')} /></span><input checked={input.practiceMode === 'typed'} name="practice-mode" onChange={() => input.setPracticeMode('typed')} type="radio" /></label>
        <label className="ge-select-card" data-selected={input.practiceMode === 'spoken' ? 'true' : undefined}><span><LocalizedMessage resolution={message('learner.onboarding.practice_spoken')} /></span><input checked={input.practiceMode === 'spoken'} name="practice-mode" onChange={() => input.setPracticeMode('spoken')} type="radio" /></label>
      </fieldset>
      {input.practiceMode === 'typed' ? <label><LocalizedMessage resolution={message('learner.onboarding.practice_typed_label')} /><textarea autoComplete="off" onChange={(event) => input.setPracticeText(event.currentTarget.value)} value={input.practiceText} /></label> : null}
      {input.practiceMode === 'spoken' ? <RecordingTurn createClientId={input.createClientId} labels={recordingLabels(message, 'practice')} onCaptureChange={(capture) => input.setPracticeSpoken(capture !== null)} onContinueWithoutRecording={() => input.setPracticeMode('typed')} recorder={input.audioRecorder} /> : null}
      <p><LocalizedMessage resolution={message('learner.onboarding.practice_private')} /></p>
    </div>
  }

  return <div className="ge-onboarding__acknowledgement">
    <p><LocalizedMessage resolution={message('learner.onboarding.acknowledgement_ready')} /></p>
    <p><LocalizedMessage resolution={message(input.placement === 'deferred' ? 'learner.onboarding.acknowledgement_deferred' : 'learner.onboarding.acknowledgement_beginning')} /></p>
    <p><LocalizedMessage resolution={message('learner.onboarding.acknowledgement_control')} /></p>
  </div>
}

function ConsentChoice({ input, purpose }: { readonly input: StepContentInput; readonly purpose: ConsentPurpose }) {
  const granted = isPurposeGranted(input.consentListing, purpose)
  const selected = input.selectedConsents.has(purpose)
  const upload = purpose === 'voice_upload_and_transcription'
  return <label className="ge-select-card" data-selected={selected || granted ? 'true' : undefined}>
    <span><strong><LocalizedMessage resolution={input.message(upload ? 'learner.onboarding.consent_recording_title' : 'learner.onboarding.consent_ai_title')} /></strong><span className="ge-select-card__hint"><LocalizedMessage resolution={input.message(upload ? 'learner.onboarding.consent_recording_copy' : 'learner.onboarding.consent_ai_copy')} /></span>{granted ? <span className="ge-select-card__hint"><LocalizedMessage resolution={input.message('learner.onboarding.consent_already_granted')} /></span> : null}</span>
    <input checked={selected || granted} disabled={granted || input.busy || input.consentLoading || input.consentLoadFailed} onChange={(event) => {
      const next = new Set(input.selectedConsents)
      if (event.currentTarget.checked) next.add(purpose)
      else next.delete(purpose)
      input.setSelectedConsents(next)
    }} type="checkbox" />
  </label>
}

function recordingLabels(message: StepContentInput['message'], context: 'microphone' | 'practice') {
  return {
    backCancelled: message('learner.recording.back_cancelled').text,
    continueWithout: message('learner.onboarding.microphone_type_instead').text,
    continueWithoutExplanation: message('learner.onboarding.microphone_fallback').text,
    controls: {
      blocked: message('learner.recording.blocked').text,
      cancel: message('learner.recording.cancel').text,
      error: message('learner.recording.error').text,
      idle: message('learner.recording.idle').text,
      pause: message('learner.recording.pause').text,
      paused: message('learner.recording.paused').text,
      processing: message('learner.recording.processing').text,
      ready: message('learner.recording.ready').text,
      recording: message('learner.recording.recording').text,
      region: message(context === 'practice' ? 'learner.onboarding.practice_recording_region' : 'learner.onboarding.microphone_recording_region').text,
      requestingPermission: message('learner.recording.requesting_permission').text,
      resume: message('learner.recording.resume').text,
      start: message('learner.recording.start').text,
      stop: message('learner.recording.stop').text,
    },
    permissionContinue: message('learner.recording.permission_continue').text,
    permissionDeniedOnce: message('learner.recording.permission_denied_once').text,
    permissionDeniedPermanently: message('learner.recording.permission_denied_permanently').text,
    permissionExplanation: message('learner.onboarding.microphone_permission_copy').text,
    playbackHelp: message('learner.onboarding.microphone_playback_help').text,
    playbackLabel: message('learner.recording.playback_label').text,
    retry: message('learner.recording.retry').text,
  }
}

function isPurposeGranted(listing: VoiceConsents | null, purpose: ConsentPurpose): boolean {
  return listing?.consents.some((consent) => consent.purpose === purpose && consent.state === 'granted') ?? false
}

function classifyOnboardingError(error: unknown, timedOut: boolean): OnboardingErrorKind {
  if (timedOut || (typeof error === 'object' && error !== null && 'name' in error && error.name === 'TimeoutError')) return 'timeout'
  if (globalThis.navigator?.onLine === false) return 'offline'
  if (error instanceof LearnerJourneyUnavailableError && (error.status === 400 || error.status === 422)) return 'validation'
  return 'server'
}

function onboardingErrorMessageKey(error: OnboardingErrorKind): MessageKey {
  return ({ offline: 'learner.onboarding.error_offline', server: 'learner.onboarding.error_server', timeout: 'learner.onboarding.error_timeout', validation: 'learner.onboarding.error_validation' } as const)[error]
}

async function playOutputTone(): Promise<void> {
  const AudioContextConstructor = globalThis.AudioContext
  if (AudioContextConstructor === undefined) throw new Error('Audio output unavailable')
  const context = new AudioContextConstructor()
  const oscillator = context.createOscillator()
  const gain = context.createGain()
  gain.gain.value = 0.08
  oscillator.frequency.value = 440
  oscillator.connect(gain)
  gain.connect(context.destination)
  await new Promise<void>((resolve) => {
    oscillator.addEventListener('ended', () => resolve(), { once: true })
    oscillator.start()
    oscillator.stop(context.currentTime + 0.25)
  })
  await context.close()
}
