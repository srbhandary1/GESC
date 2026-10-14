<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string $provider
 * @property string $subject_digest
 * @property CarbonImmutable $linked_at
 */
final class FederatedIdentity extends Model
{
    use HasUlids;

    public const string PROVIDER_GOOGLE = 'google';

    public const string PROVIDER_FACEBOOK = 'facebook';

    public const string PROVIDER_INSTAGRAM = 'instagram';

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'subject_digest',
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
            'linked_at' => 'immutable_datetime',
        ];
    }
}
