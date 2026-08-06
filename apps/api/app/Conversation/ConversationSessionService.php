<?php

declare(strict_types=1);

namespace App\Conversation;

use App\Conversation\Exceptions\ConversationOperationRejected;
use App\Learning\LearnerAuthority;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class ConversationSessionService
{
    public function __construct(
        private ConversationEnvironmentGate $environment,
        private LearnerAuthority $authority,
        private ConversationCommandIdempotency $commands,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function start(
        User $learner,
        string $personaKey,
        string $scenarioKey,
        string $coachingMode,
        string $idempotencyKey,
    ): array {
        $this->environment->requireDeterministicFakes();
        $this->commands->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $learner,
            $personaKey,
            $scenarioKey,
            $coachingMode,
            $idempotencyKey,
        ): array {
            $context = $this->authority->require($learner);
            $membershipId = (string) $context->membershipId;
            $fingerprint = $this->commands->fingerprint([
                'coaching_mode' => $coachingMode,
                'mode' => 'friend',
                'persona_key' => $personaKey,
                'scenario_key' => $scenarioKey,
            ]);
            $replay = $this->commands->replay(
                actor: $learner,
                operation: 'conversation.session.start',
                aggregateType: 'membership',
                aggregateId: $membershipId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
            );

            if ($replay instanceof stdClass) {
                return $this->existing(
                    organizationId: $context->organizationId,
                    membershipId: $membershipId,
                    sessionId: $this->string($replay, 'result_id'),
                    replayed: true,
                );
            }

            $prototype = $this->prototype(
                organizationId: $context->organizationId,
                personaKey: $personaKey,
                scenarioKey: $scenarioKey,
                coachingMode: $coachingMode,
            );
            $now = Date::now()->toImmutable();
            $sessionId = (string) Str::ulid();

            DB::table('conversation_sessions')->insert([
                'id' => $sessionId,
                'organization_id' => $context->organizationId,
                'learner_membership_id' => $membershipId,
                'persona_id' => $prototype->persona_id,
                'persona_version_id' => $prototype->persona_version_id,
                'persona_definition_hash' => $prototype->persona_definition_hash,
                'scenario_id' => $prototype->scenario_id,
                'scenario_version_id' => $prototype->scenario_version_id,
                'scenario_definition_hash' => $prototype->scenario_definition_hash,
                'mode' => 'friend',
                'coaching_mode' => $coachingMode,
                'status' => 'active',
                'started_at' => $now,
                'ended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->commands->record(
                actor: $learner,
                operation: 'conversation.session.start',
                aggregateType: 'membership',
                aggregateId: $membershipId,
                idempotencyKey: $idempotencyKey,
                fingerprint: $fingerprint,
                resultType: 'conversation_session',
                resultId: $sessionId,
                completedAt: $now,
            );

            return $this->existing(
                organizationId: $context->organizationId,
                membershipId: $membershipId,
                sessionId: $sessionId,
                replayed: false,
            );
        }, 3);
    }

    private function prototype(
        string $organizationId,
        string $personaKey,
        string $scenarioKey,
        string $coachingMode,
    ): stdClass {
        $allowedModes = config('conversation.coaching_modes', ['gentle']);

        if (
            $personaKey !== config('conversation.prototype.persona_key')
            || $scenarioKey
                !== config('conversation.prototype.scenario_key')
            || ! is_array($allowedModes)
            || ! in_array($coachingMode, $allowedModes, true)
        ) {
            throw new ConversationOperationRejected;
        }

        $prototype = DB::table('ai_personas as persona')
            ->join('persona_versions as persona_version', function ($join): void {
                $join
                    ->on(
                        'persona_version.organization_id',
                        '=',
                        'persona.organization_id',
                    )
                    ->on(
                        'persona_version.persona_id',
                        '=',
                        'persona.id',
                    );
            })
            ->join('roleplay_scenarios as scenario', function ($join): void {
                $join->on(
                    'scenario.organization_id',
                    '=',
                    'persona.organization_id',
                );
            })
            ->join('scenario_versions as scenario_version', function ($join): void {
                $join
                    ->on(
                        'scenario_version.organization_id',
                        '=',
                        'scenario.organization_id',
                    )
                    ->on(
                        'scenario_version.scenario_id',
                        '=',
                        'scenario.id',
                    );
            })
            ->where('persona.organization_id', $organizationId)
            ->where('persona.key', $personaKey)
            ->where('persona.status', 'development_only')
            ->where('persona_version.review_status', 'synthetic_unreviewed')
            ->where('scenario.key', $scenarioKey)
            ->where('scenario.mode', 'friend')
            ->where('scenario.status', 'development_only')
            ->where('scenario_version.review_status', 'synthetic_unreviewed')
            ->orderByDesc('persona_version.version')
            ->orderByDesc('scenario_version.version')
            ->first([
                'persona.id as persona_id',
                'persona.key as persona_key',
                'persona_version.id as persona_version_id',
                'persona_version.version as persona_version',
                'persona_version.display_name as persona_display_name',
                'persona_version.disclosure as persona_disclosure',
                'persona_version.style_code as persona_style_code',
                'persona_version.definition_hash as persona_definition_hash',
                'persona_version.review_status as persona_review_status',
                'scenario.id as scenario_id',
                'scenario.key as scenario_key',
                'scenario_version.id as scenario_version_id',
                'scenario_version.version as scenario_version',
                'scenario_version.topic_code as scenario_topic_code',
                'scenario_version.response_code as scenario_response_code',
                'scenario_version.definition_hash as scenario_definition_hash',
                'scenario_version.review_status as scenario_review_status',
            ]);

        if (! $prototype instanceof stdClass) {
            throw new ConversationOperationRejected;
        }

        return $prototype;
    }

    /**
     * @return array<string, mixed>
     */
    private function existing(
        string $organizationId,
        string $membershipId,
        string $sessionId,
        bool $replayed,
    ): array {
        $row = DB::table('conversation_sessions as session')
            ->join('ai_personas as persona', function ($join): void {
                $join
                    ->on(
                        'persona.organization_id',
                        '=',
                        'session.organization_id',
                    )
                    ->on('persona.id', '=', 'session.persona_id');
            })
            ->join('persona_versions as persona_version', function ($join): void {
                $join
                    ->on(
                        'persona_version.organization_id',
                        '=',
                        'session.organization_id',
                    )
                    ->on(
                        'persona_version.id',
                        '=',
                        'session.persona_version_id',
                    )
                    ->on(
                        'persona_version.persona_id',
                        '=',
                        'session.persona_id',
                    );
            })
            ->join('roleplay_scenarios as scenario', function ($join): void {
                $join
                    ->on(
                        'scenario.organization_id',
                        '=',
                        'session.organization_id',
                    )
                    ->on('scenario.id', '=', 'session.scenario_id');
            })
            ->join('scenario_versions as scenario_version', function ($join): void {
                $join
                    ->on(
                        'scenario_version.organization_id',
                        '=',
                        'session.organization_id',
                    )
                    ->on(
                        'scenario_version.id',
                        '=',
                        'session.scenario_version_id',
                    )
                    ->on(
                        'scenario_version.scenario_id',
                        '=',
                        'session.scenario_id',
                    );
            })
            ->where('session.organization_id', $organizationId)
            ->where('session.learner_membership_id', $membershipId)
            ->where('session.id', $sessionId)
            ->first([
                'session.id',
                'session.status',
                'session.mode',
                'session.coaching_mode',
                'persona.id as persona_id',
                'persona.key as persona_key',
                'persona_version.id as persona_version_id',
                'persona_version.version as persona_version',
                'persona_version.display_name as persona_display_name',
                'persona_version.disclosure as persona_disclosure',
                'persona_version.definition_hash as persona_definition_hash',
                'persona_version.review_status as persona_review_status',
                'scenario.id as scenario_id',
                'scenario.key as scenario_key',
                'scenario_version.id as scenario_version_id',
                'scenario_version.version as scenario_version',
                'scenario_version.topic_code as scenario_topic_code',
                'scenario_version.definition_hash as scenario_definition_hash',
                'scenario_version.review_status as scenario_review_status',
            ]);

        if (! $row instanceof stdClass) {
            throw new ConversationOperationRejected;
        }

        return [
            'id' => $this->string($row, 'id'),
            'status' => $this->string($row, 'status'),
            'mode' => $this->string($row, 'mode'),
            'coaching_mode' => $this->string($row, 'coaching_mode'),
            'persona' => [
                'id' => $this->string($row, 'persona_id'),
                'key' => $this->string($row, 'persona_key'),
                'version_id' => $this->string(
                    $row,
                    'persona_version_id',
                ),
                'version' => $this->integer($row, 'persona_version'),
                'display_name' => $this->string(
                    $row,
                    'persona_display_name',
                ),
                'disclosure' => $this->string(
                    $row,
                    'persona_disclosure',
                ),
                'definition_hash' => $this->string(
                    $row,
                    'persona_definition_hash',
                ),
                'review_status' => $this->string(
                    $row,
                    'persona_review_status',
                ),
            ],
            'scenario' => [
                'id' => $this->string($row, 'scenario_id'),
                'key' => $this->string($row, 'scenario_key'),
                'version_id' => $this->string(
                    $row,
                    'scenario_version_id',
                ),
                'version' => $this->integer($row, 'scenario_version'),
                'topic_code' => $this->string(
                    $row,
                    'scenario_topic_code',
                ),
                'definition_hash' => $this->string(
                    $row,
                    'scenario_definition_hash',
                ),
                'review_status' => $this->string(
                    $row,
                    'scenario_review_status',
                ),
            ],
            'replayed' => $replayed,
        ];
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new ConversationOperationRejected;
        }

        return $value;
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        if (
            (! is_int($value) && ! is_string($value))
            || ! is_numeric($value)
        ) {
            throw new ConversationOperationRejected;
        }

        return (int) $value;
    }
}
