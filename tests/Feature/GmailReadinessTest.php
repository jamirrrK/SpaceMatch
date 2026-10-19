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
            'otp.email.driver' => 'mail',
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

    public function test_gmail_requires_authentication_supported_port_and_tls_without_sender_or_password_format_assumptions(): void
    {
        $this->configureGmail();
        $service = app(OtpVerificationService::class);
        $this->assertTrue($service->emailAvailable());

        config(['mail.mailers.smtp.password' => 'abcd efgh ijkl mnop']);
        $this->assertTrue($service->emailAvailable());

        config(['mail.mailers.smtp.username' => 'verified-alias@gmail.com']);
        $this->assertTrue($service->emailAvailable());

        foreach ([
            'password' => null,
            'port' => 25,
            'scheme' => 'smtps',
        ] as $key => $invalid) {
            $this->configureGmail();
            config(["mail.mailers.smtp.{$key}" => $invalid]);
            $this->assertFalse($service->emailAvailable(), "Gmail accepted invalid {$key} configuration.");
        }

        $this->configureGmail();
        config(['mail.mailers.smtp.auto_tls' => false, 'mail.mailers.smtp.require_tls' => false]);
        $this->assertFalse($service->emailAvailable(), 'Gmail accepted an unencrypted transport.');

        $this->configureGmail();
        config(['mail.mailers.smtp.url' => 'smtp://sender%40gmail.com:secret@smtp.gmail.com:587']);
        $this->assertTrue($service->emailAvailable());
    }

    public function test_gmail_remains_disabled_without_explicit_activation(): void
    {
        $this->configureGmail();
        config(['otp.email.enabled' => false]);
        $this->assertFalse(app(OtpVerificationService::class)->emailAvailable());
    }

    public function test_testing_mail_fake_does_not_override_disabled_email_otp(): void
    {
        config([
            'app.env' => 'testing',
            'otp.email.enabled' => false,
            'otp.testing_email_delivery' => true,
        ]);

        $this->assertFalse(app(OtpVerificationService::class)->emailAvailable());
    }
}
