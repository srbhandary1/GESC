<?php

declare(strict_types=1);

return [
    'provider_driver' => env(
        'CONVERSATION_PROVIDER_DRIVER',
        'disabled',
    ),
    'maximum_provider_attempts' => 2,
    'maximum_utterance_characters' => 500,
    'session' => [
        'max_learner_turns' => (int) env('CONVERSATION_SESSION_MAX_LEARNER_TURNS', 8),
        'max_tokens' => (int) env('CONVERSATION_SESSION_MAX_TOKENS', 12000),
    ],
    'budget_alert' => [
        'approved_spend_cap_inr' => (int) env('CONVERSATION_APPROVED_SPEND_CAP_INR', 500),
        'warning_threshold_percent' => (int) env(
            'CONVERSATION_BUDGET_WARNING_THRESHOLD_PERCENT',
            80,
        ),
    ],
    'speech_artifact_disk' => env('CONVERSATION_SPEECH_ARTIFACT_DISK', 'local'),
    'speech_artifact_maximum_bytes' => (int) env(
        'CONVERSATION_SPEECH_ARTIFACT_MAXIMUM_BYTES',
        2_000_000,
    ),
    'speech_artifact_retention_days' => (int) env(
        'CONVERSATION_SPEECH_ARTIFACT_RETENTION_DAYS',
        30,
    ),
    'coaching_modes' => ['gentle', 'tutor', 'fluency'],
    'ui_locales' => [
        'kn-IN',
        'ml-IN',
        'ta-IN',
        'te-IN',
        'hi-IN',
        'en-IN',
    ],
    'prototype' => [
        'persona_key' => 'patient-friend-prototype',
        'scenario_key' => 'everyday-friend-prototype',
        'coaching_mode' => 'gentle',
        'ui_locale' => 'kn-IN',
        'mode' => 'friend',
    ],
    'prototypes' => [
        'friend' => [
            'persona_key' => 'patient-friend-prototype',
            'scenario_key' => 'everyday-friend-prototype',
            'mode' => 'friend',
        ],
        'skill_focus' => [
            'persona_key' => 'patient-friend-prototype',
            'scenario_key' => 'past-tense-skill-focus-prototype',
            'mode' => 'friend',
        ],
        'review' => [
            'persona_key' => 'patient-friend-prototype',
            'scenario_key' => 'carry-sentence-review-prototype',
            'mode' => 'friend',
        ],
        'roleplay' => [
            'persona_key' => 'clinic-reception-roleplay-prototype',
            'scenario_key' => 'clinic-reception-roleplay-scenario',
            'mode' => 'roleplay',
        ],
    ],

    'roleplay_catalog' => [
        [
            'id' => 'clinic-reception',
            'display_name' => 'Clinic reception',
            'persona_key' => 'clinic-reception-roleplay-prototype',
            'scenario_key' => 'clinic-reception-roleplay-scenario',
            'topic_code' => 'clinic-reception',
            'style_code' => 'clinic-reception-roleplay-v1',
            'response_code' => 'roleplay.clinic-reception.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'greet-receptionist',
                    'title' => 'Greet the receptionist',
                    'completion_criteria_code' => 'synthetic.greet-receptionist.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'state-appointment-reason',
                    'title' => 'State the appointment reason',
                    'completion_criteria_code' => 'synthetic.state-appointment-reason.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
        [
            'id' => 'job-interview',
            'display_name' => 'Job interview',
            'persona_key' => 'job-interview-roleplay-prototype',
            'scenario_key' => 'job-interview-roleplay-scenario',
            'topic_code' => 'job-interview',
            'style_code' => 'job-interview-roleplay-v1',
            'response_code' => 'roleplay.job-interview.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'introduce-yourself',
                    'title' => 'Introduce yourself briefly',
                    'completion_criteria_code' => 'synthetic.introduce-yourself.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'answer-strength-question',
                    'title' => 'Answer a simple strengths question',
                    'completion_criteria_code' => 'synthetic.answer-strength-question.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
        [
            'id' => 'bank-counter',
            'display_name' => 'Bank counter',
            'persona_key' => 'bank-counter-roleplay-prototype',
            'scenario_key' => 'bank-counter-roleplay-scenario',
            'topic_code' => 'bank-counter',
            'style_code' => 'bank-counter-roleplay-v1',
            'response_code' => 'roleplay.bank-counter.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'state-banking-need',
                    'title' => 'State why you are at the bank',
                    'completion_criteria_code' => 'synthetic.state-banking-need.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'confirm-account-details',
                    'title' => 'Confirm simple account details politely',
                    'completion_criteria_code' => 'synthetic.confirm-account-details.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
        [
            'id' => 'restaurant-order',
            'display_name' => 'Restaurant order',
            'persona_key' => 'restaurant-order-roleplay-prototype',
            'scenario_key' => 'restaurant-order-roleplay-scenario',
            'topic_code' => 'restaurant-order',
            'style_code' => 'restaurant-order-roleplay-v1',
            'response_code' => 'roleplay.restaurant-order.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'greet-server',
                    'title' => 'Greet the server',
                    'completion_criteria_code' => 'synthetic.greet-server.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'place-simple-order',
                    'title' => 'Place a simple food order',
                    'completion_criteria_code' => 'synthetic.place-simple-order.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
        [
            'id' => 'hotel-checkin',
            'display_name' => 'Hotel check-in',
            'persona_key' => 'hotel-checkin-roleplay-prototype',
            'scenario_key' => 'hotel-checkin-roleplay-scenario',
            'topic_code' => 'hotel-checkin',
            'style_code' => 'hotel-checkin-roleplay-v1',
            'response_code' => 'roleplay.hotel-checkin.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'state-reservation-name',
                    'title' => 'Give your reservation name',
                    'completion_criteria_code' => 'synthetic.state-reservation-name.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'ask-room-question',
                    'title' => 'Ask one practical room question',
                    'completion_criteria_code' => 'synthetic.ask-room-question.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
        [
            'id' => 'phone-customer-support',
            'display_name' => 'Phone customer support',
            'persona_key' => 'phone-customer-support-roleplay-prototype',
            'scenario_key' => 'phone-customer-support-roleplay-scenario',
            'topic_code' => 'phone-customer-support',
            'style_code' => 'phone-customer-support-roleplay-v1',
            'response_code' => 'roleplay.phone-customer-support.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'explain-problem',
                    'title' => 'Explain the problem clearly',
                    'completion_criteria_code' => 'synthetic.explain-problem.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'confirm-next-step',
                    'title' => 'Confirm the next support step',
                    'completion_criteria_code' => 'synthetic.confirm-next-step.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
        [
            'id' => 'grocery-shop',
            'display_name' => 'Grocery shop',
            'persona_key' => 'grocery-shop-roleplay-prototype',
            'scenario_key' => 'grocery-shop-roleplay-scenario',
            'topic_code' => 'grocery-shop',
            'style_code' => 'grocery-shop-roleplay-v1',
            'response_code' => 'roleplay.grocery-shop.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'ask-for-item',
                    'title' => 'Ask for an item location or price',
                    'completion_criteria_code' => 'synthetic.ask-for-item.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'complete-purchase',
                    'title' => 'Complete a short purchase exchange',
                    'completion_criteria_code' => 'synthetic.complete-purchase.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
        [
            'id' => 'school-parent-meeting',
            'display_name' => 'School parent meeting',
            'persona_key' => 'school-parent-meeting-roleplay-prototype',
            'scenario_key' => 'school-parent-meeting-roleplay-scenario',
            'topic_code' => 'school-parent-meeting',
            'style_code' => 'school-parent-meeting-roleplay-v1',
            'response_code' => 'roleplay.school-parent-meeting.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'greet-teacher',
                    'title' => 'Greet the teacher politely',
                    'completion_criteria_code' => 'synthetic.greet-teacher.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'ask-about-progress',
                    'title' => 'Ask about the child progress',
                    'completion_criteria_code' => 'synthetic.ask-about-progress.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
        [
            'id' => 'doctor-appointment',
            'display_name' => 'Doctor appointment',
            'persona_key' => 'doctor-appointment-roleplay-prototype',
            'scenario_key' => 'doctor-appointment-roleplay-scenario',
            'topic_code' => 'doctor-appointment',
            'style_code' => 'doctor-appointment-roleplay-v1',
            'response_code' => 'roleplay.doctor-appointment.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'describe-symptoms',
                    'title' => 'Describe symptoms simply',
                    'completion_criteria_code' => 'synthetic.describe-symptoms.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'ask-medicine-question',
                    'title' => 'Ask one medicine or follow-up question',
                    'completion_criteria_code' => 'synthetic.ask-medicine-question.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
        [
            'id' => 'airport-checkin',
            'display_name' => 'Airport check-in',
            'persona_key' => 'airport-checkin-roleplay-prototype',
            'scenario_key' => 'airport-checkin-roleplay-scenario',
            'topic_code' => 'airport-checkin',
            'style_code' => 'airport-checkin-roleplay-v1',
            'response_code' => 'roleplay.airport-checkin.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'present-booking',
                    'title' => 'Present booking details',
                    'completion_criteria_code' => 'synthetic.present-booking.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'answer-baggage-question',
                    'title' => 'Answer a simple baggage question',
                    'completion_criteria_code' => 'synthetic.answer-baggage-question.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
        [
            'id' => 'office-meeting',
            'display_name' => 'Office meeting',
            'persona_key' => 'office-meeting-roleplay-prototype',
            'scenario_key' => 'office-meeting-roleplay-scenario',
            'topic_code' => 'office-meeting',
            'style_code' => 'office-meeting-roleplay-v1',
            'response_code' => 'roleplay.office-meeting.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'share-status-update',
                    'title' => 'Share a short status update',
                    'completion_criteria_code' => 'synthetic.share-status-update.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'agree-next-action',
                    'title' => 'Agree one next action',
                    'completion_criteria_code' => 'synthetic.agree-next-action.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
        [
            'id' => 'bus-train-ticket',
            'display_name' => 'Bus or train ticket',
            'persona_key' => 'bus-train-ticket-roleplay-prototype',
            'scenario_key' => 'bus-train-ticket-roleplay-scenario',
            'topic_code' => 'bus-train-ticket',
            'style_code' => 'bus-train-ticket-roleplay-v1',
            'response_code' => 'roleplay.bus-train-ticket.follow-up.v1',
            'objectives' => [
                [
                    'objective_key' => 'request-ticket',
                    'title' => 'Request a ticket to a destination',
                    'completion_criteria_code' => 'synthetic.request-ticket.v1',
                    'sort_order' => 1,
                ],
                [
                    'objective_key' => 'confirm-fare-time',
                    'title' => 'Confirm fare or departure time',
                    'completion_criteria_code' => 'synthetic.confirm-fare-time.v1',
                    'sort_order' => 2,
                ],
            ],
        ],
    ],

    'guidance_tts' => [
        'live_requests_enabled' => env(
            'CONVERSATION_GUIDANCE_TTS_LIVE_REQUESTS_ENABLED',
            false,
        ),
        'endpoint' => env('CONVERSATION_GUIDANCE_TTS_ENDPOINT', ''),
        'allowed_host' => env('CONVERSATION_GUIDANCE_TTS_ALLOWED_HOST', ''),
        'region' => 'centralindia',
        'credential_kind' => env('CONVERSATION_GUIDANCE_TTS_CREDENTIAL_KIND', ''),
        'secret_file' => env('CONVERSATION_GUIDANCE_TTS_SECRET_FILE', ''),
        'route' => '/tts/cognitiveservices/v1',
        'output_format' => 'riff-24khz-16bit-mono-pcm',
        'model_version' => 'soft-launch-guidance-tts-v1',
        'maximum_characters' => 400,
        'maximum_audio_response_bytes' => 10 * 1024 * 1024,
        'connection_timeout_seconds' => 3,
        'request_timeout_seconds' => 12,
        'maximum_spend_inr' => 500.0,
        'approved_spend_cap_inr' => env(
            'CONVERSATION_GUIDANCE_TTS_APPROVED_SPEND_CAP_INR',
        ),
        'vendor_evidence_path' => env(
            'CONVERSATION_GUIDANCE_TTS_VENDOR_EVIDENCE_PATH',
            '',
        ),
        'spend_approval_path' => env(
            'CONVERSATION_GUIDANCE_TTS_SPEND_APPROVAL_PATH',
            '',
        ),
        'evidence' => [
            'subscription' => env(
                'CONVERSATION_GUIDANCE_TTS_SUBSCRIPTION_EVIDENCE_ID',
                '',
            ),
            'contracting_entity' => env(
                'CONVERSATION_GUIDANCE_TTS_CONTRACTING_ENTITY_REVIEW_ID',
                '',
            ),
            'agreement_sha256' => env(
                'CONVERSATION_GUIDANCE_TTS_AGREEMENT_SHA256',
                '',
            ),
            'dpa_sha256' => env('CONVERSATION_GUIDANCE_TTS_DPA_SHA256', ''),
            'subprocessor_sha256' => env(
                'CONVERSATION_GUIDANCE_TTS_SUBPROCESSOR_SHA256',
                '',
            ),
            'privacy_legal_review' => env(
                'CONVERSATION_GUIDANCE_TTS_PRIVACY_LEGAL_REVIEW_ID',
                '',
            ),
            'logging_disabled' => env(
                'CONVERSATION_GUIDANCE_TTS_LOGGING_DISABLED_EVIDENCE_ID',
                '',
            ),
            'credential_version' => env(
                'CONVERSATION_GUIDANCE_TTS_CREDENTIAL_VERSION_ID',
                '',
            ),
            'spend_approval' => env(
                'CONVERSATION_GUIDANCE_TTS_SPEND_APPROVAL_ID',
                '',
            ),
        ],
        'pricing' => [
            'inr_per_tts_character' => (float) env(
                'CONVERSATION_GUIDANCE_TTS_INR_PER_CHARACTER',
                0.0003814041,
            ),
        ],
    ],

    'azure_openai' => [
        'live_requests_enabled' => env(
            'CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED',
            false,
        ),
        'endpoint' => env('CONVERSATION_AZURE_OPENAI_ENDPOINT', ''),
        'allowed_host' => env('CONVERSATION_AZURE_OPENAI_ALLOWED_HOST', ''),
        'deployment' => env('CONVERSATION_AZURE_OPENAI_DEPLOYMENT', ''),
        'api_version' => env(
            'CONVERSATION_AZURE_OPENAI_API_VERSION',
            '2024-10-21',
        ),
        'credential_kind' => env(
            'CONVERSATION_AZURE_OPENAI_CREDENTIAL_KIND',
            '',
        ),
        'secret_file' => env('CONVERSATION_AZURE_OPENAI_SECRET_FILE', ''),
        'connection_timeout_seconds' => 3,
        'request_timeout_seconds' => 30,
        'maximum_json_response_bytes' => 256 * 1024,
        'stream_responses' => env(
            'CONVERSATION_AZURE_OPENAI_STREAM_RESPONSES',
            false,
        ),
        'maximum_spend_inr' => 10000.0,
        'approved_spend_cap_inr' => env(
            'CONVERSATION_AZURE_OPENAI_APPROVED_SPEND_CAP_INR',
        ),
        'vendor_evidence_path' => env(
            'CONVERSATION_AZURE_OPENAI_VENDOR_EVIDENCE_PATH',
            '',
        ),
        'spend_approval_path' => env(
            'CONVERSATION_AZURE_OPENAI_SPEND_APPROVAL_PATH',
            '',
        ),
        'evidence' => [
            'subscription' => env(
                'CONVERSATION_AZURE_OPENAI_SUBSCRIPTION_EVIDENCE_ID',
                '',
            ),
            'contracting_entity' => env(
                'CONVERSATION_AZURE_OPENAI_CONTRACTING_ENTITY_REVIEW_ID',
                '',
            ),
            'agreement_sha256' => env(
                'CONVERSATION_AZURE_OPENAI_AGREEMENT_SHA256',
                '',
            ),
            'dpa_sha256' => env('CONVERSATION_AZURE_OPENAI_DPA_SHA256', ''),
            'subprocessor_sha256' => env(
                'CONVERSATION_AZURE_OPENAI_SUBPROCESSOR_SHA256',
                '',
            ),
            'privacy_legal_review' => env(
                'CONVERSATION_AZURE_OPENAI_PRIVACY_LEGAL_REVIEW_ID',
                '',
            ),
            'logging_disabled' => env(
                'CONVERSATION_AZURE_OPENAI_LOGGING_DISABLED_EVIDENCE_ID',
                '',
            ),
            'credential_version' => env(
                'CONVERSATION_AZURE_OPENAI_CREDENTIAL_VERSION_ID',
                '',
            ),
            'spend_approval' => env(
                'CONVERSATION_AZURE_OPENAI_SPEND_APPROVAL_ID',
                '',
            ),
            'region_acceptance' => env(
                'CONVERSATION_AZURE_OPENAI_REGION_ACCEPTANCE_ID',
                '',
            ),
        ],
        'pricing' => [
            'microunits_per_input_token' => (int) env(
                'CONVERSATION_AZURE_OPENAI_MICROUNITS_PER_INPUT_TOKEN',
                2,
            ),
            'microunits_per_output_token' => (int) env(
                'CONVERSATION_AZURE_OPENAI_MICROUNITS_PER_OUTPUT_TOKEN',
                8,
            ),
        ],
    ],
];
