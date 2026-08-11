<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $authentication_session_id
 * @property string $status
 * @property int $attempts
 * @property CarbonImmutable $available_at
 * @property CarbonImmutable|null $leased_until
 * @property CarbonImmutable|null $completed_at
 */
final class AuthenticationSessionCleanupRequest extends Model
{
    use HasUlids;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_COMPLETED = 'completed';

    protected $guarded = ['*'];

    /**
     * @return BelongsTo<AuthenticationSession, $this>
     */
    public function authenticationSession(): BelongsTo
    {
        return $this->belongsTo(AuthenticationSession::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'leased_until' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
