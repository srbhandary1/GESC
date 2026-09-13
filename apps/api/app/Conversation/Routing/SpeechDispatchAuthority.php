<?php

declare(strict_types=1);

namespace App\Conversation\Routing;

use App\Conversation\Providers\ConversationProviderException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

final readonly class SpeechDispatchAuthority
{
    public function __construct(
        private Application $application,
        private Repository $config,
    ) {}

    public function assertDispatchAllowed(string $providerKey, string $stage): never
    {
        $this->denialReasons($providerKey, $stage);
        throw $this->denied($providerKey, $stage);
    }

    /**
     * @return list<string>
     */
    public function denialReasons(string $providerKey, string $stage): array
    {
        $reasons = [
            'http_enablement_not_authorized',
            'live_deployment_key_rotation_required',
        ];
        $candidate = $this->config->get('speech_routing.candidates.'.$providerKey);
        if (! is_array($candidate) || ($candidate['stage'] ?? null) !== $stage) {
            $reasons[] = 'candidate_unregistered';
        }
        if ($this->application->environment('production')) {
            $reasons[] = 'production_blocked';
        }
        if (filter_var(
            is_array($candidate) ? ($candidate['kill_switch'] ?? true) : true,
            FILTER_VALIDATE_BOOL,
        )) {
            $reasons[] = 'kill_switch';
        }
        if (! filter_var(
            is_array($candidate) ? ($candidate['dispatch_authorized'] ?? false) : false,
            FILTER_VALIDATE_BOOL,
        )) {
            $reasons[] = 'dispatch_flag_off';
        }
        $evidence = is_array($candidate) ? ($candidate['evidence_file'] ?? '') : '';
        if (! is_string($evidence) || $evidence === '' || ! is_file($evidence)) {
            $reasons[] = 'evidence_missing';
        }
        $secret = is_array($candidate) ? ($candidate['secret_file'] ?? '') : '';
        if (! is_string($secret) || $secret === '' || ! is_file($secret)) {
            $reasons[] = 'secret_unbound';
        }

        return $reasons;
    }

    private function denied(
        string $providerKey,
        string $stage,
    ): ConversationProviderException {
        return new ConversationProviderException(
            safeCode: 'provider_not_authorized',
            providerKey: $providerKey,
            modelVersion: $stage.'-dispatch-blocked',
        );
    }
}
