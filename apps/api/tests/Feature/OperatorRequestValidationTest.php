<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\Operator\PublishOperatorCurriculumRequest;
use App\Http\Requests\Operator\RecordEnglishContentReviewRequest;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OperatorRequestValidationTest extends TestCase
{
    public function test_english_review_requires_exact_decision_and_hashes(): void
    {
        $valid = $this->validator(
            RecordEnglishContentReviewRequest::create(
                '/api/v1/operator/content/reviews/english',
                'POST',
                [
                    'source_revision_id' => (string) Str::ulid(),
                    'source_content_hash' => str_repeat('a', 64),
                    'authority_appointment_id' => (string) Str::ulid(),
                    'decision' => 'approved',
                ],
            ),
        );
        self::assertFalse(
            $valid->fails(),
            (string) json_encode($valid->errors()->toArray()),
        );

        self::assertTrue($this->validator(
            RecordEnglishContentReviewRequest::create(
                '/api/v1/operator/content/reviews/english',
                'POST',
                [
                    'source_revision_id' => (string) Str::ulid(),
                    'source_content_hash' => str_repeat('a', 64),
                    'authority_appointment_id' => (string) Str::ulid(),
                    'decision' => 'maybe',
                ],
            ),
        )->fails());

        self::assertTrue($this->validator(
            RecordEnglishContentReviewRequest::create(
                '/api/v1/operator/content/reviews/english',
                'POST',
                [
                    'source_revision_id' => (string) Str::ulid(),
                    'source_content_hash' => str_repeat('a', 64),
                    'authority_appointment_id' => (string) Str::ulid(),
                    'decision' => 'approved',
                    'unexpected' => true,
                ],
            ),
        )->fails());
    }

    public function test_publish_rejects_unsupported_fields(): void
    {
        self::assertFalse($this->validator(
            PublishOperatorCurriculumRequest::create(
                '/api/v1/operator/content/publications',
                'POST',
                [
                    'curriculum_version_id' => (string) Str::ulid(),
                    'expected_head_version' => 0,
                ],
            ),
        )->fails());

        self::assertTrue($this->validator(
            PublishOperatorCurriculumRequest::create(
                '/api/v1/operator/content/publications',
                'POST',
                [
                    'curriculum_version_id' => (string) Str::ulid(),
                    'expected_head_version' => 0,
                    'organization_id' => (string) Str::ulid(),
                ],
            ),
        )->fails());
    }

    private function validator(
        RecordEnglishContentReviewRequest|PublishOperatorCurriculumRequest $request,
    ): ValidatorContract {
        $request->headers->set(
            'Idempotency-Key',
            (string) Str::uuid(),
        );
        $validator = Validator::make(
            $request->validationData(),
            $request->rules(),
        );
        $request->withValidator($validator);

        return $validator;
    }
}
