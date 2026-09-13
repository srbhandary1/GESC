<?php

declare(strict_types=1);

namespace App\Authentication\Mfa;

use Illuminate\Auth\Access\AuthorizationException;

final class FreshPrivilegedMfaRequired extends AuthorizationException {}
