export type FoundationLessonGroup = 'foundations' | 'daily' | 'work'

export interface FoundationLesson {
  readonly id: string
  readonly number: number
  readonly group: FoundationLessonGroup
  readonly title: string
  readonly guidance: string
  readonly modelEn: string
  readonly minutes: number
}

/**
 * First 16 modules of the master-plan foundation pathway (§6.2).
 * Presentation fixtures only — not published curriculum.
 */
export const FOUNDATION_LESSONS: readonly FoundationLesson[] = [
  {
    id: 'greetings',
    number: 1,
    group: 'foundations',
    title: 'Greetings and introductions',
    guidance: 'Say your name and introduce yourself in one short line.',
    modelEn: 'Hello, my name is Ravi. I am pleased to meet you.',
    minutes: 5,
  },
  {
    id: 'personal-information',
    number: 2,
    group: 'foundations',
    title: 'Be, have and personal information',
    guidance: 'Say who you are and one fact about your family or city.',
    modelEn: 'I am Ravi. I have two sisters, and I live in Bengaluru.',
    minutes: 5,
  },
  {
    id: 'daily-routines',
    number: 3,
    group: 'foundations',
    title: 'Present simple and daily routines',
    guidance: 'Describe a usual morning in the present simple.',
    modelEn: 'I wake up at six. Then I drink tea and go to work.',
    minutes: 5,
  },
  {
    id: 'happening-now',
    number: 4,
    group: 'foundations',
    title: 'Present continuous and what is happening now',
    guidance: 'Say what is happening right now.',
    modelEn: 'I am sitting in a meeting, and I am listening carefully.',
    minutes: 5,
  },
  {
    id: 'yesterday',
    number: 5,
    group: 'foundations',
    title: 'Past simple and talking about yesterday',
    guidance: 'Tell one completed action from yesterday.',
    modelEn: 'Yesterday I finished the report and called my manager.',
    minutes: 5,
  },
  {
    id: 'future-plans',
    number: 6,
    group: 'foundations',
    title: 'Future plans using will and going to',
    guidance: 'State tomorrow’s plan clearly.',
    modelEn: 'I am going to visit the client tomorrow. I will take the morning train.',
    minutes: 5,
  },
  {
    id: 'questions',
    number: 7,
    group: 'foundations',
    title: 'Questions, negatives and short answers',
    guidance: 'Ask one question and give a short answer.',
    modelEn: 'Do you have a minute? Yes, I do. I am free now.',
    minutes: 5,
  },
  {
    id: 'articles',
    number: 8,
    group: 'foundations',
    title: 'Articles, prepositions and frequent transfer errors',
    guidance: 'Use a clear article and preposition in one workplace line.',
    modelEn: 'I work in a bank on M.G. Road, near the bus stand.',
    minutes: 5,
  },
  {
    id: 'family-home',
    number: 9,
    group: 'daily',
    title: 'Family and home',
    guidance: 'Say one line about your home or family.',
    modelEn: 'I live with my parents. Our home is close to the market.',
    minutes: 5,
  },
  {
    id: 'coffee-shop',
    number: 10,
    group: 'daily',
    title: 'Coffee-shop conversation and small talk',
    guidance: 'Order a drink and add a short friendly line.',
    modelEn: 'One filter coffee, please. It is busy today, isn’t it?',
    minutes: 5,
  },
  {
    id: 'shopping',
    number: 11,
    group: 'daily',
    title: 'Shopping and restaurant communication',
    guidance: 'Ask for the menu or name what you want.',
    modelEn: 'Could I see the menu, please? I will have the vegetable thali.',
    minutes: 5,
  },
  {
    id: 'travel',
    number: 12,
    group: 'daily',
    title: 'Travel, taxi, bus, railway and directions',
    guidance: 'Ask for a place or a ticket in one clear sentence.',
    modelEn: 'Please take me to the railway station. How long will it take?',
    minutes: 5,
  },
  {
    id: 'appointments',
    number: 13,
    group: 'daily',
    title: 'Appointments, clinic and service conversations',
    guidance: 'State an appointment or ask for help with a form.',
    modelEn: 'I have an appointment at ten. I need help with this form.',
    minutes: 5,
  },
  {
    id: 'telephone',
    number: 14,
    group: 'daily',
    title: 'Telephone calls and asking for help',
    guidance: 'Give your name on a call and ask for help.',
    modelEn: 'Hello, this is Ravi speaking. Could you help me with my booking?',
    minutes: 5,
  },
  {
    id: 'interview',
    number: 15,
    group: 'work',
    title: 'Tell me about yourself for an interview',
    guidance: 'Give two short lines about your work.',
    modelEn: 'I am an accounts assistant. I like clear work and I learn quickly.',
    minutes: 5,
  },
  {
    id: 'office',
    number: 16,
    group: 'work',
    title: 'Office introductions and daily stand-up',
    guidance: 'Say your name and today’s task in the office.',
    modelEn: 'Good morning. I am Ravi from accounts. Today I am updating the invoice list.',
    minutes: 5,
  },
]

export const FOUNDATION_LESSON_GROUPS: readonly FoundationLessonGroup[] = [
  'foundations',
  'daily',
  'work',
]

export function lessonsInGroup(
  group: FoundationLessonGroup,
): readonly FoundationLesson[] {
  return FOUNDATION_LESSONS.filter((lesson) => lesson.group === group)
}

export function foundationLessonById(
  id: string | null | undefined,
): FoundationLesson | null {
  if (id === null || id === undefined || id === '') {
    return null
  }

  return FOUNDATION_LESSONS.find((lesson) => lesson.id === id) ?? null
}

export function lessonIdFromHash(hash: string | undefined): string | null {
  const value = (hash ?? '').replace(/^#/u, '')
  const match = /^(?:learn|practice)\/([a-z0-9-]+)$/u.exec(value)
  if (match === null) {
    return null
  }

  return foundationLessonById(match[1])?.id ?? null
}

export function destinationFromLessonHash(
  hash: string | undefined,
): 'learn' | 'practice' | null {
  const value = (hash ?? '').replace(/^#/u, '')
  if (value.startsWith('learn/') && lessonIdFromHash(hash) !== null) {
    return 'learn'
  }
  if (value.startsWith('practice/') && lessonIdFromHash(hash) !== null) {
    return 'practice'
  }
  return null
}
