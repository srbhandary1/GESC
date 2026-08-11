<?php

declare(strict_types=1);

namespace App\Operator;

use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

/**
 * Versioned, tenant-bound inventory for learner privacy operations.
 *
 * Secret-bearing values, raw audio, transcripts, and cryptographic digests are
 * deliberately excluded. Every group is still reported when it has no rows so
 * an export is a complete statement of the classes inspected.
 */
final class LearnerPrivacyInventory
{
    public const string VERSION = 'learner-privacy-v3';

    /**
     * Membership-linked governance records are not creatable by an authorized
     * learner flow. They remain inventoried because the foundation schema can
     * still link a membership to their authoritative actor fields.
     *
     * @var list<array{table: string, membership_column: string, id_column?: string}>
     */
    private const array GOVERNANCE_ACTOR_LINKS = [
        [
            'table' => 'cohort_trainers',
            'membership_column' => 'trainer_membership_id',
            'id_column' => 'cohort_id',
        ],
        ['table' => 'programs', 'membership_column' => 'created_by_membership_id'],
        ['table' => 'curriculum_versions', 'membership_column' => 'created_by_membership_id'],
        ['table' => 'content_revisions', 'membership_column' => 'authored_by_membership_id'],
        ['table' => 'localized_variants', 'membership_column' => 'translated_by_membership_id'],
        ['table' => 'curriculum_graph_revisions', 'membership_column' => 'authored_by_membership_id'],
        ['table' => 'authority_appointments', 'membership_column' => 'appointee_membership_id'],
        ['table' => 'authority_appointments', 'membership_column' => 'appointed_by_membership_id'],
        ['table' => 'authority_appointment_events', 'membership_column' => 'actor_membership_id'],
        ['table' => 'content_reviews', 'membership_column' => 'reviewer_membership_id'],
        ['table' => 'curriculum_graph_reviews', 'membership_column' => 'reviewer_membership_id'],
        ['table' => 'media_assets', 'membership_column' => 'uploaded_by_membership_id'],
        ['table' => 'media_rights_revisions', 'membership_column' => 'confirmed_by_membership_id'],
        ['table' => 'curriculum_publications', 'membership_column' => 'created_by_membership_id'],
        [
            'table' => 'publication_graph_components',
            'membership_column' => 'reviewer_membership_id',
            'id_column' => 'curriculum_publication_id',
        ],
        ['table' => 'publication_events', 'membership_column' => 'actor_membership_id'],
        ['table' => 'publication_commands', 'membership_column' => 'actor_membership_id'],
        ['table' => 'curriculum_share_grants', 'membership_column' => 'issued_by_membership_id'],
        ['table' => 'curriculum_share_grant_events', 'membership_column' => 'actor_membership_id'],
        ['table' => 'curriculum_clones', 'membership_column' => 'cloned_by_membership_id'],
        ['table' => 'privacy_export_requests', 'membership_column' => 'requested_by_membership_id'],
        ['table' => 'privacy_erasure_requests', 'membership_column' => 'requested_by_membership_id'],
    ];

    /**
     * @var list<array{table: string, user_column: string}>
     */
    private const array GOVERNANCE_USER_LINKS = [
        ['table' => 'authority_appointments', 'user_column' => 'appointee_user_id'],
        ['table' => 'publication_events', 'user_column' => 'platform_actor_user_id'],
        ['table' => 'publication_commands', 'user_column' => 'actor_user_id'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function export(string $organizationId, stdClass $membership): array
    {
        $membershipId = (string) $membership->id;
        $userId = (string) $membership->user_id;
        $groups = $this->groups($organizationId, $membershipId, $userId);

        return [
            'inventory_version' => self::VERSION,
            'scope' => [
                'kind' => 'organization_membership',
                'organization_id' => $organizationId,
                'membership_id' => $membershipId,
            ],
            'excluded_sensitive_values' => [
                'authentication_credentials',
                'cryptographic_digests',
                'raw_audio',
                'raw_transcripts',
                'provider_secret_payloads',
            ],
            'record_groups' => $groups,
        ];
    }

    /**
     * Snapshot the deterministic record-class outcomes before erasure mutates
     * deletable or redactable records.
     *
     * @return array<string, mixed>
     */
    public function erasureOutcome(
        string $organizationId,
        stdClass $membership,
    ): array {
        $groups = $this->groups(
            $organizationId,
            (string) $membership->id,
            (string) $membership->user_id,
        );

        return [
            'inventory_version' => self::VERSION,
            'scope' => [
                'kind' => 'organization_membership',
                'organization_id' => $organizationId,
                'membership_id' => (string) $membership->id,
            ],
            'record_outcomes' => array_map(
                static fn (array $group): array => [
                    'record_class' => $group['record_class'],
                    'record_count' => $group['record_count'],
                    'action' => $group['erasure_disposition'],
                    'retention_basis' => $group['retention_basis'],
                ],
                $groups,
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function groups(
        string $organizationId,
        string $membershipId,
        string $userId,
    ): array {
        $identityDigests = Schema::hasTable('user_identities')
            ? DB::table('user_identities')
                ->where('user_id', $userId)
                ->pluck('identifier_digest')
                ->filter(static fn (mixed $value): bool => is_string($value))
                ->values()
                ->all()
            : [];
        $mobileFamilyIds = $this->stringIds(
            DB::table('mobile_token_families')->where('user_id', $userId),
        );
        $oauthAccessTokenIds = Schema::hasTable('oauth_access_tokens')
            ? DB::table('oauth_access_tokens')
                ->where('user_id', $userId)
                ->pluck('id')
                ->filter(static fn (mixed $value): bool => is_string($value))
                ->values()
                ->all()
            : [];
        $privilegedRecoveryCaseIds = Schema::hasTable('privileged_password_recovery_cases')
            ? DB::table('privileged_password_recovery_cases')
                ->where('target_user_id', $userId)
                ->pluck('id')
                ->filter(static fn (mixed $value): bool => is_string($value))
                ->values()
                ->all()
            : [];
        $groups = [
            $this->group(
                'users',
                'global_identity',
                'included_safe_fields',
                'retain_global_identity_authority',
                'A user is a global identity that can be shared by memberships in other organizations.',
                $this->rows(
                    DB::table('users')->where('id', $userId),
                    [
                        'id',
                        'username',
                        'display_name',
                        'status',
                        'credential_version',
                        'username_established_at',
                        'credential_cutover_at',
                        'created_at',
                        'updated_at',
                    ],
                ),
            ),
            $this->group(
                'user_identities',
                'global_identity',
                'included_safe_fields_without_identifier_digest',
                'retain_global_identity_authority',
                'Login identifiers are global authority records; secret digests are never exported.',
                $this->rows(
                    DB::table('user_identities')->where('user_id', $userId),
                    ['id', 'type', 'display_hint', 'status', 'verified_at', 'created_at', 'updated_at'],
                ),
            ),
            $this->countOnlyGroup(
                'sessions',
                'global_authentication',
                'count_only_secret_bearing',
                'revoke_and_delete_session_storage',
                'Session storage is a revocable credential, not learner evidence.',
                Schema::hasTable('sessions')
                    ? DB::table('sessions')->where('user_id', $userId)->count()
                    : 0,
            ),
            $this->group(
                'authentication_sessions',
                'global_authentication',
                'included_safe_fields_without_storage_secret',
                'revoke_and_retain_security_evidence',
                'Session lifecycle metadata is retained for security accountability.',
                $this->rows(
                    DB::table('authentication_sessions')->where('user_id', $userId),
                    [
                        'id',
                        'is_privileged',
                        'started_at',
                        'last_seen_at',
                        'idle_expires_at',
                        'absolute_expires_at',
                        'revoked_at',
                        'revocation_reason',
                    ],
                ),
            ),
            $this->countOnlyGroup(
                'password_credentials',
                'global_authentication',
                'count_only_secret_bearing',
                'retain_global_security_authority',
                'Password hashes and hashing metadata are authentication credentials and are never exported.',
                Schema::hasTable('password_credentials')
                    ? DB::table('password_credentials')->where('user_id', $userId)->count()
                    : 0,
            ),
            $this->countOnlyGroup(
                'password_recovery_challenges',
                'global_authentication',
                'count_only_secret_bearing',
                'retain_until_security_expiry',
                'Lookup and reset-token digests are secret-bearing short-lived security records.',
                Schema::hasTable('password_recovery_challenges')
                    ? DB::table('password_recovery_challenges')->where('user_id', $userId)->count()
                    : 0,
            ),
            $this->group(
                'privileged_password_recovery_cases',
                'global_authentication',
                'included_safe_fields_without_external_evidence',
                'retain_privileged_recovery_accountability',
                'External evidence references and digests are excluded; safe case lifecycle is retained.',
                Schema::hasTable('privileged_password_recovery_cases')
                    ? $this->rows(
                        DB::table('privileged_password_recovery_cases')->where('target_user_id', $userId),
                        [
                            'id',
                            'reason_key',
                            'status',
                            'version',
                            'requested_at',
                            'not_before_at',
                            'completed_at',
                            'expires_at',
                            'created_at',
                            'updated_at',
                        ],
                    )
                    : [],
            ),
            $this->countOnlyGroup(
                'privileged_password_recovery_approvals',
                'global_authentication',
                'count_only_security_authority',
                'retain_privileged_recovery_accountability',
                'Approval actor and evidence digests remain restricted platform security evidence.',
                $privilegedRecoveryCaseIds === []
                    || ! Schema::hasTable('privileged_password_recovery_approvals')
                    ? 0
                    : DB::table('privileged_password_recovery_approvals')
                        ->whereIn('case_id', $privilegedRecoveryCaseIds)
                        ->count(),
            ),
            $this->group(
                'password_security_events',
                'global_authentication',
                'included_safe_fields_without_request_digest',
                'retain_security_accountability',
                'Content-free password lifecycle evidence is retained; request digests are excluded.',
                Schema::hasTable('password_security_events')
                    ? $this->rows(
                        DB::table('password_security_events')->where('user_id', $userId),
                        ['id', 'event_type', 'outcome', 'occurred_at', 'created_at', 'updated_at'],
                    )
                    : [],
            ),
            $this->countOnlyGroup(
                'otp_challenges',
                'global_authentication',
                'count_only_secret_bearing',
                'retain_until_security_expiry',
                'OTP challenge digests and codes are secret-bearing short-lived security records.',
                $identityDigests === [] || ! Schema::hasTable('otp_challenges')
                    ? 0
                    : DB::table('otp_challenges')
                        ->whereIn('destination_digest', $identityDigests)
                        ->count(),
            ),
            $this->countOnlyGroup(
                'oauth_auth_codes',
                'global_authentication',
                'count_only_secret_bearing',
                'revoke_and_retain_security_evidence',
                'Authorization codes are credential material and are not exported.',
                Schema::hasTable('oauth_auth_codes')
                    ? DB::table('oauth_auth_codes')->where('user_id', $userId)->count()
                    : 0,
            ),
            $this->countOnlyGroup(
                'oauth_access_tokens',
                'global_authentication',
                'count_only_secret_bearing',
                'revoke_and_retain_security_evidence',
                'Access-token identifiers and scopes are security records and are not exported.',
                count($oauthAccessTokenIds),
            ),
            $this->countOnlyGroup(
                'oauth_refresh_tokens',
                'global_authentication',
                'count_only_secret_bearing',
                'revoke_and_retain_security_evidence',
                'Refresh-token identifiers are credential material and are not exported.',
                $oauthAccessTokenIds === [] || ! Schema::hasTable('oauth_refresh_tokens')
                    ? 0
                    : DB::table('oauth_refresh_tokens')
                        ->whereIn('access_token_id', $oauthAccessTokenIds)
                        ->count(),
            ),
            $this->group(
                'mobile_token_families',
                'global_authentication',
                'included_safe_fields_without_installation_or_other_tenant_identifiers',
                'revoke_and_retain_security_evidence',
                'Mobile credential-family lifecycle metadata is retained for reuse detection.',
                $this->mobileFamilyRows(
                    $organizationId,
                    $membershipId,
                    $userId,
                ),
            ),
            $this->countOnlyGroup(
                'mobile_refresh_tokens',
                'global_authentication',
                'count_only_secret_bearing',
                'revoke_and_retain_security_evidence',
                'Mobile refresh-token digests and Passport identifiers are not exported.',
                $mobileFamilyIds === [] || ! Schema::hasTable('mobile_refresh_tokens')
                    ? 0
                    : DB::table('mobile_refresh_tokens')
                        ->whereIn('family_id', $mobileFamilyIds)
                        ->count(),
            ),
            $this->group(
                'privileged_mfa_factors',
                'global_authentication',
                'included_safe_fields_without_factor_secret',
                'retain_global_security_authority',
                'MFA may protect a role in another organization; encrypted secrets are excluded.',
                $this->rows(
                    DB::table('privileged_mfa_factors')->where('user_id', $userId),
                    ['id', 'factor_type', 'status', 'confirmed_at', 'revoked_at', 'created_at', 'updated_at'],
                ),
            ),
            $this->countOnlyGroup(
                'privileged_mfa_challenges',
                'global_authentication',
                'count_only_secret_bearing',
                'retain_until_security_expiry',
                'MFA challenge digests are secret-bearing security evidence.',
                Schema::hasTable('privileged_mfa_challenges')
                    ? DB::table('privileged_mfa_challenges')->where('user_id', $userId)->count()
                    : 0,
            ),
            $this->countOnlyGroup(
                'privileged_mfa_recovery_codes',
                'global_authentication',
                'count_only_secret_bearing',
                'retain_global_security_authority',
                'Recovery-code digests may protect another organization membership.',
                Schema::hasTable('privileged_mfa_recovery_codes')
                    ? DB::table('privileged_mfa_recovery_codes')->where('user_id', $userId)->count()
                    : 0,
            ),
            $this->group(
                'memberships',
                'organization_membership',
                'included_safe_fields',
                'end_membership_retain_authority',
                'The membership row remains the tenant-ownership anchor for retained evidence.',
                $this->rows(
                    $this->tenantMembershipQuery('memberships', $organizationId, 'id', $membershipId),
                    ['id', 'membership_type', 'status', 'joined_at', 'ended_at', 'created_at', 'updated_at'],
                ),
            ),
            $this->group(
                'membership_roles',
                'organization_membership',
                'included_safe_fields',
                'delete_authorization_links',
                'Role assignments are mutable authorization links, not authoritative learner evidence.',
                $this->membershipRoleRows($organizationId, $membershipId),
            ),
            $this->group(
                'institution_invites',
                'organization_membership',
                'included_safe_fields_without_invite_digest',
                'retain_tenant_access_evidence',
                'Accepted-invite evidence supports tenant access accountability.',
                $this->rows(
                    DB::table('institution_invites')
                        ->where('organization_id', $organizationId)
                        ->where('accepted_membership_id', $membershipId),
                    ['id', 'membership_type', 'status', 'expires_at', 'accepted_at', 'created_at', 'updated_at'],
                ),
            ),
            $this->group(
                'enrollments',
                'organization_membership',
                'included_safe_fields',
                'withdraw_open_enrollments_retain_pins',
                'Enrollment and curriculum pins anchor authoritative learning evidence.',
                $this->rows(
                    $this->tenantMembershipQuery('enrollments', $organizationId, 'learner_membership_id', $membershipId),
                    [
                        'id',
                        'cohort_id',
                        'status',
                        'version',
                        'curriculum_owner_organization_id',
                        'curriculum_version_id',
                        'curriculum_publication_id',
                        'curriculum_share_grant_id',
                        'enrolled_at',
                        'activated_at',
                        'ended_at',
                        'created_at',
                        'updated_at',
                    ],
                ),
            ),
            $this->directGroup(
                'learner_profiles',
                $organizationId,
                $membershipId,
                ['id', 'adult_confirmed_at', 'onboarding_completed_at', 'created_at'],
                'retain_immutable_onboarding_evidence',
                'The profile is append-only evidence of adult confirmation and completed onboarding.',
            ),
            $this->directGroup(
                'learner_goals',
                $organizationId,
                $membershipId,
                ['id', 'learner_profile_id', 'goal_key', 'selected_at', 'created_at'],
                'retain_immutable_onboarding_evidence',
                'The selected goal is append-only onboarding evidence under the learning authority trigger.',
            ),
            $this->directGroup(
                'learner_profile_current_goals',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'learner_profile_id',
                    'goal_key',
                    'version',
                    'corrected_at',
                    'created_at',
                    'updated_at',
                ],
                'retain_governed_onboarding_projection',
                'The current goal projection is governed personalization state backed by retained correction evidence.',
            ),
            $this->directGroup(
                'learner_goal_corrections',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'learner_profile_id',
                    'actor_membership_id',
                    'command_id',
                    'previous_goal_key',
                    'successor_goal_key',
                    'effective_at',
                    'created_at',
                ],
                'retain_immutable_onboarding_correction_evidence',
                'Attributed goal corrections are append-only authoritative onboarding history.',
            ),
            $this->directGroup(
                'organization_administration_events',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'actor_membership_id',
                    'event_type',
                    'subject_learner_profile_id',
                    'subject_enrollment_id',
                    'command_id',
                    'reason_key',
                    'effective_at',
                    'created_at',
                ],
                'retain_time_limited_administration_accountability',
                'Learner administration events retain only opaque identifiers and governed lifecycle keys.',
                'id',
                'subject_membership_id',
            ),
            $this->directGroup(
                'learner_attempts',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'enrollment_id',
                    'curriculum_mission_id',
                    'learning_objective_id',
                    'rule_version',
                    'attempt_number',
                    'status',
                    'started_at',
                    'submitted_at',
                    'created_at',
                    'updated_at',
                ],
                'retain_immutable_learning_evidence',
                'Attempts are immutable/versioned evidence for deterministic marks and progression.',
            ),
            $this->directGroup(
                'item_responses',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'learner_attempt_id',
                    'learning_item_id',
                    'response_number',
                    'check_id',
                    'selected_option_id',
                    'check_correct',
                    'client_capture_id',
                    'capture_mime_type',
                    'capture_byte_size',
                    'capture_duration_ms',
                    'captured_at',
                    'created_at',
                ],
                'retain_immutable_learning_evidence',
                'Submitted item responses are immutable authoritative evidence; no raw local capture is stored.',
            ),
            $this->directGroup(
                'attempt_submissions',
                $organizationId,
                $membershipId,
                [
                    'learner_attempt_id',
                    'attempt_number',
                    'knowledge_score',
                    'knowledge_check_correct',
                    'local_capture_receipt_present',
                    'knowledge_state',
                    'spoken_state',
                    'result_key',
                    'rule_version',
                    'submitted_at',
                    'created_at',
                ],
                'retain_immutable_learning_evidence',
                'Submissions are authoritative marks and cannot be erased without breaking deterministic records.',
                'learner_attempt_id',
            ),
            $this->directGroup(
                'mastery_states',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'enrollment_id',
                    'learning_objective_id',
                    'knowledge_state',
                    'spoken_state',
                    'best_knowledge_score',
                    'evidence_attempt_id',
                    'rule_version',
                    'mastered_at',
                    'created_at',
                    'updated_at',
                ],
                'retain_deterministic_progression_authority',
                'Mastery is a deterministic projection tied to immutable evidence.',
            ),
            $this->directGroup(
                'review_schedules',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'enrollment_id',
                    'learning_objective_id',
                    'source_attempt_id',
                    'schedule_kind',
                    'rule_version',
                    'timezone',
                    'due_at',
                    'created_at',
                ],
                'retain_deterministic_progression_authority',
                'Review scheduling is deterministically derived from retained attempt evidence.',
            ),
            $this->actorCommandGroup(
                'learning_commands',
                $organizationId,
                $membershipId,
                'retain_idempotency_authority',
            ),
            $this->directGroup(
                'conversation_sessions',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'persona_id',
                    'persona_version_id',
                    'scenario_id',
                    'scenario_version_id',
                    'mode',
                    'coaching_mode',
                    'ui_locale',
                    'status',
                    'started_at',
                    'ended_at',
                    'created_at',
                    'updated_at',
                ],
                'retain_immutable_conversation_evidence',
                'Session snapshots are immutable and contain no message bodies.',
            ),
            $this->directGroup(
                'conversation_turns',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'conversation_session_id',
                    'turn_number',
                    'status',
                    'failed_stage',
                    'failure_code',
                    'accepted_at',
                    'completed_at',
                    'created_at',
                    'updated_at',
                ],
                'retain_immutable_conversation_evidence',
                'Turn state is immutable; utterance, transcript, and reply digests are excluded.',
            ),
            $this->directGroup(
                'conversation_turn_events',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'conversation_turn_id',
                    'conversation_session_id',
                    'sequence',
                    'state',
                    'event_code',
                    'provider_stage',
                    'provider_attempt',
                    'recorded_at',
                ],
                'retain_immutable_conversation_evidence',
                'Turn lifecycle events support deterministic replay and failure diagnosis.',
            ),
            $this->directGroup(
                'ai_cost_ledger',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'conversation_turn_id',
                    'conversation_session_id',
                    'provider_stage',
                    'provider_key',
                    'model_version',
                    'provider_attempt',
                    'latency_ms',
                    'input_units',
                    'output_units',
                    'estimated_cost_microunits',
                    'result_status',
                    'recorded_at',
                ],
                'retain_usage_authority',
                'Usage records contain safe metadata only and support cost/accountability controls.',
            ),
            $this->actorCommandGroup(
                'conversation_commands',
                $organizationId,
                $membershipId,
                'retain_idempotency_authority',
            ),
            $this->directGroup(
                'correction_suggestions',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'conversation_turn_id',
                    'conversation_session_id',
                    'coaching_mode',
                    'error_category',
                    'retry_recommended',
                    'safety_state',
                    'prompt_version_id',
                    'recorded_at',
                ],
                'retain_derived_feedback_evidence',
                'Feedback evidence is retained without correction or explanation digests.',
            ),
            $this->directGroup(
                'learner_error_patterns',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'error_category',
                    'observation_count',
                    'first_observed_at',
                    'last_observed_at',
                ],
                'delete_derived_personalization_projection',
                'The aggregate is reconstructable personalization state, not authoritative evidence.',
            ),
            $this->directGroup(
                'learner_memory_facts',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'purpose',
                    'source',
                    'status',
                    'fact_text',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ],
                'redact_fact_text_and_mark_deleted',
                'Raw learner-authored memory is removable; only digest lifecycle evidence remains.',
            ),
            $this->directGroup(
                'memory_events',
                $organizationId,
                $membershipId,
                ['id', 'memory_fact_id', 'event_code', 'recorded_at'],
                'retain_immutable_digest_evidence',
                'Memory lifecycle events are immutable and raw fact values are excluded.',
            ),
            $this->directGroup(
                'conversation_session_stage_events',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'conversation_session_id',
                    'from_stage',
                    'to_stage',
                    'sequence',
                    'created_at',
                ],
                'retain_immutable_conversation_evidence',
                'Roleplay stage transitions are immutable evidence without message content.',
            ),
            $this->directGroup(
                'roleplay_feedback_evidence',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'conversation_session_id',
                    'scenario_id',
                    'scenario_version_id',
                    'scenario_objective_id',
                    'completion_state',
                    'created_at',
                ],
                'retain_immutable_conversation_evidence',
                'Objective evidence is immutable; evidence digests are excluded.',
            ),
            $this->directGroup(
                'voice_consent_events',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'purpose',
                    'policy_version',
                    'notice_revision',
                    'requested_locale',
                    'resolved_locale',
                    'state',
                    'effective_at',
                    'created_at',
                ],
                'retain_consent_evidence_per_adr_0007',
                'Consent grants and withdrawals are retained as time-limited accountability evidence.',
            ),
            $this->directGroup(
                'voice_recordings',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'conversation_session_id',
                    'upload_consent_event_id',
                    'provider_consent_event_id',
                    'status',
                    'byte_size',
                    'duration_ms',
                    'mime_type',
                    'codec',
                    'sample_rate_hz',
                    'channels',
                    'transcript_confidence_bps',
                    'transcript_action',
                    'source_delete_by',
                    'source_deleted_at',
                    'transcript_expires_at',
                    'completed_at',
                    'created_at',
                    'updated_at',
                ],
                'delete_source_object_and_transcript_retain_receipt',
                'ADR-0007 requires source/transcript deletion while retaining safe lifecycle metadata.',
            ),
            $this->directGroup(
                'voice_provider_runs',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'voice_recording_id',
                    'conversation_session_id',
                    'provider_stage',
                    'provider_key',
                    'model_version',
                    'provider_attempt',
                    'result_status',
                    'safe_failure_code',
                    'latency_ms',
                    'input_units',
                    'output_units',
                    'estimated_cost_microunits',
                    'recorded_at',
                ],
                'retain_provider_accountability_metadata',
                'Provider-run metadata contains no audio or transcript content.',
            ),
            $this->directGroup(
                'voice_deletion_requests',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'voice_recording_id',
                    'reason',
                    'status',
                    'source_deleted',
                    'transcript_deleted',
                    'provider_deletion_status',
                    'requested_at',
                    'completed_at',
                ],
                'retain_deletion_receipt',
                'Deletion receipts prove source, transcript, and provider disposition.',
            ),
            $this->voiceObjectGroup($organizationId, $membershipId),
            $this->directGroup(
                'privacy_export_requests',
                $organizationId,
                $membershipId,
                ['id', 'status', 'requested_at', 'completed_at', 'created_at', 'updated_at'],
                'redact_export_payload_retain_receipt',
                'The request receipt is retained while the stored personal-data payload is redacted.',
                'id',
                'target_membership_id',
            ),
            $this->directGroup(
                'privacy_erasure_requests',
                $organizationId,
                $membershipId,
                [
                    'id',
                    'status',
                    'voice_recording_count',
                    'voice_deletion_completed_count',
                    'requested_at',
                    'completed_at',
                    'created_at',
                    'updated_at',
                ],
                'retain_privacy_operation_receipt',
                'Erasure receipts are accountability records and contain no raw learner content.',
                'id',
                'target_membership_id',
            ),
            $this->privacyVoiceLinkGroup($organizationId, $membershipId),
        ];

        foreach (self::GOVERNANCE_ACTOR_LINKS as $definition) {
            $idColumn = $definition['id_column'] ?? 'id';
            $groups[] = $this->group(
                $definition['table'].'.'.$definition['membership_column'],
                'organization_actor_link',
                'included_record_identifiers_only',
                'retain_governed_authority',
                'The record is governed or immutable authority; learner flows cannot create this actor link.',
                $this->rows(
                    $this->tenantMembershipQuery(
                        $definition['table'],
                        $organizationId,
                        $definition['membership_column'],
                        $membershipId,
                    ),
                    [$idColumn],
                    $idColumn,
                ),
            );
        }

        foreach (self::GOVERNANCE_USER_LINKS as $definition) {
            $groups[] = $this->group(
                $definition['table'].'.'.$definition['user_column'],
                'global_identity',
                'included_record_identifiers_only',
                'retain_governed_authority',
                'The record is governed or immutable authority linked through the global user identity.',
                $this->rows(
                    DB::table($definition['table'])
                        ->where('organization_id', $organizationId)
                        ->where($definition['user_column'], $userId),
                    ['id'],
                ),
            );
        }

        $subjectIds = $this->subjectIds($groups);
        $auditRows = Schema::hasTable('audit_events')
            ? $this->rows(
                DB::table('audit_events')
                    ->where(function (Builder $scope) use (
                        $organizationId,
                        $membershipId,
                        $subjectIds,
                        $userId,
                    ): void {
                        $scope
                            ->where(function (Builder $tenant) use (
                                $organizationId,
                                $membershipId,
                                $subjectIds,
                                $userId,
                            ): void {
                                $tenant
                                    ->where('organization_id', $organizationId)
                                    ->where(function (Builder $related) use (
                                        $membershipId,
                                        $subjectIds,
                                        $userId,
                                    ): void {
                                        $related
                                            ->where('actor_user_id', $userId)
                                            ->orWhere(
                                                'actor_membership_id',
                                                $membershipId,
                                            );

                                        if ($subjectIds !== []) {
                                            $related->orWhereIn(
                                                'subject_id',
                                                $subjectIds,
                                            );
                                        }
                                    });
                            })
                            ->orWhere(function (Builder $authentication) use (
                                $userId,
                            ): void {
                                $authentication
                                    ->where('scope_kind', 'platform')
                                    ->whereNull('organization_id')
                                    ->where('actor_user_id', $userId)
                                    ->where(
                                        'action',
                                        'like',
                                        'authentication.%',
                                    );
                            });
                    }),
                [
                    'id',
                    'scope_kind',
                    'action',
                    'subject_type',
                    'subject_id',
                    'occurred_at',
                ],
            )
            : [];
        $groups[] = $this->group(
            'audit_events',
            'organization_accountability',
            'included_safe_fields',
            'retain_append_only_audit_evidence',
            'Audit events are append-only accountability authority and contain safe identifiers only.',
            $auditRows,
        );

        $outboxRows = $subjectIds === [] || ! Schema::hasTable('outbox_events')
            ? []
            : $this->rows(
                DB::table('outbox_events')
                    ->where('organization_id', $organizationId)
                    ->whereIn('aggregate_id', $subjectIds),
                [
                    'id',
                    'scope_kind',
                    'event_type',
                    'aggregate_type',
                    'aggregate_id',
                    'occurred_at',
                    'available_at',
                    'published_at',
                    'attempts',
                    'last_error_code',
                    'dead_lettered_at',
                    'dead_letter_reason_code',
                ],
            );
        $groups[] = $this->group(
            'outbox_events',
            'organization_accountability',
            'included_safe_fields_without_payload',
            'retain_delivery_and_idempotency_evidence',
            'Outbox records preserve durable work and delivery state; payloads are excluded.',
            $outboxRows,
        );
        $outboxIds = array_map(
            static fn (array $row): string => (string) $row['record_id'],
            $outboxRows,
        );
        $groups[] = $this->group(
            'processed_events',
            'organization_accountability',
            'included_safe_fields',
            'retain_delivery_and_idempotency_evidence',
            'Consumer receipts prevent replay of durable operations.',
            $outboxIds === [] || ! Schema::hasTable('processed_events')
                ? []
                : $this->rows(
                    DB::table('processed_events')->whereIn('outbox_event_id', $outboxIds),
                    ['id', 'outbox_event_id', 'consumer_key', 'processed_at'],
                ),
        );
        $groups[] = $this->countOnlyGroup(
            'client_offline_records',
            'client_device',
            'not_server_enumerable',
            'revoke_access_and_require_client_purge',
            'Encrypted offline records live only on the learner device and are purged by the client authentication lifecycle.',
            0,
        );

        return $groups;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function directGroup(
        string $table,
        string $organizationId,
        string $membershipId,
        array $columns,
        string $erasureDisposition,
        string $retentionBasis,
        string $idColumn = 'id',
        string $membershipColumn = 'learner_membership_id',
    ): array {
        return $this->group(
            $table,
            'organization_membership',
            'included_safe_fields',
            $erasureDisposition,
            $retentionBasis,
            $this->rows(
                $this->tenantMembershipQuery(
                    $table,
                    $organizationId,
                    $membershipColumn,
                    $membershipId,
                ),
                $columns,
                $idColumn,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function actorCommandGroup(
        string $table,
        string $organizationId,
        string $membershipId,
        string $erasureDisposition,
    ): array {
        return $this->group(
            $table,
            'organization_membership',
            'included_safe_fields_without_fingerprints_or_idempotency_keys',
            $erasureDisposition,
            'Command receipts prevent replay and preserve authoritative operation results.',
            $this->rows(
                $this->tenantMembershipQuery(
                    $table,
                    $organizationId,
                    'actor_membership_id',
                    $membershipId,
                ),
                [
                    'id',
                    'operation',
                    'aggregate_type',
                    'aggregate_id',
                    'result_type',
                    'result_id',
                    'completed_at',
                    'created_at',
                ],
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function voiceObjectGroup(
        string $organizationId,
        string $membershipId,
    ): array {
        $rows = [];

        if (Schema::hasTable('voice_recordings')) {
            $recordings = DB::table('voice_recordings')
                ->where('organization_id', $organizationId)
                ->where('learner_membership_id', $membershipId)
                ->orderBy('id')
                ->get(['id', 'source_object_key', 'source_deleted_at']);

            foreach ($recordings as $recording) {
                $rows[] = [
                    'record_id' => (string) $recording->id,
                    'attributes' => [
                        'object_present' => is_string($recording->source_object_key)
                            && $recording->source_object_key !== '',
                        'source_deleted_at' => $this->value(
                            $recording->source_deleted_at,
                        ),
                    ],
                ];
            }
        }

        return $this->group(
            'voice_source_objects',
            'external_object',
            'included_state_without_object_key_or_audio',
            'delete_external_object_and_reconcile_receipt',
            'Raw audio is deleted through durable ADR-0007 deletion intents; object keys are never exported.',
            $rows,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function privacyVoiceLinkGroup(
        string $organizationId,
        string $membershipId,
    ): array {
        if (! Schema::hasTable('privacy_erasure_voice_deletions')) {
            return $this->group(
                'privacy_erasure_voice_deletions',
                'organization_accountability',
                'included_safe_fields',
                'retain_privacy_operation_receipt',
                'The link proves which durable deletion receipt fulfilled an erasure.',
                [],
            );
        }

        $rows = DB::table('privacy_erasure_voice_deletions as links')
            ->join(
                'privacy_erasure_requests as requests',
                function ($join): void {
                    $join
                        ->on('requests.organization_id', '=', 'links.organization_id')
                        ->on('requests.id', '=', 'links.privacy_erasure_request_id');
                },
            )
            ->where('links.organization_id', $organizationId)
            ->where('requests.target_membership_id', $membershipId)
            ->orderBy('links.id')
            ->get([
                'links.id',
                'links.privacy_erasure_request_id',
                'links.voice_deletion_request_id',
                'links.created_at',
            ]);

        return $this->group(
            'privacy_erasure_voice_deletions',
            'organization_accountability',
            'included_safe_fields',
            'retain_privacy_operation_receipt',
            'The link proves which durable deletion receipt fulfilled an erasure.',
            $this->normalizeRows(array_values($rows->all()), 'id'),
        );
    }

    /**
     * @return list<array{record_id: string, attributes: array<string, mixed>}>
     */
    private function membershipRoleRows(
        string $organizationId,
        string $membershipId,
    ): array {
        if (! Schema::hasTable('membership_roles')) {
            return [];
        }

        $rows = DB::table('membership_roles as assignments')
            ->join('roles', function ($join): void {
                $join
                    ->on('roles.organization_id', '=', 'assignments.organization_id')
                    ->on('roles.id', '=', 'assignments.role_id');
            })
            ->where('assignments.organization_id', $organizationId)
            ->where('assignments.membership_id', $membershipId)
            ->orderBy('assignments.role_id')
            ->get([
                'assignments.role_id',
                'roles.key as role_key',
                'roles.name as role_name',
                'assignments.created_at',
                'assignments.updated_at',
            ]);

        return $this->normalizeRows(array_values($rows->all()), 'role_id');
    }

    /**
     * Global mobile credential families can be selected into another tenant.
     * Export their security lifecycle without disclosing that tenant's IDs.
     *
     * @return list<array{record_id: string, attributes: array<string, mixed>}>
     */
    private function mobileFamilyRows(
        string $organizationId,
        string $membershipId,
        string $userId,
    ): array {
        if (! Schema::hasTable('mobile_token_families')) {
            return [];
        }

        $rows = DB::table('mobile_token_families')
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get([
                'id',
                'client_id',
                'selected_organization_id',
                'selected_membership_id',
                'last_used_at',
                'absolute_expires_at',
                'revoked_at',
                'revocation_reason',
                'created_at',
                'updated_at',
            ]);
        $records = [];

        foreach ($rows as $row) {
            $records[] = [
                'record_id' => (string) $row->id,
                'attributes' => [
                    'client_id' => $this->value($row->client_id),
                    'selected_target_membership' => (string) $row->selected_organization_id === $organizationId
                        && (string) $row->selected_membership_id === $membershipId,
                    'last_used_at' => $this->value($row->last_used_at),
                    'absolute_expires_at' => $this->value($row->absolute_expires_at),
                    'revoked_at' => $this->value($row->revoked_at),
                    'revocation_reason' => $this->value($row->revocation_reason),
                    'created_at' => $this->value($row->created_at),
                    'updated_at' => $this->value($row->updated_at),
                ],
            ];
        }

        return $records;
    }

    private function tenantMembershipQuery(
        string $table,
        string $organizationId,
        string $membershipColumn,
        string $membershipId,
    ): Builder {
        return DB::table($table)
            ->where('organization_id', $organizationId)
            ->where($membershipColumn, $membershipId);
    }

    /**
     * @param  list<string>  $columns
     * @return list<array{record_id: string, attributes: array<string, mixed>}>
     */
    private function rows(
        Builder $query,
        array $columns,
        string $idColumn = 'id',
    ): array {
        $table = $query->from;

        if (! is_string($table)) {
            return [];
        }

        $baseTable = explode(' ', $table)[0];

        if (! Schema::hasTable($baseTable)) {
            return [];
        }

        return $this->normalizeRows(
            array_values($query->orderBy($idColumn)->get($columns)->all()),
            $idColumn,
        );
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<array{record_id: string, attributes: array<string, mixed>}>
     */
    private function normalizeRows(array $rows, string $idColumn): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $values = get_object_vars($row);
            $recordId = $values[$idColumn] ?? null;

            if (! is_string($recordId) || $recordId === '') {
                continue;
            }

            unset($values[$idColumn]);
            $attributes = [];

            foreach ($values as $key => $value) {
                $attributes[$key] = $this->value($value);
            }

            $normalized[] = [
                'record_id' => $recordId,
                'attributes' => $attributes,
            ];
        }

        return $normalized;
    }

    private function value(mixed $value): string|int|float|bool|null
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (
            is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value === null
        ) {
            return $value;
        }

        return (string) $value;
    }

    /**
     * @param  list<array{record_id: string, attributes: array<string, mixed>}>  $records
     * @return array<string, mixed>
     */
    private function group(
        string $recordClass,
        string $scope,
        string $exportDisposition,
        string $erasureDisposition,
        string $retentionBasis,
        array $records,
    ): array {
        return [
            'record_class' => $recordClass,
            'scope' => $scope,
            'record_count' => count($records),
            'export_disposition' => $exportDisposition,
            'erasure_disposition' => $erasureDisposition,
            'retention_basis' => $retentionBasis,
            'records' => $records,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function countOnlyGroup(
        string $recordClass,
        string $scope,
        string $exportDisposition,
        string $erasureDisposition,
        string $retentionBasis,
        int $count,
    ): array {
        return [
            'record_class' => $recordClass,
            'scope' => $scope,
            'record_count' => $count,
            'export_disposition' => $exportDisposition,
            'erasure_disposition' => $erasureDisposition,
            'retention_basis' => $retentionBasis,
            'records' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<string>
     */
    private function subjectIds(array $groups): array
    {
        $ids = [];

        foreach ($groups as $group) {
            $records = $group['records'] ?? [];

            if (! is_array($records)) {
                continue;
            }

            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }

                $id = $record['record_id'] ?? null;

                if (is_string($id) && preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $id) === 1) {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * @return list<string>
     */
    private function stringIds(Builder $query): array
    {
        $table = $query->from;

        if (! is_string($table) || ! Schema::hasTable(explode(' ', $table)[0])) {
            return [];
        }

        return array_values(
            $query
                ->pluck('id')
                ->filter(static fn (mixed $value): bool => is_string($value))
                ->all(),
        );
    }
}
