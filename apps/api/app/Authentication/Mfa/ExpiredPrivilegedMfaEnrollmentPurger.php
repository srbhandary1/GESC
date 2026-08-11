<?php

declare(strict_types=1);

namespace App\Authentication\Mfa;

use App\Models\PrivilegedMfaChallenge;
use App\Models\PrivilegedMfaFactor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ExpiredPrivilegedMfaEnrollmentPurger
{
    public function purge(int $limit = 500): int
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The purge limit must be positive.');
        }

        return DB::transaction(function () use ($limit): int {
            $now = Date::now();
            /** @var Collection<int, PrivilegedMfaFactor> $factors */
            $factors = PrivilegedMfaFactor::query()
                ->where('status', PrivilegedMfaFactor::STATUS_PENDING)
                ->where('pending_expires_at', '<=', $now)
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get(['id']);
            $factorIds = $factors->modelKeys();

            if ($factorIds === []) {
                return 0;
            }

            PrivilegedMfaChallenge::query()
                ->whereIn('factor_id', $factorIds)
                ->whereNull('consumed_at')
                ->whereNull('superseded_at')
                ->update([
                    'superseded_at' => $now,
                    'updated_at' => $now,
                ]);
            PrivilegedMfaFactor::query()
                ->whereIn('id', $factorIds)
                ->where('status', PrivilegedMfaFactor::STATUS_PENDING)
                ->update([
                    'secret_ciphertext' => null,
                    'status' => PrivilegedMfaFactor::STATUS_ABANDONED,
                    'pending_expires_at' => null,
                    'updated_at' => $now,
                ]);

            return count($factorIds);
        });
    }
}
