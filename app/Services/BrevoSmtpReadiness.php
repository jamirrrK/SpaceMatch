<?php

namespace App\Services;

class BrevoSmtpReadiness
{
    /** Boolean diagnostics only: never return a credential or provider response. */
    public function checks(string $mailer = 'smtp'): array
    {
        $prefix = "mail.mailers.{$mailer}";
        $password = (string) config("{$prefix}.password");
        $from = strtolower(trim((string) config('mail.from.address')));
        $username = strtolower(trim((string) config("{$prefix}.username")));

        return [
            'smtp_mailer' => config('mail.default') === $mailer && config("{$prefix}.transport") === 'smtp',
            'brevo_host' => config("{$prefix}.host") === 'smtp-relay.brevo.com',
            'port_587' => (int) config("{$prefix}.port") === 587,
            'supported_starttls_scheme' => config("{$prefix}.scheme") === 'smtp',
            'automatic_starttls' => (bool) config("{$prefix}.auto_tls"),
            'tls_required' => (bool) config("{$prefix}.require_tls"),
            'correct_smtp_login' => $username === 'be87b4001@smtp-brevo.com',
            'sender_address_valid' => filter_var($from, FILTER_VALIDATE_EMAIL) !== false && ! str_ends_with($from, '@smtp-brevo.com'),
            'sender_name' => config('mail.from.name') === 'SpaceMatch',
            'login_distinct_from_sender' => $username !== $from,
            'private_smtp_key_present' => $password !== '',
            // Accept documented short/standard and legacy keys without truncation.
            // Reject common placeholders, API keys and Gmail App Passwords.
            'credential_type_plausible' => strlen($password) >= 15
                && preg_match('/\s/', $password) === 0
                && preg_match('/^[A-Za-z0-9]{16}$/D', $password) !== 1
                && ! str_starts_with(strtolower($password), 'xkeysib-')
                && ! in_array(strtolower($password), ['your_brevo_smtp_key', 'your-brevo-smtp-key', 'your_smtp_key_here'], true),
            'no_url_override' => blank(config("{$prefix}.url")),
            'sender_approval_recorded' => (bool) config('otp.email.brevo_sender_verified'),
            'approval_matches_current_sender' => $from !== '' && strtolower((string) config('otp.email.brevo_verified_sender')) === $from,
        ];
    }

    public function transportConfigured(string $mailer = 'smtp'): bool
    {
        $checks = $this->checks($mailer);
        unset($checks['sender_approval_recorded'], $checks['approval_matches_current_sender']);

        return ! in_array(false, $checks, true);
    }

    public function ready(string $mailer = 'smtp'): bool
    {
        return ! in_array(false, $this->checks($mailer), true);
    }
}
