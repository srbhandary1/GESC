<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LessonPracticeMedia extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'lesson_practice_media';

    protected $fillable = [
        'id',
        'organization_id',
        'blueprint_id',
        'part_order',
        'part_id',
        'media_asset_id',
        'metadata_revision_id',
        'metadata_content_hash',
        'media_rights_revision_id',
    ];

    protected function casts(): array
    {
        return [
            'part_order' => 'integer',
        ];
    }
}
