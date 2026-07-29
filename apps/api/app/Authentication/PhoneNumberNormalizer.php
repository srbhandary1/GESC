<?php

declare(strict_types=1);

namespace App\Authentication;

use InvalidArgumentException;

final class PhoneNumberNormalizer
{
    public function normalize(string $mobileNumber): string
    {
        $normalized = preg_replace('/[\\s().-]+/', '', trim($mobileNumber));

        if (! is_string($normalized) || preg_match('/^\\+[1-9][0-9]{7,14}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('The mobile number must use E.164 format.');
        }

        return $normalized;
    }
}
