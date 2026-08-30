<?php

declare(strict_types=1);

namespace App\Curriculum\LessonPractice\Exceptions;

use RuntimeException;

final class LessonPracticeIdempotencyConflict extends RuntimeException {}
