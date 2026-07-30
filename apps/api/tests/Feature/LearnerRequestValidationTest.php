<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\Learner\CompleteOnboardingRequest;
use App\Http\Requests\Learner\RecordAttemptResponseRequest;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LearnerRequestValidationTest extends TestCase
{
    public function test_adult_confirmation_requires_the_exact_json_boolean_true(): void
    {
        self::assertFalse($this->validator(
            CompleteOnboardingRequest::create(
                '/api/v1/me/onboarding',
                'PUT',
                [
                    'adult_confirmed' => true,
                    'goal_key' => 'everyday-conversation',
                ],
            ),
        )->fails());
        self::assertTrue($this->validator(
            CompleteOnboardingRequest::create(
                '/api/v1/me/onboarding',
                'PUT',
                [
                    'adult_confirmed' => 'yes',
                    'goal_key' => 'everyday-conversation',
                ],
            ),
        )->fails());
        self::assertTrue($this->validator(
            CompleteOnboardingRequest::create(
                '/api/v1/me/onboarding',
                'PUT',
                [
                    'adult_confirmed' => 1,
                    'goal_key' => 'everyday-conversation',
                ],
            ),
        )->fails());
    }

    public function test_response_requires_explicit_nullable_receipt_and_exact_boolean_capture(): void
    {
        self::assertTrue($this->validator(
            RecordAttemptResponseRequest::create(
                '/api/v1/attempts/example/responses',
                'POST',
                [
                    'check_id' => 'daily-greeting-check',
                    'selected_option_id' => 'polite-greeting',
                ],
            ),
        )->fails());
        $nullableReceipt = $this->validator(
            RecordAttemptResponseRequest::create(
                '/api/v1/attempts/example/responses',
                'POST',
                [
                    'check_id' => 'daily-greeting-check',
                    'selected_option_id' => 'polite-greeting',
                    'local_recording' => null,
                ],
            ),
        );
        self::assertFalse(
            $nullableReceipt->fails(),
            (string) json_encode($nullableReceipt->errors()->toArray()),
        );
        self::assertTrue($this->validator(
            RecordAttemptResponseRequest::create(
                '/api/v1/attempts/example/responses',
                'POST',
                [
                    'check_id' => 'daily-greeting-check',
                    'selected_option_id' => 'polite-greeting',
                    'local_recording' => [
                        'captured' => '1',
                        'client_capture_id' => (string) Str::uuid(),
                        'mime_type' => 'audio/webm',
                        'byte_size' => 512,
                        'duration_ms' => 1200,
                    ],
                ],
            ),
        )->fails());
        self::assertTrue($this->validator(
            RecordAttemptResponseRequest::create(
                '/api/v1/attempts/example/responses',
                'POST',
                [
                    'check_id' => 'daily-greeting-check',
                    'selected_option_id' => 'polite-greeting',
                    'local_recording' => [
                        'captured' => true,
                        'client_capture_id' => (string) Str::uuid(),
                        'mime_type' => 'audio/webm',
                        'byte_size' => '512',
                        'duration_ms' => '1200',
                    ],
                ],
            ),
        )->fails());
        self::assertTrue($this->validator(
            RecordAttemptResponseRequest::create(
                '/api/v1/attempts/example/responses',
                'POST',
                [
                    'check_id' => 'Daily Greeting Check',
                    'selected_option_id' => str_repeat('a', 101),
                    'local_recording' => null,
                ],
            ),
        )->fails());
        self::assertFalse($this->validator(
            RecordAttemptResponseRequest::create(
                '/api/v1/attempts/example/responses',
                'POST',
                [
                    'check_id' => 'daily-greeting-check',
                    'selected_option_id' => 'polite-greeting',
                    'local_recording' => [
                        'captured' => true,
                        'client_capture_id' => (string) Str::uuid(),
                        'mime_type' => 'audio/webm',
                        'byte_size' => 512,
                        'duration_ms' => 1200,
                    ],
                ],
            ),
        )->fails());
    }

    public function test_response_http_rejects_json_numeric_strings(): void
    {
        Route::post(
            '/api/_test/learner-attempt-response-validation',
            static fn (
                RecordAttemptResponseRequest $request,
            ) => response()->json(['accepted' => true]),
        );

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson(
                '/api/_test/learner-attempt-response-validation',
                [
                    'check_id' => 'daily-greeting-check',
                    'selected_option_id' => 'polite-greeting',
                    'local_recording' => [
                        'captured' => true,
                        'client_capture_id' => (string) Str::uuid(),
                        'mime_type' => 'audio/webm',
                        'byte_size' => '512',
                        'duration_ms' => '1200',
                    ],
                ],
            )
            ->assertUnprocessable();
    }

    private function validator(
        CompleteOnboardingRequest|RecordAttemptResponseRequest $request,
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
