<?php

require __DIR__.'/gmail-otp-support.php';
$app = gmail_bootstrap();
if (in_array('--clear-config', $argv, true)) {
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    gmail_safe_json(['configuration_cache_cleared' => true]);
    exit(0);
}

$readiness = app(\App\Services\BrevoSmtpReadiness::class);
$report = [
    'checked_at' => gmdate(DATE_ATOM), 'provider' => 'Brevo',
    'checks' => $readiness->checks(),
    'transport_settings_valid' => $readiness->transportConfigured(),
    'sender_approval_confirmed' => (bool) config('otp.email.brevo_sender_verified')
        && strtolower((string) config('otp.email.brevo_verified_sender')) === strtolower((string) config('mail.from.address')),
    'email_otp_enabled' => (bool) config('otp.email.enabled'),
    'registration_available' => app(\App\Services\OtpVerificationService::class)->emailAvailable(),
    'sms_disabled' => ! (bool) config('otp.sms.enabled'),
    'smtp_authenticated' => false, 'smtp_test_accepted' => false,
    'actual_inbox_receipt_confirmed' => false,
];

if (in_array('--authenticate', $argv, true) || in_array('--send-test-to-authorized-inbox', $argv, true)) {
    if (! $readiness->transportConfigured()) {
        $report['blocked_reason'] = 'Required Brevo settings or private SMTP key are missing or invalid.';
    } elseif (in_array('--send-test-to-authorized-inbox', $argv, true) && ! $readiness->ready()) {
        $report['blocked_reason'] = 'Sender approval must be confirmed separately before sending.';
    } else {
        try {
            $transport = \Illuminate\Support\Facades\Mail::mailer('smtp')->getSymfonyTransport();
            $transport->start();
            $report['smtp_authenticated'] = true;
            if (in_array('--send-test-to-authorized-inbox', $argv, true)) {
                $existing = __DIR__.'/../docs/brevo-test-mail.json';
                // One test until its inbox result is resolved; no accidental resend.
                if (is_file($existing)) {
                    $report['blocked_reason'] = 'A prior test-send artifact exists. Resolve inbox confirmation before sending another test.';
                } else {
                    $subject = 'SpaceMatch Brevo delivery check '.gmdate('Y-m-d H:i:s').' UTC';
                    \Illuminate\Support\Facades\Mail::mailer('smtp')->raw(
                        'This is the authorized SpaceMatch Brevo delivery check. It contains no password or verification code. Please confirm actual receipt; SMTP acceptance is not inbox confirmation.',
                        fn ($message) => $message->to('spacematchhaha@gmail.com')->subject($subject),
                    );
                    $report['smtp_test_accepted'] = true;
                    $report['test_subject'] = $subject;
                    file_put_contents($existing, json_encode([
                        'accepted_at' => gmdate(DATE_ATOM), 'subject' => $subject,
                        'recipient' => 'spacematchhaha@gmail.com', 'smtp_accepted' => true,
                        'actual_inbox_receipt_confirmed' => false,
                    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                }
            }
            $transport->stop();
        } catch (\Throwable $error) {
            // Inspect only the numeric SMTP code. Never print exception messages
            // or debug transcripts, which could contain AUTH or message content.
            $code = method_exists($error, 'getCode') ? (int) $error->getCode() : 0;
            $report['failure_category'] = match ($code) {
                535 => 'authentication_rejected', 525 => 'outbound_ip_not_authorized',
                450 => 'transactional_sending_unavailable', 550, 553 => 'sender_or_recipient_rejected',
                default => 'smtp_connection_authentication_or_send_failed',
            };
            $report['blocked_reason'] = 'SMTP operation failed; no secret-bearing diagnostic was displayed.';
        }
    }
}
gmail_safe_json($report, __DIR__.'/../docs/brevo-smtp-readiness.json');
exit(isset($report['blocked_reason']) ? 1 : 0);
