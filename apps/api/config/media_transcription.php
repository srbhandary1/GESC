<?php

declare(strict_types=1);

return [
    'required_environment' => 'lpl-media-transcription',
    'enabled' => env('LPL_MEDIA_TRANSCRIPTION_ENABLED', false),
    'live_requests_enabled' => env(
        'LPL_MEDIA_TRANSCRIPTION_LIVE_REQUESTS_ENABLED',
        false,
    ),
    'approval_reference' => 'APP-0052',
    'approved_request_sha256' => 'cb489ec3f034861d256934aa0fcf5128a997af73ec6da4bd461453ebce02bdf4',
    'organization_id' => '01KYS97G7MX374P303XJHDXT72',
    'program_id' => '01KZNK3BWD8A8NJ5A5JW0ZVZMT',
    'expected_manifest_sha256' => 'e26eda8b96626503db37ec76d10f350b2cf76328777803c999dc7ecb2aab0a6e',
    'manifest' => [
        [
            'sequence' => 1,
            'part_key' => 'introduction',
            'media_asset_id' => '01KZNFYHCJSDWSPK08ZAC7G3AJ',
            'media_rights_revision_id' => '01KZNKNFWGSRDBD57H18YCDPG8',
            'source_sha256' => '6838818d74481ffe38e13948dea2954886dcc519ed927112b703f8e2064573aa',
            'source_bytes' => 4_968_408,
            'source_duration_milliseconds' => 310_526,
        ],
        [
            'sequence' => 2,
            'part_key' => 'practice',
            'media_asset_id' => '01KZNFYJ1M911SB8YVH9P4EZZ0',
            'media_rights_revision_id' => '01KZNKNFXKFRSVXRKPYWHDCF7C',
            'source_sha256' => '75909e9baf340356d7ecf66435977ee1cce1fe93a597da58a24b5b9ea1833dd7',
            'source_bytes' => 7_357_881,
            'source_duration_milliseconds' => 459_868,
        ],
        [
            'sequence' => 3,
            'part_key' => 'conversation',
            'media_asset_id' => '01KZNFYJVSZRPZCEPTTQ4QTMNT',
            'media_rights_revision_id' => '01KZNKNFYDDC31CV2BR155DXVR',
            'source_sha256' => '8124b3bc6f71b65258358b0121cc3d72f1543948ca3ef3a1bb419f1fa0d65c70',
            'source_bytes' => 2_157_215,
            'source_duration_milliseconds' => 134_826,
        ],
        [
            'sequence' => 4,
            'part_key' => 'interaction',
            'media_asset_id' => '01KZNFYK75V9WF8R5VCWRRZHNN',
            'media_rights_revision_id' => '01KZNKNFZ7A5Z9JPSC2AV0QPZQ',
            'source_sha256' => '4862e927078ecb0ea56b7240f6d5b52356365115a6512836357bf340fdcf895e',
            'source_bytes' => 4_276_686,
            'source_duration_milliseconds' => 267_293,
        ],
    ],
    'caps' => [
        'logical_assets' => 4,
        'http_attempts' => 8,
        'uploaded_bytes' => 37_520_380,
        'billable_milliseconds' => 2_345_026,
        'estimated_cost_microunits' => 100_000_000,
    ],
    'provenance_retention_days' => 365,
    'provider' => [
        'key' => 'azure-speech',
        'model' => 'fast-transcription-2025-10-15-provider-managed',
        'region' => 'centralindia',
        'api_version' => '2025-10-15',
        'route' => '/speechtotext/transcriptions:transcribe',
        'locale' => 'en-IN',
        'profanity_filter_mode' => 'None',
        'endpoint' => env('LPL_MEDIA_TRANSCRIPTION_AZURE_ENDPOINT', ''),
        'allowed_host' => env(
            'LPL_MEDIA_TRANSCRIPTION_AZURE_ALLOWED_HOST',
            '',
        ),
        'credential_kind' => env(
            'LPL_MEDIA_TRANSCRIPTION_AZURE_CREDENTIAL_KIND',
            '',
        ),
        'secret_file' => env(
            'LPL_MEDIA_TRANSCRIPTION_AZURE_SECRET_FILE',
            '',
        ),
        'connection_timeout_seconds' => 3,
        'request_timeout_seconds' => 300,
        'maximum_response_bytes' => 1_048_576,
    ],
    'evidence' => [
        'vendor_file' => env(
            'LPL_MEDIA_TRANSCRIPTION_VENDOR_EVIDENCE_FILE',
            '',
        ),
        'vendor_sha256' => env(
            'LPL_MEDIA_TRANSCRIPTION_VENDOR_EVIDENCE_SHA256',
            '',
        ),
        'price_file' => env(
            'LPL_MEDIA_TRANSCRIPTION_PRICE_EVIDENCE_FILE',
            '',
        ),
        'price_sha256' => env(
            'LPL_MEDIA_TRANSCRIPTION_PRICE_EVIDENCE_SHA256',
            '',
        ),
        'permit_file' => env(
            'LPL_MEDIA_TRANSCRIPTION_PERMIT_FILE',
            '',
        ),
        'safe_report_file' => env(
            'LPL_MEDIA_TRANSCRIPTION_SAFE_REPORT_FILE',
            '',
        ),
    ],
    'candidate' => [
        'disk' => env(
            'LPL_MEDIA_TRANSCRIPTION_CANDIDATE_DISK',
            'media_transcription_candidates',
        ),
        'unreconciled_expiry_days' => 7,
        'cleanup_target_minutes' => 15,
        'cleanup_hard_minutes' => 60,
        'deletion_max_attempts' => 5,
        'deletion_retry_seconds' => 60,
    ],
];
