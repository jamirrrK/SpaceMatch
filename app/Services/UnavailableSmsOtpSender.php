<?php

namespace App\Services;

use RuntimeException;

class UnavailableSmsOtpSender implements SmsOtpSenderInterface
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function send(string $destination, string $message): void
    {
        throw new RuntimeException('SMS verification is not configured.');
    }
}