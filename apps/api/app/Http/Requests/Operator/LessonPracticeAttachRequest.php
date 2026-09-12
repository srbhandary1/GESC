<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Validation\Rule;

final class LessonPracticeAttachRequest extends IdempotentRequest
{
    /** @return array<string, list<mixed>> */
    protected function payloadRules(): array
    {
        $ulid = ['required', 'string', 'ulid'];
        $hash = ['required', 'string', 'regex:/\A[0-9a-f]{64}\z/'];

        return [
            'program_id' => $ulid,
            'curriculum_version_id' => $ulid,
            'expected_workspace_version' => ['required', 'integer', 'min:1'],
            'blueprint_content_hash' => $hash,
            'parts' => ['required', 'array', 'size:4'],
            'parts.*.order' => ['required', 'integer', 'min:1', 'max:4'],
            'parts.*.part_id' => ['required', Rule::in(['introduction', 'practice', 'conversation', 'interaction'])],
            'parts.*.asset_id' => $ulid,
            'parts.*.metadata_revision_id' => $ulid,
            'parts.*.metadata_content_hash' => $hash,
            'parts.*.rights_revision_id' => $ulid,
        ];
    }
}
