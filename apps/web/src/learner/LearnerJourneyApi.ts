import type {
  CompleteLearnerOnboardingRequest,
  CompleteOfflineMissionRequest,
  GovernedContent as ContractGovernedContent,
  KnowledgeState as ContractKnowledgeState,
  LearnerAttempt as ContractLearnerAttempt,
  LearnerGoal as ContractLearnerGoal,
  LearnerHome as ContractLearnerHome,
  LearnerMission as ContractLearnerMission,
  LearnerMissionResult as ContractLearnerMissionResult,
  LearnerProgress as ContractLearnerProgress,
  LocalCaptureReceipt,
  OfflineMissionBundle,
  RecordLearnerAttemptResponseRequest,
  SpokenState as ContractSpokenState,
} from '@global-english/contracts'

export const LEARNER_GOALS = [
  'everyday-conversation',
  'workplace-confidence',
  'travel-confidence',
] as const satisfies readonly ContractLearnerGoal[]

export type LearnerGoal = ContractLearnerGoal
export type GovernedContent = ContractGovernedContent
export type LearnerHome = ContractLearnerHome
export type OnboardingInput = CompleteLearnerOnboardingRequest
export type LearnerMission = ContractLearnerMission
export type LearnerAttempt = ContractLearnerAttempt
export type LocalRecordingMetadata = LocalCaptureReceipt
export type AttemptResponseInput = RecordLearnerAttemptResponseRequest
export type KnowledgeState = ContractKnowledgeState
export type SpokenState = ContractSpokenState
export type MissionResult = ContractLearnerMissionResult
export type LearnerProgress = ContractLearnerProgress
export type OfflineBundle = OfflineMissionBundle
export type OfflineCompletionInput = CompleteOfflineMissionRequest

export interface LearnerJourneyApi {
  home(signal?: AbortSignal): Promise<LearnerHome>

  updateOnboarding(
    input: OnboardingInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerHome>

  mission(
    missionId: string,
    enrollmentId: string,
    signal?: AbortSignal,
  ): Promise<LearnerMission>

  startAttempt(
    missionId: string,
    enrollmentId: string,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<LearnerAttempt>

  recordResponse(
    attemptId: string,
    input: AttemptResponseInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<void>

  submitAttempt(
    attemptId: string,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<MissionResult>

  progress(
    enrollmentId: string,
    signal?: AbortSignal,
  ): Promise<LearnerProgress>

  offlineBundle?(
    missionId: string,
    enrollmentId: string,
    signal?: AbortSignal,
  ): Promise<OfflineBundle>

  downloadMedia?(
    url: string,
    signal?: AbortSignal,
  ): Promise<Uint8Array<ArrayBuffer>>

  completeOffline?(
    input: OfflineCompletionInput,
    commandId: string,
    signal?: AbortSignal,
  ): Promise<MissionResult>
}

export class LearnerJourneyUnavailableError extends Error {
  readonly status?: number

  constructor(status?: number) {
    super('The learner journey could not be completed.')
    this.name = 'LearnerJourneyUnavailableError'
    this.status = status
  }
}
