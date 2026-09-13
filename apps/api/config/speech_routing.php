<?php

declare(strict_types=1);

return [
    'version' => 'speech-route-v1',
    'maximum_dispatches_per_stage' => 2,
    'live_router_enabled' => false,
    'candidates' => [
        'elevenlabs-tts' => [
            'stage' => 'tts',
            'product' => 'eleven_v3_conversational',
            'route_role' => 'challenger',
            'dispatch_authorized' => env('SPEECH_ELEVENLABS_TTS_DISPATCH_AUTHORIZED', false),
            'kill_switch' => env('SPEECH_ELEVENLABS_TTS_KILL_SWITCH', true),
            'secret_file' => env('SPEECH_ELEVENLABS_SECRET_FILE', ''),
            'evidence_file' => env('SPEECH_ELEVENLABS_EVIDENCE_FILE', ''),
            'allowed_host' => env('SPEECH_ELEVENLABS_ALLOWED_HOST', 'api.in.residency.elevenlabs.io'),
        ],
        'elevenlabs-stt' => [
            'stage' => 'stt',
            'product' => 'scribe_v2',
            'route_role' => 'challenger',
            'dispatch_authorized' => env('SPEECH_ELEVENLABS_STT_DISPATCH_AUTHORIZED', false),
            'kill_switch' => env('SPEECH_ELEVENLABS_STT_KILL_SWITCH', true),
            'secret_file' => env('SPEECH_ELEVENLABS_SECRET_FILE', ''),
            'evidence_file' => env('SPEECH_ELEVENLABS_EVIDENCE_FILE', ''),
            'allowed_host' => env('SPEECH_ELEVENLABS_ALLOWED_HOST', 'api.in.residency.elevenlabs.io'),
        ],
        'elevenlabs-stt-realtime' => [
            'stage' => 'stt',
            'product' => 'scribe_v2_realtime',
            'route_role' => 'challenger',
            'dispatch_authorized' => env(
                'SPEECH_ELEVENLABS_STT_REALTIME_DISPATCH_AUTHORIZED',
                false,
            ),
            'kill_switch' => env(
                'SPEECH_ELEVENLABS_STT_REALTIME_KILL_SWITCH',
                true,
            ),
            'secret_file' => env('SPEECH_ELEVENLABS_SECRET_FILE', ''),
            'evidence_file' => env('SPEECH_ELEVENLABS_EVIDENCE_FILE', ''),
            'allowed_host' => env(
                'SPEECH_ELEVENLABS_ALLOWED_HOST',
                'api.in.residency.elevenlabs.io',
            ),
        ],
        'sarvam-saaras-realtime' => [
            'stage' => 'stt',
            'product' => 'saaras_realtime',
            'route_role' => 'challenger',
            'dispatch_authorized' => env(
                'SPEECH_SARVAM_SAARAS_REALTIME_DISPATCH_AUTHORIZED',
                false,
            ),
            'kill_switch' => env(
                'SPEECH_SARVAM_SAARAS_REALTIME_KILL_SWITCH',
                true,
            ),
            'secret_file' => env('VOICE_INDIC_SECRET_FILE', ''),
            'evidence_file' => env('SPEECH_SARVAM_EVIDENCE_FILE', ''),
            'allowed_host' => env('SPEECH_SARVAM_STT_ALLOWED_HOST', 'api.sarvam.ai'),
        ],
        'sarvam-bulbul-tts' => [
            'stage' => 'tts',
            'product' => 'bulbul:v3',
            'route_role' => 'challenger',
            'dispatch_authorized' => env('SPEECH_SARVAM_BULBUL_DISPATCH_AUTHORIZED', false),
            'kill_switch' => env('SPEECH_SARVAM_BULBUL_KILL_SWITCH', true),
            'secret_file' => env('VOICE_INDIC_SECRET_FILE', ''),
            'evidence_file' => env('SPEECH_SARVAM_EVIDENCE_FILE', ''),
            'allowed_host' => env('SPEECH_SARVAM_TTS_ALLOWED_HOST', 'api.sarvam.ai'),
        ],
    ],
    'wave38' => [
        'status_file' => env('SPEECH_WAVE38_STATUS_FILE', ''),
        'elevenlabs_cap_inr' => 500,
        'sarvam_cap_inr' => 500,
        'required_elevenlabs_host' => 'api.in.residency.elevenlabs.io',
    ],
];
