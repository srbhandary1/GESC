<?php

declare(strict_types=1);

namespace App\Authentication\Password;

interface PasswordBlocklist
{
    public function contains(string $normalizedCandidate, string $username): bool;
}
