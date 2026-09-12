<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\MediaLibrary;

use App\Http\Requests\IdempotentRequest;
use App\Media\MediaKindPolicy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

final class MediaCommandRequest extends IdempotentRequest
{
    protected function prepareForValidation(): void
    {
        $values = [];

        foreach ([
            'asset_kind',
            'safe_label',
            'operator_label',
            'reason_key',
            'rationale_key',
            'rights_holder_reference',
            'provenance_reference',
            'rights_basis_key',
            'license_or_contract_reference',
            'retention_policy_key',
            'deletion_constraint_key',
        ] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $values[$field] = trim($value);
            }
        }

        if ($values !== []) {
            $this->merge($values);
        }
    }

    /** @return array<string, list<mixed>> */
    protected function payloadRules(): array
    {
        $ulid = ['required', 'string', 'ulid'];
        $nullableUlid = ['nullable', 'string', 'ulid'];
        $sha256 = ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'];
        $nullableSha256 = ['nullable', 'string', 'regex:/\A[0-9a-f]{64}\z/'];
        $stableKey = ['required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9_.-]*\z/'];

        return match ($this->route()?->getName()) {
            'api.v1.operator.media.uploads.store' => [
                'asset_kind' => ['required', Rule::in(MediaKindPolicy::SUPPORTED)],
                'safe_label' => ['required', 'string', 'max:160', 'not_regex:/[\x00-\x1F\x7F]/u'],
                'total_bytes' => ['required', 'integer', 'min:1', 'max:20000000'],
                'whole_object_hash' => $sha256,
            ],
            'api.v1.operator.media.upload-parts.store' => [
                'part_number' => ['required', 'integer', 'min:1', 'max:1000'],
                'expected_upload_version' => ['required', 'integer', 'min:1'],
                'part_hash' => $sha256,
            ],
            'api.v1.operator.media.upload-finalizations.store',
            'api.v1.operator.media.upload-acceptances.store' => [
                'expected_upload_version' => ['required', 'integer', 'min:1'],
            ],
            'api.v1.operator.media.upload-rejections.store' => [
                'expected_upload_version' => ['required', 'integer', 'min:1'],
                'reason_key' => $stableKey,
            ],
            'api.v1.operator.media.asset-adoptions.store' => [
                'selected_asset_hash' => $sha256,
            ],
            'api.v1.operator.media.metadata-revisions.store' => [
                'expected_head_revision_id' => $nullableUlid,
                'expected_metadata_version' => ['required', 'integer', 'min:0'],
                'operator_label' => ['required', 'string', 'max:160'],
                'transcript' => ['required', 'string', 'max:65536'],
                'text_alternative' => ['nullable', 'string', 'max:8000'],
            ],
            'api.v1.operator.media.metadata-variants.store' => [
                'source_content_hash' => $sha256,
                'expected_head_variant_id' => $nullableUlid,
                'expected_variant_version' => ['required', 'integer', 'min:0'],
                'transcript' => ['required', 'string', 'max:65536'],
                'text_alternative' => ['nullable', 'string', 'max:8000'],
            ],
            'api.v1.operator.media.metadata-reviews.store' => [
                'program_id' => $ulid,
                'metadata_revision_id' => $ulid,
                'source_content_hash' => $sha256,
                'review_kind' => ['required', Rule::in(['academic_en', 'kannada'])],
                'metadata_variant_id' => $nullableUlid,
                'variant_content_hash' => $nullableSha256,
                'decision' => ['required', Rule::in(['approved', 'rejected', 'withdrawn'])],
                'rationale_key' => ['nullable', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9_.-]*\z/'],
                'authority_appointment_id' => $ulid,
            ],
            'api.v1.operator.media.rights-decisions.store' => [
                'action' => ['required', Rule::in(['confirm', 'reject', 'withdraw'])],
                'program_id' => $ulid,
                'media_asset_id' => ['required_if:action,confirm', 'nullable', 'string', 'ulid'],
                'selected_asset_hash' => ['required_if:action,confirm', 'nullable', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
                'parent_revision_id' => $nullableUlid,
                'evidence_hash' => $sha256,
                'rights_holder_reference' => ['required_if:action,confirm', 'nullable', 'string', 'max:120'],
                'provenance_reference' => ['required_if:action,confirm', 'nullable', 'string', 'max:120'],
                'rights_basis_key' => ['required_if:action,confirm', 'nullable', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9_.-]*\z/'],
                'license_or_contract_reference' => ['required', 'string', 'max:240'],
                'permitted_release_channels' => ['required_if:action,confirm', 'nullable', 'array', 'min:1', 'max:4'],
                'permitted_release_channels.*' => ['string', Rule::in(['android', 'web'])],
                'permitted_territories' => ['required_if:action,confirm', 'nullable', 'array', 'min:1', 'max:8'],
                'permitted_territories.*' => ['string', 'max:16', 'regex:/\A[A-Z]{2}\z/'],
                'learner_delivery_allowed' => ['required_if:action,confirm', 'nullable', 'boolean'],
                'sharing_allowed' => ['required_if:action,confirm', 'nullable', 'boolean'],
                'derivative_use_allowed' => ['required_if:action,confirm', 'nullable', 'boolean'],
                'effective_at' => ['required_if:action,confirm', 'nullable', 'date_format:Y-m-d\TH:i:sP'],
                'expires_at' => ['nullable', 'date_format:Y-m-d\TH:i:sP', 'after:effective_at'],
                'retention_policy_key' => ['required_if:action,confirm', 'nullable', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9_.-]*\z/'],
                'deletion_constraint_key' => ['required_if:action,confirm', 'nullable', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9_.-]*\z/'],
                'authority_appointment_id' => $ulid,
            ],
            'api.v1.operator.media.attachments.store' => [
                'program_id' => $ulid,
                'curriculum_version_id' => $ulid,
                'source_revision_id' => $ulid,
                'usage_key' => ['required', Rule::in(MediaKindPolicy::CURRENTLY_ATTACHABLE)],
                'expected_workspace_version' => ['required', 'integer', 'min:1'],
                'expected_lifecycle_version' => ['required', 'integer', 'min:1'],
                'metadata_revision_id' => $ulid,
                'metadata_content_hash' => $sha256,
                'english_review_id' => $ulid,
                'metadata_variant_id' => $ulid,
                'variant_content_hash' => $sha256,
                'kannada_review_id' => $ulid,
                'media_rights_revision_id' => $ulid,
            ],
            'api.v1.operator.media.replacements.store' => [
                'successor_media_asset_id' => $ulid,
                'expected_predecessor_lifecycle_version' => ['required', 'integer', 'min:1'],
                'expected_successor_lifecycle_version' => ['required', 'integer', 'min:1'],
            ],
            'api.v1.operator.media.retirements.store' => [
                'expected_lifecycle_version' => ['required', 'integer', 'min:1'],
                'reason_key' => $stableKey,
            ],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    protected function payloadValidationData(): array
    {
        if (
            $this->route()?->getName()
                === 'api.v1.operator.media.upload-parts.store'
        ) {
            return [
                'part_number' => $this->header('Media-Part-Number'),
                'expected_upload_version' => $this->header(
                    'Media-Upload-Version',
                ),
                'part_hash' => $this->header('Media-Part-SHA256'),
            ];
        }

        return parent::payloadValidationData();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $routeName = $this->route()?->getName();
            $allowed = $this->allowedPayloadKeys($routeName);
            $bodyKeys = array_keys($this->request->all());

            if (array_diff($bodyKeys, $allowed) !== []) {
                $validator->errors()->add(
                    'body',
                    'The request contains unsupported fields.',
                );
            }

            if ($this->query() !== []) {
                $validator->errors()->add(
                    'query',
                    'Command endpoints do not accept query parameters.',
                );
            }

            if ($routeName === 'api.v1.operator.media.upload-parts.store') {
                $contentType = strtolower((string) $this->header('Content-Type'));

                if ($contentType !== 'application/octet-stream') {
                    $validator->errors()->add(
                        'content_type',
                        'Media parts require application/octet-stream.',
                    );
                }
            } elseif (! $this->isJson()) {
                $validator->errors()->add(
                    'content_type',
                    'Media commands require application/json.',
                );
            }

            $reviewKind = $this->input('review_kind');

            if (
                $routeName === 'api.v1.operator.media.metadata-reviews.store'
                && (($reviewKind === 'academic_en'
                        && ($this->input('metadata_variant_id') !== null
                            || $this->input('variant_content_hash') !== null))
                    || ($reviewKind === 'kannada'
                        && (! is_string($this->input('metadata_variant_id'))
                            || ! is_string($this->input('variant_content_hash')))))
            ) {
                $validator->errors()->add(
                    'review_kind',
                    'The review target does not match the review kind.',
                );
            }

            $action = $this->input('action');

            if (
                $routeName === 'api.v1.operator.media.rights-decisions.store'
                && in_array($action, ['reject', 'withdraw'], true)
                && ! is_string($this->input('parent_revision_id'))
            ) {
                $validator->errors()->add(
                    'parent_revision_id',
                    'A parent rights revision is required.',
                );
            }
        });
    }

    /** @return list<string> */
    private function allowedPayloadKeys(?string $routeName): array
    {
        return match ($routeName) {
            'api.v1.operator.media.uploads.store' => [
                'asset_kind', 'safe_label', 'total_bytes', 'whole_object_hash',
            ],
            'api.v1.operator.media.upload-parts.store' => [],
            'api.v1.operator.media.upload-finalizations.store',
            'api.v1.operator.media.upload-acceptances.store' => [
                'expected_upload_version',
            ],
            'api.v1.operator.media.upload-rejections.store' => [
                'expected_upload_version', 'reason_key',
            ],
            'api.v1.operator.media.asset-adoptions.store' => [
                'selected_asset_hash',
            ],
            'api.v1.operator.media.metadata-revisions.store' => [
                'expected_head_revision_id', 'expected_metadata_version',
                'operator_label', 'transcript', 'text_alternative',
            ],
            'api.v1.operator.media.metadata-variants.store' => [
                'source_content_hash', 'expected_head_variant_id',
                'expected_variant_version', 'transcript', 'text_alternative',
            ],
            'api.v1.operator.media.metadata-reviews.store' => [
                'program_id', 'metadata_revision_id', 'source_content_hash',
                'review_kind', 'metadata_variant_id', 'variant_content_hash',
                'decision', 'rationale_key', 'authority_appointment_id',
            ],
            'api.v1.operator.media.rights-decisions.store' => [
                'action', 'program_id', 'media_asset_id', 'selected_asset_hash',
                'parent_revision_id', 'evidence_hash',
                'rights_holder_reference', 'provenance_reference',
                'rights_basis_key', 'license_or_contract_reference',
                'permitted_release_channels', 'permitted_territories',
                'learner_delivery_allowed', 'sharing_allowed',
                'derivative_use_allowed', 'effective_at', 'expires_at',
                'retention_policy_key', 'deletion_constraint_key',
                'authority_appointment_id',
            ],
            'api.v1.operator.media.attachments.store' => [
                'program_id', 'curriculum_version_id', 'source_revision_id',
                'usage_key', 'expected_workspace_version',
                'expected_lifecycle_version', 'metadata_revision_id',
                'metadata_content_hash', 'english_review_id',
                'metadata_variant_id', 'variant_content_hash',
                'kannada_review_id', 'media_rights_revision_id',
            ],
            'api.v1.operator.media.replacements.store' => [
                'successor_media_asset_id',
                'expected_predecessor_lifecycle_version',
                'expected_successor_lifecycle_version',
            ],
            'api.v1.operator.media.retirements.store' => [
                'expected_lifecycle_version', 'reason_key',
            ],
            default => [],
        };
    }
}
