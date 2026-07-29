<?php

declare(strict_types=1);

namespace App\Outbox;

use App\Models\OutboxEvent;
use App\Tenancy\OrganizationContextManager;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class OutboxEventRecorder
{
    /**
     * @var list<string>
     */
    private const array SAFE_PAYLOAD_KEYS = [
        'cohort_id',
        'enrollment_id',
        'identity_id',
        'invite_id',
        'membership_id',
        'organization_id',
        'user_id',
    ];

    public function __construct(
        private OrganizationContextManager $contexts,
    ) {}

    /**
     * @param  array<string, string>  $payload
     */
    public function recordTenant(
        string $eventType,
        string $aggregateType,
        string $aggregateId,
        array $payload,
        ?string $correlationId = null,
    ): OutboxEvent {
        $this->assertSafeDescriptor($eventType);
        $this->assertSafeDescriptor($aggregateType);
        $this->assertUlid($aggregateId);
        $this->assertSafePayload($payload);

        $context = $this->contexts->current();
        $occurredAt = now();

        return OutboxEvent::query()->create([
            'scope_kind' => 'organization',
            'organization_id' => $context->organizationId,
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
            'correlation_id' => $correlationId ?? $context->correlationId,
            'occurred_at' => $occurredAt,
            'available_at' => $occurredAt,
        ]);
    }

    private function assertSafeDescriptor(string $value): void
    {
        if (preg_match('/\A[a-z][a-z0-9_.-]{0,159}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Outbox descriptors must be non-sensitive stable keys.');
        }
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function assertSafePayload(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (! in_array($key, self::SAFE_PAYLOAD_KEYS, true)) {
                throw new InvalidArgumentException('Outbox payload key is not allowlisted.');
            }

            $this->assertUlid($value);
        }
    }

    private function assertUlid(string $value): void
    {
        if (! Str::isUlid($value)) {
            throw new InvalidArgumentException('Outbox identifiers must be ULIDs.');
        }
    }
}
