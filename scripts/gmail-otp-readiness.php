<?php

require __DIR__.'/gmail-otp-support.php';
$app = gmail_bootstrap();

$password = (string) config('mail.mailers.smtp.password');
$checks = [
    'mailer_smtp' => config('mail.default') === 'smtp',
    'host_gmail' => config('mail.mailers.smtp.host') === 'smtp.gmail.com',
    'port_587' => (int) config('mail.mailers.smtp.port') === 587,
    'scheme_smtp_starttls' => config('mail.mailers.smtp.scheme') === 'smtp',
    'auto_tls_enabled' => (bool) config('mail.mailers.smtp.auto_tls'),
    'tls_required' => (bool) config('mail.mailers.smtp.require_tls'),
    'official_username' => config('mail.mailers.smtp.username') === 'spacematchhaha@gmail.com',
    'official_from_address' => config('mail.from.address') === 'spacematchhaha@gmail.com',
    'official_from_name' => config('mail.from.name') === 'SpaceMatch',
    'password_present' => $password !== '',
    'app_password_format_valid' => preg_match('/^[A-Za-z0-9]{16}$/D', $password) === 1,
    'no_mail_url_override' => blank(config('mail.mailers.smtp.url')),
    'openssl_available' => extension_loaded('openssl'),
    'sms_disabled' => ! (bool) config('otp.sms.enabled'),
];
unset($password);
$report = [
    'checked_at' => gmdate(DATE_ATOM),
    'checks' => $checks,
    'transport_settings_valid' => ! in_array(false, $checks, true),
    'email_otp_enabled' => (bool) config('otp.email.enabled'),
    'registration_email_available' => app(\App\Services\OtpVerificationService::class)->emailAvailable(),
    'smtp_authenticated' => false,
    'minimal_test_smtp_accepted' => false,
    'inbox_receipt_confirmed' => false,
];
if (! $report['transport_settings_valid']) {
    $report['blocked_reason'] = 'Private Gmail App Password or required transport settings are not valid.';
}

if (in_array('--authenticate', $argv, true) || in_array('--send-test-to-sender', $argv, true)) {
    if (! $report['transport_settings_valid']) {
        $report['blocked_reason'] = 'Required Gmail settings or private App Password are invalid.';
    } else {
        try {
            $transport = \Illuminate\Support\Facades\Mail::mailer('smtp')->getSymfonyTransport();
            $transport->start();
            $report['smtp_authenticated'] = true;
            if (in_array('--send-test-to-sender', $argv, true)) {
                $subject = 'SpaceMatch Gmail readiness '.gmdate('Y-m-d H:i:s').' UTC';
                \Illuminate\Support\Facades\Mail::mailer('smtp')->raw(
                    'This is the authorized SpaceMatch email delivery check. No action, password, or verification code is required. Please confirm receipt in the inbox; SMTP acceptance alone does not confirm receipt.',
                    fn ($message) => $message->to('spacematchhaha@gmail.com')->subject($subject),
                );
                $report['minimal_test_smtp_accepted'] = true;
                $report['test_subject'] = $subject;
                $report['test_recipient'] = 'spacematchhaha@gmail.com';
                file_put_contents(__DIR__.'/../docs/gmail-otp-test-mail.json', json_encode([
                    'accepted_at' => gmdate(DATE_ATOM),
                    'subject' => $subject,
                    'recipient' => 'spacematchhaha@gmail.com',
                    'smtp_authenticated' => true,
                    'smtp_accepted' => true,
                    'inbox_receipt_confirmed' => false,
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            }
            $transport->stop();
        } catch (\Throwable) {
            // Never echo or log transport exceptions, DSNs, passwords or OTPs.
            $report['blocked_reason'] = 'SMTP authentication or sending failed. No secret-bearing exception was printed.';
        }
    }
}

gmail_safe_json($report, __DIR__.'/../docs/gmail-otp-readiness.json');
exit(isset($report['blocked_reason']) ? 1 : 0);
