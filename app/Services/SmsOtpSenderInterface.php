<?php

namespace App\Services;

interface SmsOtpSenderInterface
{
    public function isConfigured(): bool;

    public function send(string $destination, string $message): void;
}