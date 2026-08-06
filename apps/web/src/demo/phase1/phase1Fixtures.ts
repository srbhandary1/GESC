export type Phase1Module =
  | 'dashboard'
  | 'attendance'
  | 'ai-teacher'
  | 'daily-activity'
  | 'levels'
  | 'notices'

export const PHASE1_MODULES = [
  'dashboard',
  'attendance',
  'ai-teacher',
  'daily-activity',
  'levels',
  'notices',
] as const satisfies readonly Phase1Module[]

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
  readonly title: string
  readonly status: 'locked' | 'current' | 'completed'
  readonly progressPercent: number
}

export interface Phase1LevelRoom {
  readonly id: string
  readonly title: string
  readonly summary: string
  readonly available: boolean
}

export interface Phase1Notice {
  readonly id: string
  readonly category:
    | 'announcement'
    | 'trainer'
    | 'course'
    | 'holiday'
    | 'motivation'
    | 'event'
  readonly title: string
  readonly body: string
  readonly postedOn: string
}

export interface Phase1AiTopic {
  readonly id: string
  readonly labelEn: string
  readonly labelKn: string
  readonly replyEn: string
  readonly replyKn: string
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
  monthLabel: 'August 2026',
  presentDays: 3,
  workingDaysSoFar: 4,
  percentage: 75,
  days: monthDays(),
}

export const phase1DailyActivity = {
  audioLessonTitle: 'Introduce yourself clearly',
  audioLessonSummary:
    'Listen to a short model introduction, then practise the same lines.',
  audioLessonDuration: '4 min',
  quizTitle: "Today's 5-mark check",
  monthlyScore: 42,
  monthlyMaximum: 155,
  questions: [
    {
      id: 'q1',
      prompt: 'Choose the natural greeting.',
      options: [
        'Hello, my name is Ravi.',
        'Hello name Ravi.',
        'Name is Ravi hello.',
      ],
      correctIndex: 0,
    },
    {
      id: 'q2',
      prompt: 'Which sentence is correct?',
      options: [
        'I am learning English.',
        'I learning English.',
        'I is learn English.',
      ],
      correctIndex: 0,
    },
    {
      id: 'q3',
      prompt: 'Pick the polite close.',
      options: ['Nice to meet you.', 'Meet you nice.', 'You meet nice.'],
      correctIndex: 0,
    },
    {
      id: 'q4',
      prompt: 'Complete: "I work ___ a bank."',
      options: ['in', 'on', 'at to'],
      correctIndex: 0,
    },
    {
      id: 'q5',
      prompt: 'Best reply to "How are you?"',
      options: [
        'I am fine, thank you.',
        'I fine thank.',
        'Fine I am thank.',
      ],
      correctIndex: 0,
    },
  ] satisfies readonly Phase1QuizQuestion[],
}

export const phase1Levels: readonly Phase1Level[] = [
  {
    id: 'pre-1',
    title: 'Pre-Level 1',
    status: 'completed',
    progressPercent: 100,
  },
  {
    id: 'pre-2',
    title: 'Pre-Level 2',
    status: 'completed',
    progressPercent: 100,
  },
  {
    id: 'level-1',
    title: 'Level 1',
    status: 'current',
    progressPercent: 35,
  },
  ...Array.from({ length: 16 }, (_, index) => ({
    id: `level-${index + 2}`,
    title: `Level ${index + 2}`,
    status: 'locked' as const,
    progressPercent: 0,
  })),
]

export const phase1LevelRooms: readonly Phase1LevelRoom[] = [
  {
    id: 'ai-teacher',
    title: 'AI Teacher',
    summary: 'Course-grounded guidance for this level (scripted demo).',
    available: true,
  },
  {
    id: 'audio',
    title: 'Audio Lessons',
    summary: 'Short listening models for this level.',
    available: true,
  },
  {
    id: 'content',
    title: 'Content Room',
    summary: "Reading and phrase notes for today's outcomes.",
    available: true,
  },
  {
    id: 'conversation',
    title: 'Conversation Room',
    summary: 'Guided speaking turns (Friend prototype later).',
    available: true,
  },
  {
    id: 'activity',
    title: 'Activity Room',
    summary: 'Short practice tasks tied to the level objective.',
    available: true,
  },
  {
    id: 'assignment',
    title: 'Assignment',
    summary: 'Take-home speaking or writing task.',
    available: false,
  },
  {
    id: 'assessment',
    title: 'Assessment',
    summary: 'Level check before unlock.',
    available: false,
  },
  {
    id: 'unlock',
    title: 'Unlock Next Level',
    summary: 'Opens when assessment criteria are met.',
    available: false,
  },
]

export const phase1Notices: readonly Phase1Notice[] = [
  {
    id: 'n1',
    category: 'announcement',
    title: 'Welcome to the Phase 1 demo pathway',
    body: 'This notice board is synthetic. Production notices will come from trainers.',
    postedOn: '2026-08-04',
  },
  {
    id: 'n2',
    category: 'trainer',
    title: 'Trainer tip: practise your introduction aloud',
    body: 'Spend two minutes repeating "Hello, my name is ..." with clear pauses.',
    postedOn: '2026-08-03',
  },
  {
    id: 'n3',
    category: 'course',
    title: 'Course update: Level 1 audio pack',
    body: 'New listening lines for introductions will appear in Daily Activity.',
    postedOn: '2026-08-02',
  },
  {
    id: 'n4',
    category: 'holiday',
    title: 'Holiday notice',
    body: 'No live class on 3 August in this synthetic calendar.',
    postedOn: '2026-08-01',
  },
  {
    id: 'n5',
    category: 'motivation',
    title: 'Small steps win',
    body: 'Five focused minutes today beats waiting for a perfect study block.',
    postedOn: '2026-08-01',
  },
  {
    id: 'n6',
    category: 'event',
    title: 'Upcoming: speaking clinic',
    body: 'Optional group practice is listed here in the full product.',
    postedOn: '2026-07-30',
  },
]

export const phase1AiTopics: readonly Phase1AiTopic[] = [
  {
    id: 'welcome',
    labelEn: 'Welcome me',
    labelKn: 'Welcome (KN)',
    replyEn:
      "Welcome. You are on Level 1. Finish today's audio and 5-mark quiz to keep your streak.",
    replyKn:
      'Svagata. Neevu Level 1 ralliddiri. Indina audio mattu 5-anka quiz mugisi abhyasavannu munduvarisi.',
  },
  {
    id: 'structure',
    labelEn: 'Explain the course',
    labelKn: 'Course (KN)',
    replyEn:
      'The course runs Pre-Level 1, Pre-Level 2, then Levels 1-17. Each level has audio, content, conversation, activity, assignment and assessment rooms.',
    replyKn:
      'Course Pre-Level 1, Pre-Level 2 mattu Levels 1-17. Prati hantadalli audio, content, conversation, activity, assignment mattu assessment rooms ive.',
  },
  {
    id: 'level',
    labelEn: 'Explain my level',
    labelKn: 'My level (KN)',
    replyEn:
      'Level 1 focuses on clear self-introduction. You are about 35% through the level tasks in this demo.',
    replyKn:
      'Level 1 spashta parichayada mele kendrikirisuttade. Ee demode neevu sumaru 35% karyagalu pooraisiddiri.',
  },
  {
    id: 'attendance',
    labelEn: 'Attendance status',
    labelKn: 'Attendance (KN)',
    replyEn:
      'Today is marked present. Your synthetic monthly attendance is 75% (3 of 4 working days so far).',
    replyKn:
      'Indu hajaru endu gurutisalagide. Ee tingala synthetic attendance 75% (4 kelasa dinagalalli 3).',
  },
  {
    id: 'score',
    labelEn: 'Daily score',
    labelKn: 'Score (KN)',
    replyEn:
      'Daily Activity awards 1 mark per quiz question, 5 marks a day. This demo month shows 42 of 155 possible marks.',
    replyKn:
      'Prati quiz prashnege 1 anka, dinakke 5 anka. Ee demo tingalalli 155alli 42 ankagalu.',
  },
  {
    id: 'motivate',
    labelEn: 'Motivate me',
    labelKn: 'Motivation (KN)',
    replyEn:
      'Open Daily Activity, play the audio once, then answer all five questions. Small consistent work unlocks Level 1 rooms.',
    replyKn:
      'Daily Activity tereyiri, audio omme keli, aidu prashnegalige uttarisi. Sanna nirantara abhyasa Level 1 rooms tereyuttade.',
  },
]

export function moduleFromHash(hash: string): Phase1Module {
  const value = hash.replace(/^#/, '')
  if ((PHASE1_MODULES as readonly string[]).includes(value)) {
    return value as Phase1Module
  }
  return 'dashboard'
}
