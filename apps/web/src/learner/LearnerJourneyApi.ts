import type {
  CompleteLearnerOnboardingRequest,
  GovernedContent as ContractGovernedContent,
  KnowledgeState as ContractKnowledgeState,
  LearnerAttempt as ContractLearnerAttempt,
  LearnerGoal as ContractLearnerGoal,
  LearnerHome as ContractLearnerHome,
  LearnerMission as ContractLearnerMission,
  LearnerMissionResult as ContractLearnerMissionResult,
  LearnerProgress as ContractLearnerProgress,
  LocalCaptureReceipt,
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
}

export class LearnerJourneyUnavailableError extends Error {
  constructor() {
    super('The learner journey could not be completed.')
    this.name = 'LearnerJourneyUnavailableError'
  }
}
