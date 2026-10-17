<?php

return [
    'code_length' => 6,
    'expires_minutes' => 5,
    'max_attempts' => 5,
    'resend_cooldown_seconds' => 60,
    'max_resends_per_hour' => 5,
    'max_resends_per_ip_per_hour' => 10,
    'testing_email_delivery' => false,
    'email' => [
        'enabled' => (bool) env('OTP_EMAIL_ENABLED', false),
        'driver' => env('OTP_EMAIL_DRIVER', 'mail'),
        'brevo_sender_verified' => (bool) env('BREVO_SENDER_VERIFIED', false),
        'brevo_verified_sender' => env('BREVO_VERIFIED_SENDER'),
    ],
    'sms' => [
        'enabled' => (bool) env('SMS_OTP_ENABLED', false),
        'driver' => env('SMS_OTP_DRIVER'),
    ],
];
