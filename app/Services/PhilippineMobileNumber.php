<?php

namespace App\Services;

class PhilippineMobileNumber
{
    public static function normalize(?string $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $number = preg_replace('/[\s().-]+/', '', trim($number));
        if (! is_string($number)) {
            return null;
        }

        if (preg_match('/^09\d{9}$/', $number)) {
            return '+63'.substr($number, 1);
        }

        if (preg_match('/^\+639\d{9}$/', $number)) {
            return $number;
        }

        if (preg_match('/^639\d{9}$/', $number)) {
            return '+'.$number;
        }

        return null;
    }
}