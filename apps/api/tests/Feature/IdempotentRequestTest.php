<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\IdempotentRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class IdempotentRequestTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validKeys(): iterable
    {
        yield 'uuid' => [(string) Str::uuid()];
        yield 'ulid' => [(string) Str::ulid()];
    }

    #[DataProvider('validKeys')]
    public function test_shared_contract_accepts_only_the_header_key(
        string $idempotencyKey,
    ): void {
        $request = SyntheticIdempotentRequest::create(
            '/synthetic',
            'POST',
            [
                'payload' => 'accepted',
                'idempotency_key' => 'body-must-not-win',
            ],
        );
        $request->headers->set('Idempotency-Key', $idempotencyKey);

        self::assertSame(
            [
                'route_value' => 'synthetic-route',
                'idempotency_key' => $idempotencyKey,
            ],
            $request->validationData(),
        );
        self::assertFalse(Validator::make(
            $request->validationData(),
            $request->rules(),
        )->fails());
    }

    public function test_body_value_cannot_replace_a_missing_or_invalid_header(): void
    {
        $request = SyntheticIdempotentRequest::create(
            '/synthetic',
            'POST',
            [
                'payload' => 'accepted',
                'idempotency_key' => (string) Str::uuid(),
            ],
        );

        self::assertNull($request->validationData()['idempotency_key']);
        self::assertTrue(Validator::make(
            $request->validationData(),
            $request->rules(),
        )->fails());

        $request->headers->set('Idempotency-Key', 'invalid');
        self::assertTrue(Validator::make(
            $request->validationData(),
            $request->rules(),
        )->fails());
    }
}

final class SyntheticIdempotentRequest extends IdempotentRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function payloadRules(): array
    {
        return [
            'route_value' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadValidationData(): array
    {
        return ['route_value' => 'synthetic-route'];
    }
}
