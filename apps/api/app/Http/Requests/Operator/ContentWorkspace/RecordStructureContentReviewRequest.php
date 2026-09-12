<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator\ContentWorkspace;

use Illuminate\Contracts\Validation\Validator;

final class RecordStructureContentReviewRequest extends StrictContentCommandRequest
{
    protected function payloadRules(): array
    {
        return [
            'curriculum_graph_revision_id' => ['required', 'string', 'ulid'],
            'graph_hash' => ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'],
            'authority_appointment_id' => ['required', 'string', 'ulid'],
            'decision' => ['required', 'string', 'in:approved,rejected'],
            'rationale_key' => ['required', 'string', 'in:meets_standard,structure_revision_required'],
        ];
    }

    protected function allowedPayloadKeys(): array
    {
        return [
            'curriculum_graph_revision_id',
            'graph_hash',
            'authority_appointment_id',
            'decision',
            'rationale_key',
        ];
    }

    protected function validateCommand(Validator $validator): void
    {
        $decision = $this->input('decision');
        $rationale = $this->input('rationale_key');

        if (
            ($decision === 'approved' && $rationale !== 'meets_standard')
            || ($decision === 'rejected' && $rationale !== 'structure_revision_required')
        ) {
            $validator->errors()->add(
                'rationale_key',
                'The rationale is not valid for this structure decision.',
            );
        }
    }
}
