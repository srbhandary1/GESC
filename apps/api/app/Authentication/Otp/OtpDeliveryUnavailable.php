<?php

declare(strict_types=1);

namespace App\Authentication\Otp;

use RuntimeException;

final class OtpDeliveryUnavailable extends RuntimeException {}
