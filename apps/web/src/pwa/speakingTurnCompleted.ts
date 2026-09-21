export const SPEAKING_TURN_COMPLETED_EVENT = 'ge:speaking-turn-completed'

export function announceSpeakingTurnCompleted(): void {
  globalThis.dispatchEvent(new Event(SPEAKING_TURN_COMPLETED_EVENT))
}
