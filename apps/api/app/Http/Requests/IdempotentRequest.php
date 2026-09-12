<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared HTTP boundary for replay-safe commands.
 *
 * The request body never supplies authority for the idempotency key. Every
 * command reads the accepted UUID/ULID shape from the Idempotency-Key header,
 * while subclasses define only their payload and route validation contract.
 */
abstract class IdempotentRequest extends FormRequest
{
    private const string IDEMPOTENCY_KEY_RULE =
        'regex:/\A(?:[0-9A-HJKMNP-TV-Z]{26}|[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})\z/';

    /**
     * @return array<string, list<mixed>>
     */
    final public function rules(): array
    {
        return [
            ...$this->payloadRules(),
            'idempotency_key' => [
                'required',
                'string',
                self::IDEMPOTENCY_KEY_RULE,
            ],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    abstract protected function payloadRules(): array;

    /**
     * @return array<string, mixed>
     */
    final public function validationData(): array
    {
        return [
            ...$this->payloadValidationData(),
            'idempotency_key' => $this->header('Idempotency-Key'),
        ];
    }

    /**
     * Override only when route parameters form part of the validated input.
     *
     * @return array<string, mixed>
     */
    protected function payloadValidationData(): array
    {
        return parent::validationData();
    }
}
