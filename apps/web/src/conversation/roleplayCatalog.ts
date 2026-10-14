import type { MessageKey } from '@global-english/localization'

/**
 * Soft-launch synthetic Role-play catalog (mirrors API conversation.roleplay_catalog).
 * Pedagogy scripts are authored for Observe/Rehearse/Perform; review_status stays synthetic_unreviewed.
 */
export type RoleplayPedagogy = {
  readonly observeModel: readonly string[]
  readonly rehearseChunks: readonly string[]
  readonly performPrompt: string
}

export type RoleplayCatalogEntry = {
  readonly id: string
  readonly displayName: string
  readonly labelKey: MessageKey
  readonly rolesKey: MessageKey
  readonly personaKey: string
  readonly scenarioKey: string
  readonly pedagogy: RoleplayPedagogy
}

export const ROLEPLAY_CATALOG: readonly RoleplayCatalogEntry[] = [
  {
    id: 'clinic-reception',
    labelKey: 'roleplay.scene.clinic-reception',
    rolesKey: 'roleplay.roles.clinic-reception',
    displayName: 'Clinic reception',
    personaKey: 'clinic-reception-roleplay-prototype',
    scenarioKey: 'clinic-reception-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Receptionist: Good morning. How can I help you?',
        'You: Good morning. I have an appointment with Dr Rao at 10.',
        'Receptionist: May I have your name, please?',
        'You: Yes. My name is Priya Sharma.',
      ],
      rehearseChunks: [
        'Good morning. I have an appointment.',
        'My name is …',
        'It is at 10 o’clock.',
      ],
      performPrompt:
        'Greet the receptionist and say why you are here. Use your name and the appointment time.',
    },
  },
  {
    id: 'job-interview',
    labelKey: 'roleplay.scene.job-interview',
    rolesKey: 'roleplay.roles.job-interview',
    displayName: 'Job interview',
    personaKey: 'job-interview-roleplay-prototype',
    scenarioKey: 'job-interview-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Interviewer: Please sit down. Tell me about yourself.',
        'You: Good morning. My name is Arun. I work in customer support.',
        'Interviewer: What is one strength you have?',
        'You: I listen carefully and I stay calm with customers.',
      ],
      rehearseChunks: [
        'Good morning. My name is …',
        'I work in …',
        'One strength is …',
      ],
      performPrompt:
        'Introduce yourself in one or two sentences and answer a simple strengths question.',
    },
  },
  {
    id: 'bank-counter',
    labelKey: 'roleplay.scene.bank-counter',
    rolesKey: 'roleplay.roles.bank-counter',
    displayName: 'Bank counter',
    personaKey: 'bank-counter-roleplay-prototype',
    scenarioKey: 'bank-counter-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Clerk: Next, please. How can I help you?',
        'You: I would like to deposit some money.',
        'Clerk: May I have your account number?',
        'You: Yes. It is on this form.',
      ],
      rehearseChunks: [
        'I would like to …',
        'My account number is …',
        'Could you check this, please?',
      ],
      performPrompt:
        'Say why you are at the bank and confirm one simple detail politely.',
    },
  },
  {
    id: 'restaurant-order',
    labelKey: 'roleplay.scene.restaurant-order',
    rolesKey: 'roleplay.roles.restaurant-order',
    displayName: 'Restaurant order',
    personaKey: 'restaurant-order-roleplay-prototype',
    scenarioKey: 'restaurant-order-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Server: Good evening. Are you ready to order?',
        'You: Yes. I would like the vegetable thali, please.',
        'Server: Would you like water or juice?',
        'You: Water, please. Thank you.',
      ],
      rehearseChunks: [
        'I would like …, please.',
        'Could I have water, please?',
        'Thank you.',
      ],
      performPrompt: 'Greet the server and order one dish and one drink politely.',
    },
  },
  {
    id: 'hotel-checkin',
    labelKey: 'roleplay.scene.hotel-checkin',
    rolesKey: 'roleplay.roles.hotel-checkin',
    displayName: 'Hotel check-in',
    personaKey: 'hotel-checkin-roleplay-prototype',
    scenarioKey: 'hotel-checkin-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Receptionist: Welcome. Do you have a reservation?',
        'You: Yes. The name is Meera Joshi.',
        'Receptionist: A room for two nights?',
        'You: Yes. Could I have a quiet room, please?',
      ],
      rehearseChunks: [
        'I have a reservation.',
        'The name is …',
        'Could I have a quiet room, please?',
      ],
      performPrompt: 'Check in: give your name and one simple room request.',
    },
  },
  {
    id: 'phone-customer-support',
    labelKey: 'roleplay.scene.phone-customer-support',
    rolesKey: 'roleplay.roles.phone-customer-support',
    displayName: 'Phone customer support',
    personaKey: 'phone-customer-support-roleplay-prototype',
    scenarioKey: 'phone-customer-support-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Agent: Hello, support team. How can I help you?',
        'You: Hello. My internet is not working today.',
        'Agent: I am sorry. Can you tell me your customer number?',
        'You: Yes. It is 4821. Could you check it, please?',
      ],
      rehearseChunks: [
        'Hello. I have a problem with …',
        'My customer number is …',
        'Could you help me, please?',
      ],
      performPrompt:
        'Explain the problem in one sentence and give one detail the agent needs.',
    },
  },
  {
    id: 'grocery-shop',
    labelKey: 'roleplay.scene.grocery-shop',
    rolesKey: 'roleplay.roles.grocery-shop',
    displayName: 'Grocery shop',
    personaKey: 'grocery-shop-roleplay-prototype',
    scenarioKey: 'grocery-shop-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Shopkeeper: Yes, please?',
        'You: I need one kilo of rice, please.',
        'Shopkeeper: Anything else?',
        'You: Yes. Where is the milk?',
      ],
      rehearseChunks: [
        'I need …, please.',
        'How much is this?',
        'Where is the …?',
      ],
      performPrompt: 'Ask for one item and one price or location in the shop.',
    },
  },
  {
    id: 'school-parent-meeting',
    labelKey: 'roleplay.scene.school-parent-meeting',
    rolesKey: 'roleplay.roles.school-parent-meeting',
    displayName: 'School parent meeting',
    personaKey: 'school-parent-meeting-roleplay-prototype',
    scenarioKey: 'school-parent-meeting-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Teacher: Thank you for coming. How is Ananya at home?',
        'You: She is well. I wanted to ask about her reading.',
        'Teacher: She is improving. Please practise ten minutes a day.',
        'You: Thank you. I will do that.',
      ],
      rehearseChunks: [
        'I wanted to ask about …',
        'How is she doing in …?',
        'Thank you. I will …',
      ],
      performPrompt:
        'Greet the teacher and ask one clear question about your child’s learning.',
    },
  },
  {
    id: 'doctor-appointment',
    labelKey: 'roleplay.scene.doctor-appointment',
    rolesKey: 'roleplay.roles.doctor-appointment',
    displayName: 'Doctor appointment',
    personaKey: 'doctor-appointment-roleplay-prototype',
    scenarioKey: 'doctor-appointment-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Doctor: What is the problem today?',
        'You: I have a sore throat since yesterday.',
        'Doctor: Do you have a fever?',
        'You: A little. It started last night.',
      ],
      rehearseChunks: ['I have … since …', 'It started …', 'I feel …'],
      performPrompt:
        'Tell the doctor one symptom, when it started, and answer one yes/no question.',
    },
  },
  {
    id: 'airport-checkin',
    labelKey: 'roleplay.scene.airport-checkin',
    rolesKey: 'roleplay.roles.airport-checkin',
    displayName: 'Airport check-in',
    personaKey: 'airport-checkin-roleplay-prototype',
    scenarioKey: 'airport-checkin-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Agent: Good afternoon. Passport, please.',
        'You: Here it is. I am flying to Bengaluru.',
        'Agent: Window or aisle?',
        'You: Aisle, please. I have one small bag.',
      ],
      rehearseChunks: [
        'I am flying to …',
        'Window, please. / Aisle, please.',
        'I have one bag.',
      ],
      performPrompt: 'Check in for a flight: destination, seat preference, and bags.',
    },
  },
  {
    id: 'office-meeting',
    labelKey: 'roleplay.scene.office-meeting',
    rolesKey: 'roleplay.roles.office-meeting',
    displayName: 'Office meeting',
    personaKey: 'office-meeting-roleplay-prototype',
    scenarioKey: 'office-meeting-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Lead: Let’s start. Can you share the update?',
        'You: Yes. The report is ready. I sent it this morning.',
        'Lead: Any problem?',
        'You: One delay on page two. I can fix it today.',
      ],
      rehearseChunks: [
        'The update is …',
        'I can … today.',
        'Could we look at …?',
      ],
      performPrompt: 'Give a short work update and one next step.',
    },
  },
  {
    id: 'bus-train-ticket',
    labelKey: 'roleplay.scene.bus-train-ticket',
    rolesKey: 'roleplay.roles.bus-train-ticket',
    displayName: 'Bus or train ticket',
    personaKey: 'bus-train-ticket-roleplay-prototype',
    scenarioKey: 'bus-train-ticket-roleplay-scenario',
    pedagogy: {
      observeModel: [
        'Clerk: Where are you going?',
        'You: One ticket to Mysuru, please.',
        'Clerk: Morning or afternoon?',
        'You: The next bus, please. How much is it?',
      ],
      rehearseChunks: [
        'One ticket to …, please.',
        'The next bus/train, please.',
        'How much is it?',
      ],
      performPrompt: 'Buy a ticket: destination, time, and ask the price.',
    },
  },
] as const
