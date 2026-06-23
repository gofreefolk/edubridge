<?php

namespace App\Services\Auth;

use InvalidArgumentException;

class PhoneNormalizer
{
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10) {
            throw new InvalidArgumentException('invalid_phone');
        }

        return $digits;
    }

    public function toE164(string $normalizedPhone): string
    {
        return '+91'.$normalizedPhone;
    }
}
