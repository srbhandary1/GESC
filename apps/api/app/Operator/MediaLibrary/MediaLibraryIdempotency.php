<?php

declare(strict_types=1);

namespace App\Operator\MediaLibrary;

use App\Curriculum\CanonicalJson;
use App\Models\User;
use App\Operator\MediaLibrary\Exceptions\MediaLibraryConflict;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final readonly class MediaLibraryIdempotency
{
    private const string POLICY_REVISION = 'ADMIN-MEDIA-01-D-0139';

    public function __construct(
        private MediaLibraryAuthorization $authorization,
    ) {}

    /** @param array<string, mixed> $body */
    public function fingerprint(
        string $operation,
        string $targetType,
        string $targetId,
        array $body,
    ): string {
        return CanonicalJson::sha256([
            'body' => $body,
            'operation' => $operation,
            'policy_revision' => self::POLICY_REVISION,
            'target_id' => $targetId,
            'target_type' => $targetType,
        ]);
    }

    public function claim(
        User $actor,
        string $idempotencyKey,
        string $operation,
        string $targetType,
        string $targetId,
        string $fingerprint,
    ): MediaLibraryCommandClaim {
        $context = $this->authorization->context($actor);
        $actorMembershipId = $context->membershipId
            ?? throw new MediaLibraryConflict(MediaLibraryConflict::IDEMPOTENCY);
        $claimId = (string) Str::ulid();

        DB::table('content_workspace_commands')->insertOrIgnore([
            'id' => $claimId,
            'organization_id' => $context->organizationId,
            'actor_membership_id' => $actorMembershipId,
            'idempotency_key' => $idempotencyKey,
            'operation' => $operation,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'request_fingerprint' => $fingerprint,
            'result_type' => null,
            'result_id' => null,
            'completed_at' => null,
            'created_at' => now(),
        ]);
        $command = DB::table('content_workspace_commands')
            ->where('organization_id', $context->organizationId)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        if (! $command instanceof stdClass) {
            throw new MediaLibraryConflict(MediaLibraryConflict::IDEMPOTENCY);
        }

        foreach ([
            'actor_membership_id' => $actorMembershipId,
            'operation' => $operation,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'request_fingerprint' => $fingerprint,
        ] as $field => $expected) {
            if (! $this->matches($command, $field, $expected)) {
                throw new MediaLibraryConflict(
                    MediaLibraryConflict::IDEMPOTENCY,
                );
            }
        }

        $storedId = $this->string($command, 'id');

        if (($command->completed_at ?? null) !== null) {
            return new MediaLibraryCommandClaim(
                id: $storedId,
                replayed: true,
                resultType: $this->string($command, 'result_type'),
                resultId: $this->string($command, 'result_id'),
            );
        }

        if (! hash_equals($storedId, $claimId)) {
            throw new MediaLibraryConflict(MediaLibraryConflict::IDEMPOTENCY);
        }

        return new MediaLibraryCommandClaim($storedId, false);
    }

    public function complete(
        MediaLibraryCommandClaim $claim,
        string $resultType,
        string $resultId,
        CarbonInterface $completedAt,
    ): void {
        $updated = DB::table('content_workspace_commands')
            ->where('id', $claim->id)
            ->whereNull('completed_at')
            ->update([
                'result_type' => $resultType,
                'result_id' => $resultId,
                'completed_at' => $completedAt,
            ]);

        if ($updated !== 1) {
            throw new MediaLibraryConflict(MediaLibraryConflict::IDEMPOTENCY);
        }
    }

    private function matches(stdClass $row, string $field, string $expected): bool
    {
        $actual = $row->{$field} ?? null;

        return is_string($actual) && hash_equals($actual, $expected);
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value) || $value === '') {
            throw new MediaLibraryConflict(MediaLibraryConflict::IDEMPOTENCY);
        }

        return $value;
    }
}
