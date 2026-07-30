<?php

declare(strict_types=1);

namespace App\Curriculum\Governance;

enum AuthorityKind: string
{
    case EnglishAcademicReviewer = 'english_academic_reviewer';
    case KannadaReviewer = 'kannada_reviewer';
    case RightsConfirmer = 'rights_confirmer';
}
