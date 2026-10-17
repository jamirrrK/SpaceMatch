<?php

namespace Tests\Feature;

use App\Services\OtpVerificationService;
use Tests\TestCase;

class GmailReadinessTest extends TestCase
{
    private function configureGmail(): void
    {
        config([
            'app.env' => 'production',
            'otp.email.enabled' => true,
            'mail.default' => 'smtp',
            'mail.from.address' => 'spacematchhaha@gmail.com',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'spacematchhaha@gmail.com',
            // Dummy fixture credential; no network request is made by these tests.
            'mail.mailers.smtp.password' => str_repeat('a', 16),
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.auto_tls' => true,
            'mail.mailers.smtp.require_tls' => true,
            'mail.mailers.smtp.url' => null,
        ]);
    }

    public function test_gmail_requires_password_matching_sender_and_required_starttls(): void
    {
        $this->configureGmail();
        $service = app(OtpVerificationService::class);
        $this->assertTrue($service->emailAvailable());

        foreach ([
            'password' => null,
            'username' => 'other@gmail.com',
            'port' => 25,
            'scheme' => 'smtps',
            'auto_tls' => false,
            'require_tls' => false,
            'url' => 'smtp://localhost',
        ] as $key => $invalid) {
            $this->configureGmail();
            config(["mail.mailers.smtp.{$key}" => $invalid]);
            $this->assertFalse($service->emailAvailable(), "Gmail accepted invalid {$key} configuration.");
        }
    }

    public function test_gmail_remains_disabled_without_explicit_activation(): void
    {
        $this->configureGmail();
        config(['otp.email.enabled' => false]);
        $this->assertFalse(app(OtpVerificationService::class)->emailAvailable());
    }
}
