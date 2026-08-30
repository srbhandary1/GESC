<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $membership_id
 * @property string $preferred_window
 * @property string $topic
 * @property string|null $notes
 * @property string $status
 * @property string $idempotency_key
 * @property string $request_hash
 * @property string|null $acknowledge_idempotency_key
 * @property string|null $acknowledged_by_membership_id
 * @property CarbonImmutable|null $acknowledged_at
 * @property CarbonImmutable|null $created_at
 */
final class TuitionRequest extends Model
{
    public const string STATUS_SUBMITTED = 'submitted';

    public const string STATUS_ACKNOWLEDGED = 'acknowledged';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'tuition_requests';

    protected $fillable = [
        'id',
        'organization_id',
        'user_id',
        'membership_id',
        'preferred_window',
        'topic',
        'notes',
        'status',
        'idempotency_key',
        'request_hash',
        'acknowledge_idempotency_key',
        'acknowledged_by_membership_id',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'immutable_datetime',
        ];
    }
}
