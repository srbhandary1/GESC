<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LessonPracticeReview extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'lesson_practice_reviews';

    protected $fillable = [
        'id',
        'organization_id',
        'blueprint_id',
        'review_kind',
        'blueprint_content_hash',
        'decision',
        'reviewer_membership_id',
    ];
}
