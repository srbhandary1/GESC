/**
 * Restored learner curriculum for the Phase 1 level path.
 *
 * Level 1 Can: `9818fe2` localDemo sessionDetail + LPL-01 blueprint.
 * Levels 2–17: `c4c810f` AuthoredFoundationLessons.php (APP-0065/0066/0067).
 * Pre-Level 1 Be and Pre-Level 2 This/That: newly authored to close the
 * archive gap (same Phase 1 foundation pattern; not greetings/coffee).
 */
export type CurriculumAvailability = 'authored' | 'missing'

export interface CurriculumCheckItem {
  readonly id: string
  readonly prompt: string
  readonly options: readonly string[]
  readonly correctIndex: number
}

export interface LevelCurriculum {
  readonly id: string
  readonly kind: 'pre' | 'level'
  readonly number: number
  readonly availability: CurriculumAvailability
  readonly source: string | null
  readonly conceptKey: string | null
  readonly title: string
  readonly objective: string | null
  readonly targetPatterns: readonly string[]
  readonly modelLine: string | null
  readonly fallbackTurns: readonly string[]
  readonly scenario: string | null
  readonly kannadaSummary: string | null
  readonly applyTask: string | null
  readonly questions: readonly CurriculumCheckItem[]
}

export const MISSING_LEVEL_NOTICE =
  'No authored listen-learn session is in the restored LessonPractice archive for this level. Levels 1–17 use the Can / APP-0065 curriculum. This gap is not filled with greetings or coffee-shop fixtures.'

export const LEVEL_CURRICULUM: readonly LevelCurriculum[] = [
  {
    id: 'pre-1',
    kind: 'pre',
    number: 1,
    availability: 'authored',
    source: 'AuthoredPreLevels.php be — newly authored (absent from CC archive)',
    conceptKey: 'be',
    title: 'Be',
    objective:
      'By the end of this lesson, the learner can use I am, You are and This is to name a person or thing in a short classroom or workplace exchange.',
    targetPatterns: [
      'I am + noun / adjective.',
      'You are + noun / adjective.',
      'This is + noun.',
      'Is this + noun?',
    ],
    modelLine: 'I am a student. This is my book.',
    fallbackTurns: [
      'I am a student. This is my book.',
      'Is this your pen?',
      'Yes, it is.',
    ],
    scenario:
      'In a classroom or office, name yourself and one object on the table.',
    kannadaSummary:
      'ಈ ಪಾಠದಲ್ಲಿ I am, You are ಮತ್ತು This is ಬಳಸಿ ವ್ಯಕ್ತಿ ಅಥವಾ ವಸ್ತುವನ್ನು ಹೆಸರಿಸಿ.',
    applyTask:
      'Record a 20–30 second exchange: say who you are, name one object with This is, and ask one Is this…? question.',
    questions: [
      {
        id: 'be-01',
        prompt: 'Choose the correct identity sentence.',
        options: ['I am a teacher.', 'I are a teacher.', 'I be a teacher.'],
        correctIndex: 0,
      },
      {
        id: 'be-02',
        prompt: 'Choose the correct naming sentence.',
        options: ['This is my desk.', 'This are my desk.', 'This my desk.'],
        correctIndex: 0,
      },
      {
        id: 'be-03',
        prompt: 'Choose the question for a nearby object.',
        options: ['Is this your bag?', 'This is your bag?', 'Are this your bag?'],
        correctIndex: 0,
      },
    ],
  },
  {
    id: 'pre-2',
    kind: 'pre',
    number: 2,
    availability: 'authored',
    source: 'AuthoredPreLevels.php this_that — newly authored (absent from CC archive)',
    conceptKey: 'this_that',
    title: 'This / That',
    objective:
      'By the end of this lesson, the learner can use this and that to point to a near or far object at work or in class and ask which one it is.',
    targetPatterns: [
      'This is + noun.',
      'That is + noun.',
      'Is this / that + noun?',
      'These are / Those are + noun.',
    ],
    modelLine: 'This is my desk. That is your chair.',
    fallbackTurns: [
      'This is my desk. That is your chair.',
      'Is that the form?',
      'No, that is the handbook.',
    ],
    scenario:
      'At a shared desk, point to a near object and a far object and check which one a colleague needs.',
    kannadaSummary:
      'ಈ ಪಾಠದಲ್ಲಿ this (ಹತ್ತಿರ) ಮತ್ತು that (ದೂರ) ಬಳಸಿ ವಸ್ತುಗಳನ್ನು ತೋರಿಸಿ.',
    applyTask:
      'Record a 20–30 second exchange: name one near object with this, one far object with that, and ask one Is that…? question.',
    questions: [
      {
        id: 'this_that-01',
        prompt: 'Choose the sentence for a nearby object.',
        options: [
          'This is my notebook.',
          'That is my notebook here.',
          'These is my notebook.',
        ],
        correctIndex: 0,
      },
      {
        id: 'this_that-02',
        prompt: 'Choose the sentence for a farther object.',
        options: [
          'That is the door.',
          'This is the door over there.',
          'Those is the door.',
        ],
        correctIndex: 0,
      },
      {
        id: 'this_that-03',
        prompt: 'Choose the question about a farther object.',
        options: [
          'Is that your file?',
          'Are that your file?',
          'This that your file?',
        ],
        correctIndex: 0,
      },
    ],
  },
  {
    id: 'level-1',
    kind: 'level',
    number: 1,
    availability: 'authored',
    source: '9818fe2 localDemo.ts + LPL-01 level1-authored-blueprint-draft.json',
    conceptKey: 'can',
    title: 'Can',
    objective:
      'By the end of this lesson, the learner can use can and cannot to state one ability and ask for or give permission in a short everyday exchange.',
    targetPatterns: [
      'I can + base verb.',
      'I cannot + base verb.',
      'Can I + base verb?',
      'Yes, you can. / No, you cannot.',
    ],
    modelLine: 'I can speak English. Can I help you?',
    fallbackTurns: [
      'I can speak English. Can I help you?',
      'Can I use your pen?',
      'Yes, you can.',
    ],
    scenario: 'Ask for permission at a community centre.',
    kannadaSummary: 'ಈ ಪಾಠದಲ್ಲಿ can ಬಳಕೆಯನ್ನು ಕಲಿಯಿರಿ.',
    applyTask:
      'Record a 20–30 second exchange: state one ability, ask permission to do one everyday action, and answer one permission question.',
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
    ],
  },
  {
      id: 'level-2',
      kind: 'level',
      number: 2,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "should",
      title: "Should",
      objective: "By the end of this lesson, the learner can use should and should not to give one everyday suggestion and ask for simple advice.",
      targetPatterns: ["I should + base verb.", "You should not + base verb.", "Should I + base verb?", "Yes, you should. / No, you should not."],
      modelLine: "You should drink some water.",
      fallbackTurns: ["You should drink some water.", "Should I rest now?", "Yes, you should."],
      scenario: "A colleague looks tired after work. Suggest one healthy action and ask one Should I…? question.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ should ಮತ್ತು should not ಬಳಸಿ ಸರಳ ಸಲಹೆ ಕೊಡುವುದು ಮತ್ತು Should I…? ಎಂದು ಕೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. should ನಂತರ ಮೂಲ ಕ್ರಿಯಾಪದ ಬರುತ್ತದೆ.",
      applyTask: "Record a 20–30 second exchange: give one should suggestion, ask one Should I…? question, and answer it.",
      questions: [
      {
        id: "should-01",
        prompt: "Choose the correct suggestion.",
        options: ["You should rest tonight.", "You should to rest tonight.", "You should resting tonight."],
        correctIndex: 0,
      },
      {
        id: "should-02",
        prompt: "Choose the advice question.",
        options: ["Should I call her now?", "I should call her now?", "Should I calling her now?"],
        correctIndex: 0,
      },
      {
        id: "should-03",
        prompt: "Choose a suitable short reply to Should I wait?",
        options: ["No, you should not.", "No, you should not to.", "No, you should not waiting."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-3',
      kind: 'level',
      number: 3,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "do_does",
      title: "Do / Does",
      objective: "By the end of this lesson, the learner can use do and does to ask and answer simple present questions about everyday habits.",
      targetPatterns: ["Do you + base verb?", "Does he/she + base verb?", "Yes, I do. / No, I do not.", "Yes, she does. / No, he does not."],
      modelLine: "Do you drink tea in the morning?",
      fallbackTurns: ["Do you drink tea in the morning?", "Yes, I do.", "Does she work nearby?"],
      scenario: "You meet a neighbour. Ask about one daily habit and answer one Do you…? question.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ do ಮತ್ತು does ಬಳಸಿ ದಿನನಿತ್ಯದ ಅಭ್ಯಾಸಗಳ ಬಗ್ಗೆ ಪ್ರಶ್ನೆ ಕೇಳಿ ಉತ್ತರಿಸುವುದನ್ನು ಕಲಿಯಿರಿ. you ಜೊತೆ Do; he/she ಜೊತೆ Does.",
      applyTask: "Record a 20–30 second exchange: ask one Do you…? question, one Does he/she…? question, and give one short do/does answer.",
      questions: [
      {
        id: "do_does-01",
        prompt: "Choose the correct question.",
        options: ["Do you live here?", "Does you live here?", "Do you lives here?"],
        correctIndex: 0,
      },
      {
        id: "do_does-02",
        prompt: "Choose the correct question about a colleague.",
        options: ["Does she start at nine?", "Do she start at nine?", "Does she starts at nine?"],
        correctIndex: 0,
      },
      {
        id: "do_does-03",
        prompt: "Choose a suitable short reply to Do you cook at home?",
        options: ["Yes, I do.", "Yes, I does.", "Yes, I do cooks."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-4',
      kind: 'level',
      number: 4,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "is_are_ing",
      title: "Is / Are + -ing",
      objective: "By the end of this lesson, the learner can use am/is/are + -ing to say what someone is doing now and ask one present-continuous question.",
      targetPatterns: ["I am + -ing.", "She/He is + -ing.", "They are + -ing.", "Are you + -ing?"],
      modelLine: "I am waiting for a call.",
      fallbackTurns: ["I am waiting for a call.", "Are you writing the report?", "Yes, I am."],
      scenario: "You are in a busy office. Say what you are doing now and ask a colleague what they are doing.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ಈಗ ನಡೆಯುತ್ತಿರುವ ಕೆಲಸವನ್ನು am/is/are + -ing ರೂಪದಲ್ಲಿ ಹೇಳುವುದು ಮತ್ತು Are you…? ಎಂದು ಕೇಳುವುದನ್ನು ಕಲಿಯಿರಿ.",
      applyTask: "Record a 20–30 second exchange: say what you are doing now, ask one Are you…-ing? question, and answer it.",
      questions: [
      {
        id: "is_are_ing-01",
        prompt: "Choose the sentence about now.",
        options: ["She is talking to a customer.", "She talks to a customer nowing.", "She is talk to a customer."],
        correctIndex: 0,
      },
      {
        id: "is_are_ing-02",
        prompt: "Choose the correct question.",
        options: ["Are you waiting here?", "Do you waiting here?", "Is you waiting here?"],
        correctIndex: 0,
      },
      {
        id: "is_are_ing-03",
        prompt: "Choose a suitable short reply to Are you leaving?",
        options: ["No, I am not.", "No, I are not.", "No, I not leaving am."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-5',
      kind: 'level',
      number: 5,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "did",
      title: "Did",
      objective: "By the end of this lesson, the learner can use did to ask and answer simple past questions about yesterday’s actions.",
      targetPatterns: ["Did you + base verb?", "I + past verb yesterday.", "Yes, I did. / No, I did not.", "What did you + base verb?"],
      modelLine: "Did you go to the market?",
      fallbackTurns: ["Did you go to the market?", "Yes, I did.", "I cooked dinner."],
      scenario: "A friend asks about yesterday. Talk about one past action and ask one Did you…? question.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ನಿನ್ನೆಯ ಕೆಲಸದ ಬಗ್ಗೆ Did you…? ಎಂದು ಕೇಳಿ, did / did not ಎಂದು ಉತ್ತರಿಸಿ, ಒಂದು ಭೂತಕಾಲದ ವಾಕ್ಯ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. did ನಂತರ ಮೂಲ ಕ್ರಿಯಾಪದ.",
      applyTask: "Record a 20–30 second exchange: ask one Did you…? question, give a short did answer, and state one past action.",
      questions: [
      {
        id: "did-01",
        prompt: "Choose the correct past question.",
        options: ["Did you finish the work?", "Did you finished the work?", "Do you finished the work?"],
        correctIndex: 0,
      },
      {
        id: "did-02",
        prompt: "Choose the correct statement about yesterday.",
        options: ["I called my sister yesterday.", "I did called my sister yesterday.", "I call my sister yesterday."],
        correctIndex: 0,
      },
      {
        id: "did-03",
        prompt: "Choose a suitable short reply to Did you rest?",
        options: ["No, I did not.", "No, I did not rested.", "No, I not did."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-6',
      kind: 'level',
      number: 6,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "was_were_ing",
      title: "Was / Were + -ing",
      objective: "By the end of this lesson, the learner can use was/were + -ing to say what was happening at a time in the past.",
      targetPatterns: ["I was + -ing.", "They were + -ing.", "Were you + -ing?", "I was not + -ing."],
      modelLine: "I was cooking at eight.",
      fallbackTurns: ["I was cooking at eight.", "Were you watching TV?", "No, I was not."],
      scenario: "A power cut happened at 8 p.m. Say what you were doing then and ask a partner what they were doing.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ಹಿಂದಿನ ಒಂದು ಸಮಯದಲ್ಲಿ ನಡೆಯುತ್ತಿದ್ದ ಕೆಲಸವನ್ನು was/were + -ing ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. I/he/she ಜೊತೆ was; you/we/they ಜೊತೆ were.",
      applyTask: "Record a 20–30 second exchange: say what you were doing at a past time, ask one Were you…-ing? question, and answer it.",
      questions: [
      {
        id: "was_were_ing-01",
        prompt: "Choose the sentence about a past moment.",
        options: ["She was waiting at the bus stop.", "She were waiting at the bus stop.", "She was wait at the bus stop."],
        correctIndex: 0,
      },
      {
        id: "was_were_ing-02",
        prompt: "Choose the correct question.",
        options: ["Were you working then?", "Was you working then?", "Were you work then?"],
        correctIndex: 0,
      },
      {
        id: "was_were_ing-03",
        prompt: "Choose a suitable short reply to Were you sleeping?",
        options: ["Yes, I was.", "Yes, I were.", "Yes, I was sleepinging."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-7',
      kind: 'level',
      number: 7,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "have_has",
      title: "Have / Has",
      objective: "By the end of this lesson, the learner can use have/has + past participle to talk about life experience and recent finished actions with a present result.",
      targetPatterns: ["I have + past participle.", "She has + past participle.", "Have you + past participle?", "Yes, I have. / No, I have not."],
      modelLine: "I have visited Mysuru.",
      fallbackTurns: ["I have visited Mysuru.", "Have you tried this tea?", "Yes, I have."],
      scenario: "You are talking about travel and work experience. Share one experience and ask Have you ever…?",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ಅನುಭವ ಅಥವಾ ಈಗಲೂ ಸಂಬಂಧವಿರುವ ಕೆಲಸವನ್ನು have/has + past participle ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. I/you/we/they ಜೊತೆ have; he/she ಜೊತೆ has.",
      applyTask: "Record a 20–30 second exchange: state one I have… experience, ask Have you…?, and give a short have/have not answer.",
      questions: [
      {
        id: "have_has-01",
        prompt: "Choose the experience sentence.",
        options: ["I have finished the form.", "I have finish the form.", "I has finished the form."],
        correctIndex: 0,
      },
      {
        id: "have_has-02",
        prompt: "Choose the correct question.",
        options: ["Have you seen this message?", "Has you seen this message?", "Have you saw this message?"],
        correctIndex: 0,
      },
      {
        id: "have_has-03",
        prompt: "Choose a suitable short reply to Have you eaten?",
        options: ["No, I have not.", "No, I has not.", "No, I have not ate."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-8',
      kind: 'level',
      number: 8,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "have_been_has_been",
      title: "Have been / Has been",
      objective: "By the end of this lesson, the learner can use have been / has been + -ing to say an action started in the past and is still continuing.",
      targetPatterns: ["I have been + -ing.", "She has been + -ing.", "Have you been + -ing?", "How long have you been + -ing?"],
      modelLine: "I have been practising for two weeks.",
      fallbackTurns: ["I have been practising for two weeks.", "How long have you been waiting?", "She has been working since morning."],
      scenario: "A trainer asks how long you have been practising English. Answer with have been + -ing and ask one How long…? question.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ಹಿಂದೆ ಶುರುವಾಗಿ ಈಗಲೂ ನಡೆಯುತ್ತಿರುವ ಕೆಲಸವನ್ನು have been / has been + -ing ರೂಪದಲ್ಲಿ ಹೇಳಿ, How long…? ಎಂದು ಕೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. for = ಅವಧಿ; since = ಆರಂಭದ ಸಮಯ.",
      applyTask: "Record a 20–30 second exchange: say how long you have been doing one activity, ask How long have you been…?, and answer.",
      questions: [
      {
        id: "have_been_has_been-01",
        prompt: "Choose the continuing-action sentence.",
        options: ["I have been studying all morning.", "I have studying all morning.", "I has been studying all morning."],
        correctIndex: 0,
      },
      {
        id: "have_been_has_been-02",
        prompt: "Choose the correct question.",
        options: ["How long have you been living here?", "How long you have been live here?", "How long has you been living here?"],
        correctIndex: 0,
      },
      {
        id: "have_been_has_been-03",
        prompt: "Choose the clearer time phrase.",
        options: ["I have been waiting since 9 a.m.", "I have been waiting since two hours.", "I have been waiting from I arrived only."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-9',
      kind: 'level',
      number: 9,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "had",
      title: "Had",
      objective: "By the end of this lesson, the learner can use had + past participle to say one action was already finished before another past time.",
      targetPatterns: ["I had + past participle before…", "She had already + past participle.", "Had you + past participle?", "Yes, I had. / No, I had not."],
      modelLine: "I had sent the file before lunch.",
      fallbackTurns: ["I had sent the file before lunch.", "Had you called them?", "Yes, I had."],
      scenario: "You arrived at a meeting late. Explain what you had already done before you arrived.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ಇನ್ನೊಂದು ಹಿಂದಿನ ಸಮಯಕ್ಕಿಂತ ಮೊದಲೇ ಮುಗಿದ ಕೆಲಸವನ್ನು had + past participle ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ.",
      applyTask: "Record a 20–30 second exchange: say one thing you had already done before another past time, ask Had you…?, and answer.",
      questions: [
      {
        id: "had-01",
        prompt: "Choose the sentence about an earlier past action.",
        options: ["She had left before I arrived.", "She had leave before I arrived.", "She has left before I arrived yesterday only."],
        correctIndex: 0,
      },
      {
        id: "had-02",
        prompt: "Choose the correct question.",
        options: ["Had you finished before 6?", "Had you finish before 6?", "Did you had finished before 6?"],
        correctIndex: 0,
      },
      {
        id: "had-03",
        prompt: "Choose a suitable short reply to Had you eaten?",
        options: ["No, I had not.", "No, I had not ate.", "No, I have not had."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-10',
      kind: 'level',
      number: 10,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "will",
      title: "Will",
      objective: "By the end of this lesson, the learner can use will to make one future plan or promise and ask a simple Will you…? request.",
      targetPatterns: ["I will + base verb.", "I will not + base verb.", "Will you + base verb?", "Yes, I will. / No, I will not."],
      modelLine: "I will join the class tomorrow.",
      fallbackTurns: ["I will join the class tomorrow.", "Will you send the notes?", "Yes, I will."],
      scenario: "You are planning tomorrow with a classmate. Make one promise with will and ask one Will you…? request.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ನಾಳೆಯ ಯೋಜನೆ ಅಥವಾ ವಾಗ್ದಾನವನ್ನು I will + ಮೂಲ ಕ್ರಿಯಾಪದ ರೂಪದಲ್ಲಿ ಹೇಳಿ, Will you…? ಎಂದು ವಿನಂತಿಸುವುದನ್ನು ಕಲಿಯಿರಿ.",
      applyTask: "Record a 20–30 second exchange: make one I will… plan, ask Will you…?, and answer it.",
      questions: [
      {
        id: "will-01",
        prompt: "Choose the future sentence.",
        options: ["I will call you tonight.", "I will to call you tonight.", "I will calling you tonight."],
        correctIndex: 0,
      },
      {
        id: "will-02",
        prompt: "Choose the polite request.",
        options: ["Will you wait a minute?", "Will you waiting a minute?", "You will wait a minute?"],
        correctIndex: 0,
      },
      {
        id: "will-03",
        prompt: "Choose a suitable short reply to Will you help?",
        options: ["Yes, I will.", "Yes, I will to.", "Yes, I will helping."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-11',
      kind: 'level',
      number: 11,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "gonna_wanna",
      title: "Gonna / Wanna",
      objective: "By the end of this lesson, the learner can recognise gonna and wanna in informal speech and reply with a clear going to or want to sentence.",
      targetPatterns: ["I am going to + base verb.", "I want to + base verb.", "What are you going to + base verb?", "I do not want to + base verb."],
      modelLine: "I am going to practise tonight.",
      fallbackTurns: ["I am going to practise tonight.", "I want to drink water.", "What are you going to do now?"],
      scenario: "A friend uses informal speech: I’m gonna rest. Reply in clear English with going to or want to.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ಮಾತಿನ gonna/wanna ಅನ್ನು ಗುರುತಿಸಿ, ತರಗತಿಯಲ್ಲಿ going to ಮತ್ತು want to ಎಂದು ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ.",
      applyTask: "Record a 20–30 second exchange: say one going-to plan, one want-to wish, and ask What are you going to…?",
      questions: [
      {
        id: "gonna_wanna-01",
        prompt: "Choose the clearer classroom sentence.",
        options: ["I am going to start now.", "I gonna start now in this class.", "I going start now."],
        correctIndex: 0,
      },
      {
        id: "gonna_wanna-02",
        prompt: "Choose the correct want sentence.",
        options: ["I want to sit here.", "I wanna to sit here.", "I want sitting here."],
        correctIndex: 0,
      },
      {
        id: "gonna_wanna-03",
        prompt: "Choose the question about a plan.",
        options: ["What are you going to cook?", "What you gonna cooking?", "What are you going cook?"],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-12',
      kind: 'level',
      number: 12,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "would",
      title: "Would",
      objective: "By the end of this lesson, the learner can use would like and Would you…? to make polite offers, requests and preferences.",
      targetPatterns: ["I would like + noun / to + base verb.", "Would you like…?", "Would you + base verb, please?", "I would not like…"],
      modelLine: "I would like black tea.",
      fallbackTurns: ["I would like black tea.", "Would you like some water?", "Would you pass the sugar, please?"],
      scenario: "You are ordering tea with a colleague. Make one polite preference and one Would you like…? offer.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ವಿನಯದಿಂದ ಇಷ್ಟ, ಕೊಡುಗೆ ಮತ್ತು ವಿನಂತಿಯನ್ನು I would like ಮತ್ತು Would you…? ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ.",
      applyTask: "Record a 20–30 second exchange: say I would like…, offer Would you like…?, and make one Would you…, please? request.",
      questions: [
      {
        id: "would-01",
        prompt: "Choose the polite preference.",
        options: ["I would like a window seat.", "I would liking a window seat.", "I would like to a window seat."],
        correctIndex: 0,
      },
      {
        id: "would-02",
        prompt: "Choose the polite offer.",
        options: ["Would you like some help?", "Would you liking some help?", "You would like some help?"],
        correctIndex: 0,
      },
      {
        id: "would-03",
        prompt: "Choose a suitable short reply to Would you like tea?",
        options: ["Yes, I would.", "Yes, I would to.", "Yes, I would likeing."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-13',
      kind: 'level',
      number: 13,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "may",
      title: "May",
      objective: "By the end of this lesson, the learner can use may for polite permission and for a careful possibility.",
      targetPatterns: ["May I + base verb?", "Yes, you may. / No, you may not.", "It may + base verb.", "I may not + base verb."],
      modelLine: "May I sit here?",
      fallbackTurns: ["May I sit here?", "Yes, you may.", "It may rain later."],
      scenario: "You are in a quiet office. Ask polite permission with May I…? and say one possible plan with It may…",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ವಿನಯದ ಅನುಮತಿಗೆ May I…? ಮತ್ತು ಸಾಧ್ಯತೆಗೆ may + ಮೂಲ ಕ್ರಿಯಾಪದ ಬಳಸುವುದನ್ನು ಕಲಿಯಿರಿ.",
      applyTask: "Record a 20–30 second exchange: ask May I…?, answer it, and state one It may… possibility.",
      questions: [
      {
        id: "may-01",
        prompt: "Choose the polite permission question.",
        options: ["May I open the window?", "May I opening the window?", "May I to open the window?"],
        correctIndex: 0,
      },
      {
        id: "may-02",
        prompt: "Choose the possibility sentence.",
        options: ["She may come after lunch.", "She may comes after lunch.", "She may to come after lunch."],
        correctIndex: 0,
      },
      {
        id: "may-03",
        prompt: "Choose a suitable short reply to May I start?",
        options: ["Yes, you may.", "Yes, you may to.", "Yes, you may starting."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-14',
      kind: 'level',
      number: 14,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "if",
      title: "If",
      objective: "By the end of this lesson, the learner can use a simple if clause to talk about a real everyday condition and its result.",
      targetPatterns: ["If + present, will + base verb.", "If I + present, I will + base verb.", "What will you do if…?", "If it rains, we will + base verb."],
      modelLine: "If it rains, we will stay inside.",
      fallbackTurns: ["If it rains, we will stay inside.", "If I finish early, I will call you.", "What will you do if the bus is late?"],
      scenario: "You are planning a weekend outing. Use one if sentence about weather or time and ask What will you do if…?",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ನಿಜವಾದ ಷರತ್ತು ಮತ್ತು ಫಲಿತಾಂಶವನ್ನು If + ವರ್ತಮಾನ, will + ಮೂಲ ಕ್ರಿಯಾಪದ ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. if ವಾಕ್ಯದಲ್ಲಿ will ಬೇಡ.",
      applyTask: "Record a 20–30 second exchange: say one If…, I will… sentence and ask What will you do if…?",
      questions: [
      {
        id: "if-01",
        prompt: "Choose the correct real-condition sentence.",
        options: ["If I have time, I will join.", "If I will have time, I will join.", "If I have time, I joining."],
        correctIndex: 0,
      },
      {
        id: "if-02",
        prompt: "Choose the question.",
        options: ["What will you do if she is busy?", "What will you do if she will be busy?", "What you do if she is busy?"],
        correctIndex: 0,
      },
      {
        id: "if-03",
        prompt: "Choose the clearer result clause.",
        options: ["If the shop is closed, we will wait.", "If the shop is closed, we wait will.", "If the shop closed, we will waiting."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-15',
      kind: 'level',
      number: 15,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "must",
      title: "Must",
      objective: "By the end of this lesson, the learner can use must and must not for strong everyday rules and obligations.",
      targetPatterns: ["I must + base verb.", "You must not + base verb.", "Must I + base verb?", "Yes, you must. / No, you must not."],
      modelLine: "You must wear your badge.",
      fallbackTurns: ["You must wear your badge.", "You must not shout here.", "Must I sign this form?"],
      scenario: "You are explaining a workplace or classroom rule. State one must obligation and one must not prohibition.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ಕಡ್ಡಾಯ ನಿಯಮಕ್ಕೆ must ಮತ್ತು ನಿಷೇಧಕ್ಕೆ must not ಬಳಸುವುದನ್ನು ಕಲಿಯಿರಿ. must ನಂತರ ಮೂಲ ಕ್ರಿಯಾಪದ.",
      applyTask: "Record a 20–30 second exchange: give one must rule, one must not rule, and ask Must I…?",
      questions: [
      {
        id: "must-01",
        prompt: "Choose the obligation sentence.",
        options: ["You must show your ticket.", "You must to show your ticket.", "You must showing your ticket."],
        correctIndex: 0,
      },
      {
        id: "must-02",
        prompt: "Choose the prohibition.",
        options: ["You must not park here.", "You must not to park here.", "You must not parking here."],
        correctIndex: 0,
      },
      {
        id: "must-03",
        prompt: "Choose a suitable short reply to Must I wait?",
        options: ["Yes, you must.", "Yes, you must to.", "Yes, you must waiting."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-16',
      kind: 'level',
      number: 16,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "should_have",
      title: "Should have",
      objective: "By the end of this lesson, the learner can use should have + past participle to talk about a better past choice without blaming harshly.",
      targetPatterns: ["I should have + past participle.", "You should not have + past participle.", "Should I have + past participle?", "Yes, you should have. / No, you should not have."],
      modelLine: "I should have left earlier.",
      fallbackTurns: ["I should have left earlier.", "You should have told me.", "Should I have called first?"],
      scenario: "A meeting started without the notes. Talk about one better past action with should have.",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ಹಿಂದಿನ ಉತ್ತಮ ಆಯ್ಕೆಯನ್ನು should have + past participle ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. ದೂಷಣೆಯಿಲ್ಲದೆ ನಿಧಾನ ಸಲಹೆ.",
      applyTask: "Record a 20–30 second exchange: say I should have…, give one You should have…, and ask Should I have…?",
      questions: [
      {
        id: "should_have-01",
        prompt: "Choose the past-advice sentence.",
        options: ["I should have checked the time.", "I should have check the time.", "I should had checked the time."],
        correctIndex: 0,
      },
      {
        id: "should_have-02",
        prompt: "Choose the gentler correction.",
        options: ["You should not have waited so long.", "You should not have wait so long.", "You should not had waited so long."],
        correctIndex: 0,
      },
      {
        id: "should_have-03",
        prompt: "Choose a suitable short reply to Should I have asked?",
        options: ["Yes, you should have.", "Yes, you should have to.", "Yes, you should had."],
        correctIndex: 0,
      }
      ],
    },
  {
      id: 'level-17',
      kind: 'level',
      number: 17,
      availability: 'authored',
      source: "c4c810f AuthoredFoundationLessons.php APP-0065",
      conceptKey: "used_to",
      title: "Used to",
      objective: "By the end of this lesson, the learner can use used to + base verb to talk about a past habit that is not true now.",
      targetPatterns: ["I used to + base verb.", "I did not use to + base verb.", "Did you use to + base verb?", "Yes, I did. / No, I did not."],
      modelLine: "I used to walk to school.",
      fallbackTurns: ["I used to walk to school.", "Did you use to play cricket?", "I do not walk now. I take the bus."],
      scenario: "You are talking about school days versus now. Share one old habit with used to and ask Did you use to…?",
      kannadaSummary: "ಈ ಪಾಠದಲ್ಲಿ ಹಿಂದೆ ಇದ್ದ ಆದರೆ ಈಗ ಇಲ್ಲದ ಅಭ್ಯಾಸವನ್ನು used to + ಮೂಲ ಕ್ರಿಯಾಪದ ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. ಪ್ರಶ್ನೆ: Did you use to…?",
      applyTask: "Record a 20–30 second exchange: say one I used to… habit, ask Did you use to…?, and contrast with now.",
      questions: [
      {
        id: "used_to-01",
        prompt: "Choose the past-habit sentence.",
        options: ["I used to drink more tea.", "I used to drinking more tea.", "I use to drink more tea then."],
        correctIndex: 0,
      },
      {
        id: "used_to-02",
        prompt: "Choose the question.",
        options: ["Did you use to live here?", "Did you used to live here?", "Did you use to lived here?"],
        correctIndex: 0,
      },
      {
        id: "used_to-03",
        prompt: "Choose the clearer contrast with now.",
        options: ["I used to cook every day. Now I cook on Sundays.", "I used to cook every day and I still used to.", "I used cook every day now."],
        correctIndex: 0,
      }
      ],
    },
]

export function curriculumByLevelId(
  levelId: string | null | undefined,
): LevelCurriculum | null {
  if (levelId === undefined || levelId === null || levelId === '') {
    return null
  }
  return LEVEL_CURRICULUM.find((entry) => entry.id === levelId) ?? null
}

export function authoredLevelCount(): number {
  return LEVEL_CURRICULUM.filter((entry) => entry.availability === 'authored').length
}
