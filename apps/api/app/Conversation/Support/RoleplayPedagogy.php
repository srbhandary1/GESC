<?php

declare(strict_types=1);

namespace App\Conversation\Support;

/**
 * Authored Observe / Rehearse / Perform scripts for synthetic Role-play.
 * Review status remains synthetic_unreviewed until specialist curriculum review.
 */
final class RoleplayPedagogy
{
    /**
     * @return array{
     *   observe_model: list<string>,
     *   rehearse_chunks: list<string>,
     *   perform_prompt: string
     * }|null
     */
    public static function for(string $catalogId): ?array
    {
        return self::scripts()[$catalogId] ?? null;
    }

    /**
     * @return array<string, array{
     *   observe_model: list<string>,
     *   rehearse_chunks: list<string>,
     *   perform_prompt: string
     * }>
     */
    public static function scripts(): array
    {
        return [
            'clinic-reception' => [
                'observe_model' => [
                    'Receptionist: Good morning. How can I help you?',
                    'You: Good morning. I have an appointment with Dr Rao at 10.',
                    'Receptionist: May I have your name, please?',
                    'You: Yes. My name is Priya Sharma.',
                ],
                'rehearse_chunks' => [
                    'Good morning. I have an appointment.',
                    'My name is …',
                    'It is at 10 o’clock.',
                ],
                'perform_prompt' => 'Greet the receptionist and say why you are here. Use your name and the appointment time.',
            ],
            'job-interview' => [
                'observe_model' => [
                    'Interviewer: Please sit down. Tell me about yourself.',
                    'You: Good morning. My name is Arun. I work in customer support.',
                    'Interviewer: What is one strength you have?',
                    'You: I listen carefully and I stay calm with customers.',
                ],
                'rehearse_chunks' => [
                    'Good morning. My name is …',
                    'I work in …',
                    'One strength is …',
                ],
                'perform_prompt' => 'Introduce yourself in one or two sentences and answer a simple strengths question.',
            ],
            'bank-counter' => [
                'observe_model' => [
                    'Clerk: Next, please. How can I help you?',
                    'You: I would like to deposit some money.',
                    'Clerk: May I have your account number?',
                    'You: Yes. It is on this form.',
                ],
                'rehearse_chunks' => [
                    'I would like to …',
                    'My account number is …',
                    'Could you check this, please?',
                ],
                'perform_prompt' => 'Say why you are at the bank and confirm one simple detail politely.',
            ],
            'restaurant-order' => [
                'observe_model' => [
                    'Server: Good evening. Are you ready to order?',
                    'You: Yes. I would like the vegetable thali, please.',
                    'Server: Would you like water or juice?',
                    'You: Water, please. Thank you.',
                ],
                'rehearse_chunks' => [
                    'I would like …, please.',
                    'Could I have water, please?',
                    'Thank you.',
                ],
                'perform_prompt' => 'Greet the server and order one dish and one drink politely.',
            ],
            'hotel-checkin' => [
                'observe_model' => [
                    'Receptionist: Welcome. Do you have a reservation?',
                    'You: Yes. The name is Meera Joshi.',
                    'Receptionist: A room for two nights?',
                    'You: Yes. Could I have a quiet room, please?',
                ],
                'rehearse_chunks' => [
                    'I have a reservation.',
                    'The name is …',
                    'Could I have a quiet room, please?',
                ],
                'perform_prompt' => 'Check in: give your name and one simple room request.',
            ],
            'phone-customer-support' => [
                'observe_model' => [
                    'Agent: Hello, support team. How can I help you?',
                    'You: Hello. My internet is not working today.',
                    'Agent: I am sorry. Can you tell me your customer number?',
                    'You: Yes. It is 4821. Could you check it, please?',
                ],
                'rehearse_chunks' => [
                    'Hello. I have a problem with …',
                    'My customer number is …',
                    'Could you help me, please?',
                ],
                'perform_prompt' => 'Explain the problem in one sentence and give one detail the agent needs.',
            ],
            'grocery-shop' => [
                'observe_model' => [
                    'Shopkeeper: Yes, please?',
                    'You: I need one kilo of rice, please.',
                    'Shopkeeper: Anything else?',
                    'You: Yes. Where is the milk?',
                ],
                'rehearse_chunks' => [
                    'I need …, please.',
                    'How much is this?',
                    'Where is the …?',
                ],
                'perform_prompt' => 'Ask for one item and one price or location in the shop.',
            ],
            'school-parent-meeting' => [
                'observe_model' => [
                    'Teacher: Thank you for coming. How is Ananya at home?',
                    'You: She is well. I wanted to ask about her reading.',
                    'Teacher: She is improving. Please practise ten minutes a day.',
                    'You: Thank you. I will do that.',
                ],
                'rehearse_chunks' => [
                    'I wanted to ask about …',
                    'How is she doing in …?',
                    'Thank you. I will …',
                ],
                'perform_prompt' => 'Greet the teacher and ask one clear question about your child’s learning.',
            ],
            'doctor-appointment' => [
                'observe_model' => [
                    'Doctor: What is the problem today?',
                    'You: I have a sore throat since yesterday.',
                    'Doctor: Do you have a fever?',
                    'You: A little. It started last night.',
                ],
                'rehearse_chunks' => [
                    'I have … since …',
                    'It started …',
                    'I feel …',
                ],
                'perform_prompt' => 'Tell the doctor one symptom, when it started, and answer one yes/no question.',
            ],
            'airport-checkin' => [
                'observe_model' => [
                    'Agent: Good afternoon. Passport, please.',
                    'You: Here it is. I am flying to Bengaluru.',
                    'Agent: Window or aisle?',
                    'You: Aisle, please. I have one small bag.',
                ],
                'rehearse_chunks' => [
                    'I am flying to …',
                    'Window, please. / Aisle, please.',
                    'I have one bag.',
                ],
                'perform_prompt' => 'Check in for a flight: destination, seat preference, and bags.',
            ],
            'office-meeting' => [
                'observe_model' => [
                    'Lead: Let’s start. Can you share the update?',
                    'You: Yes. The report is ready. I sent it this morning.',
                    'Lead: Any problem?',
                    'You: One delay on page two. I can fix it today.',
                ],
                'rehearse_chunks' => [
                    'The update is …',
                    'I can … today.',
                    'Could we look at …?',
                ],
                'perform_prompt' => 'Give a short work update and one next step.',
            ],
            'bus-train-ticket' => [
                'observe_model' => [
                    'Clerk: Where are you going?',
                    'You: One ticket to Mysuru, please.',
                    'Clerk: Morning or afternoon?',
                    'You: The next bus, please. How much is it?',
                ],
                'rehearse_chunks' => [
                    'One ticket to …, please.',
                    'The next bus/train, please.',
                    'How much is it?',
                ],
                'perform_prompt' => 'Buy a ticket: destination, time, and ask the price.',
            ],
        ];
    }
}
