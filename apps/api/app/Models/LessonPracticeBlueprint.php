<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LessonPracticeBlueprint extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'lesson_practice_blueprints';

    protected $fillable = [
        'id',
        'organization_id',
        'program_id',
        'curriculum_version_id',
        'revision_number',
        'concept_key',
        'state',
        'canonical_json',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'canonical_json' => 'array',
            'revision_number' => 'integer',
        ];
    }
}
