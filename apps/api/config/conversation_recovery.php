<?php

declare(strict_types=1);

return [
    // ADR-0042 permits at most 15 minutes; the store also hard-caps this value.
    'ttl_seconds' => 900,
    // Includes bounded generated reply audio, never source microphone audio.
    'max_plaintext_bytes' => 8 * 1024 * 1024,
];
