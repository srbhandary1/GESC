<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $program_id
 * @property string $curriculum_version_id
 * @property int $revision_number
 * @property string $concept_key
 * @property string $state
 * @property array<array-key, mixed> $canonical_json
 * @property string $content_hash
 */
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
