<?php

declare(strict_types=1);

namespace App\Authentication\Sessions;

use App\Models\AuthenticationSession;
use App\Models\AuthenticationSessionCleanupRequest;
use Carbon\CarbonInterface;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use stdClass;
use Throwable;

final readonly class AuthenticationSessionCleanupWorker
{
    private const int MAX_ATTEMPTS = 10;

    private const int LEASE_MINUTES = 5;

    private const int RETRY_MINUTES = 5;

    public function __construct(private SessionManager $sessions) {}

    public function enqueue(AuthenticationSession $authenticationSession): string
    {
        if ($authenticationSession->revoked_at === null) {
            throw new LogicException(
                'Only revoked authentication sessions may be queued for cleanup.',
            );
        }

        $now = Date::now();
        DB::table('authentication_session_cleanup_requests')->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'authentication_session_id' => (string) $authenticationSession->getKey(),
            'status' => AuthenticationSessionCleanupRequest::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => $now,
            'leased_until' => null,
            'completed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $requestId = DB::table('authentication_session_cleanup_requests')
            ->where(
                'authentication_session_id',
                (string) $authenticationSession->getKey(),
            )
            ->value('id');

        if (! is_string($requestId)) {
            throw new LogicException(
                'The authentication-session cleanup request was not persisted.',
            );
        }

        return $requestId;
    }

    public function processOne(string $requestId): bool
    {
        return $this->process($requestId) === 'completed';
    }

    /**
     * @return array{completed: int, retried: int, exhausted: int}
     */
    public function processDue(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $now = Date::now();
        $requestIds = DB::table('authentication_session_cleanup_requests')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where(function ($query) use ($now): void {
                $query
                    ->where(function ($pending) use ($now): void {
                        $pending
                            ->where(
                                'status',
                                AuthenticationSessionCleanupRequest::STATUS_PENDING,
                            )
                            ->where('available_at', '<=', $now);
                    })
                    ->orWhere(function ($processing) use ($now): void {
                        $processing
                            ->where(
                                'status',
                                AuthenticationSessionCleanupRequest::STATUS_PROCESSING,
                            )
                            ->where('leased_until', '<=', $now);
                    });
            })
            ->orderBy('available_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->filter(static fn (mixed $id): bool => is_string($id))
            ->values()
            ->all();

        $result = [
            'completed' => 0,
            'retried' => 0,
            'exhausted' => 0,
        ];

        foreach ($requestIds as $requestId) {
            $outcome = $this->process($requestId);

            if (array_key_exists($outcome, $result)) {
                $result[$outcome]++;
            }
        }

        return $result;
    }

    private function process(string $requestId): string
    {
        $claim = $this->claim($requestId);

        if ($claim === null) {
            return 'unavailable';
        }

        $destroyed = $this->destroyStorageSession(
            $claim->storage_session_ciphertext,
        );

        return DB::transaction(function () use ($claim, $destroyed): string {
            $now = Date::now();
            $request = DB::table('authentication_session_cleanup_requests')
                ->where('id', $claim->request_id)
                ->where(
                    'status',
                    AuthenticationSessionCleanupRequest::STATUS_PROCESSING,
                )
                ->where('attempts', $claim->attempt)
                ->where('leased_until', $claim->leased_until);

            if ($destroyed) {
                $updated = $request->update([
                    'status' => AuthenticationSessionCleanupRequest::STATUS_COMPLETED,
                    'leased_until' => null,
                    'completed_at' => $now,
                    'updated_at' => $now,
                ]);

                return $updated === 1 ? 'completed' : 'unavailable';
            }

            $updated = $request->update([
                'status' => AuthenticationSessionCleanupRequest::STATUS_PENDING,
                'available_at' => $now->copy()->addMinutes(
                    self::RETRY_MINUTES,
                ),
                'leased_until' => null,
                'completed_at' => null,
                'updated_at' => $now,
            ]);

            if ($updated !== 1) {
                return 'unavailable';
            }

            return $claim->attempt >= self::MAX_ATTEMPTS
                ? 'exhausted'
                : 'retried';
        });
    }

    /**
     * @return object{
     *     request_id: string,
     *     storage_session_ciphertext: string,
     *     attempt: int,
     *     leased_until: CarbonInterface
     * }|null
     */
    private function claim(string $requestId): ?object
    {
        return DB::transaction(function () use ($requestId): ?object {
            $now = Date::now();
            $request = DB::table('authentication_session_cleanup_requests')
                ->where('id', $requestId)
                ->where('attempts', '<', self::MAX_ATTEMPTS)
                ->where(function ($query) use ($now): void {
                    $query
                        ->where(function ($pending) use ($now): void {
                            $pending
                                ->where(
                                    'status',
                                    AuthenticationSessionCleanupRequest::STATUS_PENDING,
                                )
                                ->where('available_at', '<=', $now);
                        })
                        ->orWhere(function ($processing) use ($now): void {
                            $processing
                                ->where(
                                    'status',
                                    AuthenticationSessionCleanupRequest::STATUS_PROCESSING,
                                )
                                ->where('leased_until', '<=', $now);
                        });
                })
                ->lockForUpdate()
                ->first(['authentication_session_id', 'attempts']);

            if (! $request instanceof stdClass) {
                return null;
            }

            $authenticationSession = DB::table('authentication_sessions')
                ->where('id', $request->authentication_session_id)
                ->whereNotNull('revoked_at')
                ->first(['storage_session_ciphertext']);

            if (
                ! $authenticationSession instanceof stdClass
                || ! is_string(
                    $authenticationSession->storage_session_ciphertext,
                )
            ) {
                return null;
            }

            $attempt = (int) $request->attempts + 1;
            $leasedUntil = $now->copy()->addMinutes(self::LEASE_MINUTES);
            DB::table('authentication_session_cleanup_requests')
                ->where('id', $requestId)
                ->update([
                    'status' => AuthenticationSessionCleanupRequest::STATUS_PROCESSING,
                    'attempts' => $attempt,
                    'leased_until' => $leasedUntil,
                    'updated_at' => $now,
                ]);

            return (object) [
                'request_id' => $requestId,
                'storage_session_ciphertext' => $authenticationSession
                    ->storage_session_ciphertext,
                'attempt' => $attempt,
                'leased_until' => $leasedUntil,
            ];
        });
    }

    private function destroyStorageSession(string $ciphertext): bool
    {
        try {
            return $this->sessions
                ->driver()
                ->getHandler()
                ->destroy(Crypt::decryptString($ciphertext));
        } catch (Throwable) {
            return false;
        }
    }
}
