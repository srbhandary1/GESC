<?php

declare(strict_types=1);

return [
    'upload_enabled' => env('VOICE_UPLOAD_ENABLED', false),
    'provider_driver' => env('VOICE_PROVIDER_DRIVER', 'disabled'),
    'allowed_organization_ids' => array_values(array_filter(
        array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('VOICE_ALLOWED_ORGANIZATION_IDS', '')),
        ),
        static fn (string $value): bool => $value !== '',
    )),
    'storage_disk' => env('VOICE_STORAGE_DISK', 'local'),
    'policy_version' => 'recording-policy-v1',
    'notice_revision' => 'voice-processing-notice-2026-09-19',
    'notice_locales' => ['en-IN', 'kn-IN'],
    'notices' => [
        'en-IN' => 'If you choose voice practice, one recording is uploaded privately to approved speech-processing providers to turn speech into text, including Indic speech translated to English when needed. If the primary transcription route cannot complete the request, an approved failover speech-processing provider may be used once. The minimum transcript or assistant text is then processed by an approved automated conversation provider. Raw audio is deleted after transcription, normally within 15 minutes and no later than one hour. If processing fails, encrypted audio may remain for up to 24 hours so you can retry. You can review, correct or discard the transcript and withdraw consent at any time. Your audio and transcript are not used for training or human review. Refusing keeps text practice available and does not affect marks or progress.',
        'kn-IN' => 'ನೀವು ಧ್ವನಿ ಅಭ್ಯಾಸವನ್ನು ಆಯ್ಕೆ ಮಾಡಿದರೆ, ಮಾತನ್ನು ಪಠ್ಯವಾಗಿ ಪರಿವರ್ತಿಸಲು ಒಂದು ಧ್ವನಿಮುದ್ರಣವನ್ನು ಅನುಮೋದಿತ ಧ್ವನಿ-ಸಂಸ್ಕರಣಾ ಸೇವಾ ಪೂರೈಕೆದಾರರಿಗೆ ಖಾಸಗಿಯಾಗಿ ಅಪ್‌ಲೋಡ್ ಮಾಡಲಾಗುತ್ತದೆ; ಅಗತ್ಯವಿದ್ದಾಗ ಭಾರತೀಯ ಭಾಷೆಯ ಮಾತನ್ನು ಇಂಗ್ಲಿಷ್‌ಗೆ ಅನುವಾದಿಸುವುದೂ ಇದರಲ್ಲಿ ಸೇರಿದೆ. ಪ್ರಾಥಮಿಕ ಲಿಪ್ಯಂತರ ಮಾರ್ಗವು ವಿನಂತಿಯನ್ನು ಪೂರ್ಣಗೊಳಿಸಲು ಸಾಧ್ಯವಾಗದಿದ್ದರೆ, ಅನುಮೋದಿತ ಪರ್ಯಾಯ ಧ್ವನಿ-ಸಂಸ್ಕರಣಾ ಸೇವಾ ಪೂರೈಕೆದಾರರನ್ನು ಒಮ್ಮೆ ಬಳಸಬಹುದು. ನಂತರ ಅಗತ್ಯವಿರುವ ಕನಿಷ್ಠ ಲಿಪ್ಯಂತರ ಅಥವಾ ಸಹಾಯಕ ಪಠ್ಯವನ್ನು ಅನುಮೋದಿತ ಸ್ವಯಂಚಾಲಿತ ಸಂಭಾಷಣಾ ಸೇವಾ ಪೂರೈಕೆದಾರರು ಸಂಸ್ಕರಿಸುತ್ತಾರೆ. ಲಿಪ್ಯಂತರದ ನಂತರ ಕಚ್ಚಾ ಆಡಿಯೊವನ್ನು ಸಾಮಾನ್ಯವಾಗಿ 15 ನಿಮಿಷಗಳಲ್ಲಿ ಮತ್ತು ಗರಿಷ್ಠ ಒಂದು ಗಂಟೆಯೊಳಗೆ ಅಳಿಸಲಾಗುತ್ತದೆ. ಪ್ರಕ್ರಿಯೆ ವಿಫಲವಾದರೆ ಮರುಪ್ರಯತ್ನಕ್ಕಾಗಿ ಎನ್‌ಕ್ರಿಪ್ಟ್ ಮಾಡಿದ ಆಡಿಯೊ ಗರಿಷ್ಠ 24 ಗಂಟೆಗಳವರೆಗೆ ಉಳಿಯಬಹುದು. ನೀವು ಲಿಪ್ಯಂತರವನ್ನು ಪರಿಶೀಲಿಸಬಹುದು, ಸರಿಪಡಿಸಬಹುದು ಅಥವಾ ತ್ಯಜಿಸಬಹುದು ಮತ್ತು ಯಾವುದೇ ಸಮಯದಲ್ಲಿ ಸಮ್ಮತಿಯನ್ನು ಹಿಂಪಡೆಯಬಹುದು. ನಿಮ್ಮ ಆಡಿಯೊ ಅಥವಾ ಲಿಪ್ಯಂತರವನ್ನು ತರಬೇತಿ ಅಥವಾ ಮಾನವ ಪರಿಶೀಲನೆಗೆ ಬಳಸುವುದಿಲ್ಲ. ನಿರಾಕರಿಸಿದರೂ ಪಠ್ಯ ಅಭ್ಯಾಸ ಲಭ್ಯವಿರುತ್ತದೆ ಮತ್ತು ಅಂಕಗಳು ಅಥವಾ ಪ್ರಗತಿಗೆ ಪರಿಣಾಮ ಬೀರುವುದಿಲ್ಲ.',
    ],
    'maximum_bytes' => 10 * 1024 * 1024,
    // Speak / recorded STT. Sarvam REST is about 30 seconds. Learn capture stays 120s in learning.php.
    'maximum_duration_ms' => 25_000,
    'source_delete_minutes' => 60,
    'provider_failure_hours' => 24,
    'candidate_expiry_hours' => 24,
    'history_days' => 30,
    'maximum_provider_attempts' => 2,
    'deletion_lease_seconds' => env('VOICE_DELETION_LEASE_SECONDS', 300),
    'deletion_retry_base_seconds' => env(
        'VOICE_DELETION_RETRY_BASE_SECONDS',
        60,
    ),
    'deletion_retry_max_seconds' => env(
        'VOICE_DELETION_RETRY_MAX_SECONDS',
        3600,
    ),
    'deletion_max_attempts' => env('VOICE_DELETION_MAX_ATTEMPTS', 5),
    'deletion_outbox_stale_seconds' => env(
        'VOICE_DELETION_OUTBOX_STALE_SECONDS',
        3600,
    ),
    'soft_launch' => [
        'stt_api_version' => '2025-10-15',
        'stt_route' => '/speechtotext/transcriptions:transcribe',
        'stt_locale' => 'en-IN',
        'stt_profanity_filter_mode' => 'None',
        'maximum_json_response_bytes' => 1024 * 1024,
    ],
    /*
     | Optional Indic HTTP STT (translate-to-English). Off until
     | VOICE_PROVIDER_DRIVER=indic with a secret file outside the repo.
     | Compatible with Sarvam-style REST; no vendor SDK in domain code.
     */
    'indic' => [
        'transcribe_url' => env('VOICE_INDIC_TRANSCRIBE_URL', ''),
        'allowed_host' => env('VOICE_INDIC_ALLOWED_HOST', ''),
        'secret_file' => env('VOICE_INDIC_SECRET_FILE', ''),
        'auth_header' => env('VOICE_INDIC_AUTH_HEADER', 'api-subscription-key'),
        'model' => env('VOICE_INDIC_MODEL', ''),
        'mode' => env('VOICE_INDIC_MODE', 'translate'),
        'language_code' => env('VOICE_INDIC_LANGUAGE_CODE', 'unknown'),
        'output_language' => env('VOICE_INDIC_OUTPUT_LANGUAGE', 'en-IN'),
        'maximum_json_response_bytes' => 256 * 1024,
        'connection_timeout_seconds' => 5,
        'request_timeout_seconds' => 20,
    ],
];
