<?php

declare(strict_types=1);

namespace App\Learning\LevelSessions;

use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;

/**
 * LPL-04: project exact speaking-evidence lifecycle for a level without
 * awarding spoken mastery or a spoken-progress claim from the MCQ checkpoint.
 */
final class LevelSpeakingEvidenceService
{
    /**
     * @var list<string>
     */
    private const ACCEPTED_RECORDING_STATUSES = [
        'validated',
        'awaiting_learner_confirmation',
        'confirmed',
        'corrected',
    ];

    public function enabled(): bool
    {
        return config('level_sessions.speaking_evidence.enabled', true) === true;
    }

    public function ruleKey(): string
    {
        return (string) config(
            'level_sessions.speaking_evidence.rule_key',
            'level_speaking_evidence_v1',
        );
    }

    /**
     * @return array{
     *   evidence_state: 'not_recorded'|'recorded_unassessed'|'awaiting_review'|'reviewed',
     *   spoken_state: 'unassessed',
     *   spoken_progress_claim: false
     * }
     */
    public function projection(
        OrganizationContext $context,
        int $levelNumber,
    ): array {
        if (! $this->enabled() || $context->membershipId === null) {
            return $this->payload('not_recorded');
        }

        if ($this->hasAcceptedLevelRecording(
            $context->organizationId,
            $context->membershipId,
            $levelNumber,
        )) {
            return $this->payload('recorded_unassessed');
        }

        return $this->payload('not_recorded');
    }

    private function hasAcceptedLevelRecording(
        string $organizationId,
        string $membershipId,
        int $levelNumber,
    ): bool {
        return DB::table('voice_recordings as recordings')
            ->join('conversation_sessions as sessions', function ($join): void {
                $join
                    ->on('sessions.organization_id', '=', 'recordings.organization_id')
                    ->on('sessions.id', '=', 'recordings.conversation_session_id')
                    ->on('sessions.learner_membership_id', '=', 'recordings.learner_membership_id');
            })
            ->join('conversation_lesson_drills as drills', function ($join): void {
                $join
                    ->on('drills.organization_id', '=', 'sessions.organization_id')
                    ->on('drills.conversation_session_id', '=', 'sessions.id')
                    ->on('drills.learner_membership_id', '=', 'sessions.learner_membership_id');
            })
            ->where('recordings.organization_id', $organizationId)
            ->where('recordings.learner_membership_id', $membershipId)
            ->where('drills.level_number', $levelNumber)
            ->whereIn('recordings.status', self::ACCEPTED_RECORDING_STATUSES)
            ->exists();
    }

    /**
     * @param  'not_recorded'|'recorded_unassessed'|'awaiting_review'|'reviewed'  $evidenceState
     * @return array{
     *   evidence_state: 'not_recorded'|'recorded_unassessed'|'awaiting_review'|'reviewed',
     *   spoken_state: 'unassessed',
     *   spoken_progress_claim: false
     * }
     */
    private function payload(string $evidenceState): array
    {
        return [
            'evidence_state' => $evidenceState,
            'spoken_state' => 'unassessed',
            'spoken_progress_claim' => false,
        ];
    }
}
