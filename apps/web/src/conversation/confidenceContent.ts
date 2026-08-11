export type FriendSessionType = 'confidence' | 'skill_focus' | 'review'

export interface FriendSessionContent {
  readonly coachingMode: 'gentle' | 'tutor' | 'fluency'
  readonly listenLines: readonly string[]
  readonly minimumTurnsBeforeReflect: number
  readonly scenarioKey:
    | 'everyday-friend-prototype'
    | 'past-tense-skill-focus-prototype'
    | 'carry-sentence-review-prototype'
}

/**
 * Text-first model lines and bounded scenario selection for the three Friend
 * journeys. Spoken playback is an optional client capability, never a lesson
 * recording or authoritative learning result.
 */
export const FRIEND_SESSION_CONTENT: Readonly<
  Record<FriendSessionType, FriendSessionContent>
> = {
  confidence: {
    coachingMode: 'gentle',
    listenLines: [
      'I went to the market this morning.',
      'What did you do yesterday?',
    ],
    minimumTurnsBeforeReflect: 4,
    scenarioKey: 'everyday-friend-prototype',
  },
  skill_focus: {
    coachingMode: 'tutor',
    listenLines: [
      'Yesterday, I went to the market.',
      'I bought vegetables and came home.',
    ],
    minimumTurnsBeforeReflect: 5,
    scenarioKey: 'past-tense-skill-focus-prototype',
  },
  review: {
    coachingMode: 'gentle',
    listenLines: [
      'Yesterday, I went to the market.',
      'Please say the sentence once more in your own way.',
    ],
    minimumTurnsBeforeReflect: 2,
    scenarioKey: 'carry-sentence-review-prototype',
  },
}
