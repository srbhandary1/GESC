export type Phase1Module =
  | 'dashboard'
  | 'attendance'
  | 'ai-teacher'
  | 'daily-activity'
  | 'levels'
  | 'notices'
  | 'profile'

export const PHASE1_MODULES = [
  'dashboard',
  'attendance',
  'ai-teacher',
  'daily-activity',
  'levels',
  'notices',
  'profile',
] as const satisfies readonly Phase1Module[]

/** Surfaces that stay on the Home destination instead of primary nav. */
export const PHASE1_SECONDARY_HASHES = [
  'dashboard',
  'attendance',
  'ai-teacher',
  'daily-activity',
  'levels',
  'notices',
  'profile',
] as const satisfies readonly Phase1Module[]

export const PHASE1_FOCUS_AI_TOPICS = [
  'welcome',
  'level',
  'motivate',
] as const

export interface Phase1AttendanceDay {
  readonly date: string
  readonly status: 'present' | 'absent' | 'holiday' | 'upcoming'
}

export interface Phase1QuizQuestion {
  readonly id: string
  readonly prompt: string
  readonly options: readonly string[]
  readonly correctIndex: number
}

export interface Phase1Level {
  readonly id: string
  readonly kind: 'pre' | 'level'
  readonly number: number
  readonly status: 'locked' | 'current' | 'completed'
  readonly progressPercent: number
}

export type Phase1RoomId =
  | 'ai-teacher'
  | 'audio'
  | 'content'
  | 'conversation'
  | 'activity'
  | 'assignment'
  | 'assessment'
  | 'unlock'

export interface Phase1LevelRoom {
  readonly id: Phase1RoomId
  readonly available: boolean
}

export interface Phase1Notice {
  readonly id: 'n1' | 'n2' | 'n3' | 'n4' | 'n5' | 'n6'
  readonly category:
    | 'announcement'
    | 'trainer'
    | 'course'
    | 'holiday'
    | 'motivation'
    | 'event'
  readonly postedOn: string
}

export interface Phase1AiTopic {
  readonly id:
    | 'welcome'
    | 'structure'
    | 'level'
    | 'attendance'
    | 'score'
    | 'motivate'
}

const monthDays = (): Phase1AttendanceDay[] => {
  const days: Phase1AttendanceDay[] = []
  for (let day = 1; day <= 31; day += 1) {
    const date = `2026-08-${String(day).padStart(2, '0')}`
    if (day > 5) {
      days.push({ date, status: 'upcoming' })
      continue
    }
    if (day === 3) {
      days.push({ date, status: 'holiday' })
      continue
    }
    if (day === 2) {
      days.push({ date, status: 'absent' })
      continue
    }
    days.push({ date, status: 'present' })
  }
  return days
}

export const phase1Attendance = {
  todayStatus: 'present' as const,
  presentDays: 3,
  workingDaysSoFar: 4,
  percentage: 75,
  streakDays: 3,
  days: monthDays(),
}

/**
 * Level 1 Can listen/check content restored from commit `9818fe2`
 * (`apps/web/src/demo/localDemo.ts` sessionDetail + LPL-01 blueprint
 * `docs/evidence/lpl-01/level1-authored-blueprint-draft.json`).
 * Learning-content strings stay English across UI locales.
 */
export const CAN_MODEL_LINE = 'I can speak English. Can I help you?'

export const phase1DailyActivity = {
  monthlyScore: 42,
  monthlyMaximum: 155,
  conceptKey: 'can',
  modelLine: CAN_MODEL_LINE,
  questions: [
    {
      id: 'can-01',
      prompt: 'Choose the correct sentence.',
      options: [
        'I can speak English.',
        'I can speaking English.',
        'I can to speak English.',
      ],
      correctIndex: 0,
    },
    {
      id: 'can-02',
      prompt: 'Choose the permission question.',
      options: [
        'Can I use your pen?',
        'I can use your pen?',
        'Can I using your pen?',
      ],
      correctIndex: 0,
    },
    {
      id: 'can-03',
      prompt: 'Choose the answer.',
      options: [
        'Yes, you can.',
        'Yes, you can to.',
        'Yes, you can sitting.',
      ],
      correctIndex: 0,
    },
    {
      id: 'can-04',
      prompt: 'Choose the correct negative.',
      options: [
        'I cannot swim.',
        'I cannot swimming.',
        'I cannot to swim.',
      ],
      correctIndex: 0,
    },
    {
      id: 'can-05',
      prompt: 'Choose the permission question.',
      options: [
        'Can I sit here?',
        'I can sit here?',
        'Can I sitting here?',
      ],
      correctIndex: 0,
    },
  ] satisfies readonly Phase1QuizQuestion[],
}

export const phase1Levels: readonly Phase1Level[] = [
  {
    id: 'pre-1',
    kind: 'pre',
    number: 1,
    status: 'completed',
    progressPercent: 100,
  },
  {
    id: 'pre-2',
    kind: 'pre',
    number: 2,
    status: 'completed',
    progressPercent: 100,
  },
  {
    id: 'level-1',
    kind: 'level',
    number: 1,
    status: 'current',
    progressPercent: 35,
  },
  ...Array.from({ length: 16 }, (_, index) => ({
    id: `level-${index + 2}`,
    kind: 'level' as const,
    number: index + 2,
    status: 'locked' as const,
    progressPercent: 0,
  })),
]

export const phase1LevelRooms: readonly Phase1LevelRoom[] = [
  { id: 'ai-teacher', available: true },
  { id: 'audio', available: true },
  { id: 'content', available: true },
  { id: 'conversation', available: true },
  { id: 'activity', available: true },
  { id: 'assignment', available: false },
  { id: 'assessment', available: false },
  { id: 'unlock', available: false },
]

export const phase1Notices: readonly Phase1Notice[] = [
  { id: 'n1', category: 'announcement', postedOn: '2026-08-04' },
  { id: 'n2', category: 'trainer', postedOn: '2026-08-03' },
  { id: 'n3', category: 'course', postedOn: '2026-08-02' },
  { id: 'n4', category: 'holiday', postedOn: '2026-08-01' },
  { id: 'n5', category: 'motivation', postedOn: '2026-08-01' },
  { id: 'n6', category: 'event', postedOn: '2026-07-30' },
]

export const phase1AiTopics: readonly Phase1AiTopic[] = [
  { id: 'welcome' },
  { id: 'structure' },
  { id: 'level' },
  { id: 'attendance' },
  { id: 'score' },
  { id: 'motivate' },
]

export function moduleFromHash(hash: string): Phase1Module {
  const value = hash.replace(/^#/, '')
  if (value === 'home' || value === '') {
    return 'dashboard'
  }
  if ((PHASE1_MODULES as readonly string[]).includes(value)) {
    return value as Phase1Module
  }
  return 'dashboard'
}
