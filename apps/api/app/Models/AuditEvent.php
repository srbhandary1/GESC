<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class AuditEvent extends Model
{
    use HasUlids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(
            static fn (): never => throw new LogicException('Audit events are append-only.'),
        );
        self::deleting(
            static fn (): never => throw new LogicException('Audit events are append-only.'),
        );
    }
}
