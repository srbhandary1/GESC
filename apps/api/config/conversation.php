<?php

declare(strict_types=1);

return [
    'provider_driver' => env(
        'CONVERSATION_PROVIDER_DRIVER',
        'disabled',
    ),
    'maximum_provider_attempts' => 2,
    'maximum_utterance_characters' => 500,
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
