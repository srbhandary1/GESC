<?php

declare(strict_types=1);

namespace App\Authentication\Mfa;

use RuntimeException;

final class PrivilegedMfaVerificationFailed extends RuntimeException {}
