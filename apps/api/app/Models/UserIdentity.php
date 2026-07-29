<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserIdentity extends Model
{
    use HasUlids;

    public const string STATUS_VERIFIED = 'verified';

    public const string STATUS_DISABLED = 'disabled';

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'user_id',
        'type',
        'identifier_digest',
        'display_hint',
        'status',
        'verified_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'identifier_digest',
        'display_hint',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'immutable_datetime',
        ];
    }
}
