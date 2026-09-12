<?php

declare(strict_types=1);

namespace App\Authentication\Password;

use InvalidArgumentException;

final class PasswordPolicyViolation extends InvalidArgumentException {}
