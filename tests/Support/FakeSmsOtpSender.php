<?php

namespace Tests\Support;

use App\Services\SmsOtpSenderInterface;

class FakeSmsOtpSender implements SmsOtpSenderInterface
{
    public array $messages = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $destination, string $message): void
    {
        $this->messages[] = [
            'destination' => $destination,
            'message' => $message,
        ];
    }
}
