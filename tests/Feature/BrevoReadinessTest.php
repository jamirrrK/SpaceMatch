<?php

namespace Tests\Feature;

use App\Services\BrevoSmtpReadiness;
use App\Services\OtpVerificationService;
use Tests\TestCase;

class BrevoReadinessTest extends TestCase
{
    private function configureBrevo(): void
    {
        config([
            'app.env' => 'production', 'otp.email.enabled' => true,
            'otp.email.driver' => 'mail',
            'otp.email.brevo_sender_verified' => true,
            'otp.email.brevo_verified_sender' => 'spacematchhaha@gmail.com',
            'mail.default' => 'smtp', 'mail.from.address' => 'spacematchhaha@gmail.com',
            'mail.from.name' => 'SpaceMatch', 'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
            'mail.mailers.smtp.port' => 587, 'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.auto_tls' => true, 'mail.mailers.smtp.require_tls' => true,
            'mail.mailers.smtp.username' => 'be87b4001@smtp-brevo.com',
            // Test-only dummy value; never sent to a provider.
            'mail.mailers.smtp.password' => str_repeat('b', 64),
            'mail.mailers.smtp.url' => null,
        ]);
    }

    public function test_brevo_uses_technical_login_separately_from_verified_sender(): void
    {
        $this->configureBrevo();
        $this->assertTrue(app(OtpVerificationService::class)->emailAvailable());
        config(['mail.mailers.smtp.username' => 'spacematchhaha@gmail.com']);
        $this->assertFalse(app(OtpVerificationService::class)->emailAvailable());
    }

    public function test_brevo_requires_sender_approval_bound_to_current_from_address(): void
    {
        $this->configureBrevo();
        config(['otp.email.brevo_sender_verified' => false]);
        $this->assertTrue(app(BrevoSmtpReadiness::class)->transportConfigured());
        $this->assertFalse(app(OtpVerificationService::class)->emailAvailable());
        config(['otp.email.brevo_sender_verified' => true, 'mail.from.address' => 'other@example.test']);
        $this->assertFalse(app(OtpVerificationService::class)->emailAvailable());
    }

    public function test_brevo_rejects_missing_wrong_provider_credentials_and_insecure_transport(): void
    {
        foreach ([
            ['password', null], ['password', str_repeat('a', 16)],
            ['password', 'xkeysib-'.str_repeat('a', 64)], ['password', 'your_brevo_smtp_key'],
            ['auto_tls', false], ['require_tls', false], ['port', 25],
            ['scheme', 'smtps'], ['url', 'smtp://localhost'],
        ] as [$key, $value]) {
            $this->configureBrevo();
            config(["mail.mailers.smtp.{$key}" => $value]);
            $this->assertFalse(app(OtpVerificationService::class)->emailAvailable(), "Brevo accepted invalid {$key}.");
        }
        $this->configureBrevo();
        config(['mail.mailers.smtp.password' => str_repeat('b', 15)]);
        $this->assertTrue(app(OtpVerificationService::class)->emailAvailable());
        config(['otp.email.enabled' => false]);
        $this->assertFalse(app(OtpVerificationService::class)->emailAvailable());
    }
}
