<?php

namespace App\Services;

use App\Exceptions\OtpDeliveryFailed;
use App\Exceptions\OtpDeliveryUnavailable;
use App\Mail\VerificationCodeMail;
use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class OtpVerificationService
{
    public function __construct(private readonly SmsOtpSenderInterface $smsSender)
    {
    }

    public function emailAvailable(): bool
    {
        if (app()->environment('testing') && (bool) config('otp.testing_email_delivery', false)) {
            return true;
        }

        if (! (bool) config('otp.email.enabled', false)) {
            return false;
        }

        if (strtolower((string) config('otp.email.driver', 'mail')) === 'emailjs') {
            return $this->emailJsIsConfigured();
        }

        $mailer = (string) config('mail.default');
        $transport = (string) config("mail.mailers.{$mailer}.transport");

        return match ($transport) {
            'smtp' => $this->smtpIsConfigured($mailer),
            'postmark' => filled(config('services.postmark.key')),
            'resend' => filled(config('services.resend.key')),
            'ses', 'ses-v2' => filled(config('services.ses.key')) || filled(config('services.ses.secret')),
            default => false,
        };
    }

    public function smsAvailable(): bool
    {
        return (bool) config('otp.sms.enabled')
            && filled(config('otp.sms.driver'))
            && $this->smsSender->isConfigured();
    }

    public function availableChannels(): array
    {
        $channels = [];
        if ($this->emailAvailable()) {
            $channels[] = 'email';
        }
        if ($this->smsAvailable()) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    public function issue(User $user, string $channel, string $purpose = 'registration', ?string $destination = null): OtpVerification
    {
        if ($channel === 'email' && ! $this->emailAvailable()) {
            throw new OtpDeliveryUnavailable('Email verification is not configured.');
        }

        if ($channel === 'sms' && ! $this->smsAvailable()) {
            throw new OtpDeliveryUnavailable('SMS verification is not configured.');
        }

        $destination ??= $channel === 'email' ? $user->email : $user->phone_number;
        if (! is_string($destination) || $destination === '') {
            throw new OtpDeliveryUnavailable('A verification destination is required.');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $destinationHash = $this->destinationHash($channel, $destination);

        $challenge = DB::transaction(function () use ($user, $channel, $purpose, $destination, $destinationHash, $code): OtpVerification {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            OtpVerification::query()
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->update(['consumed_at' => now()]);

            return OtpVerification::query()->create([
                'user_id' => $user->id,
                'verification_type' => $channel,
                'purpose' => $purpose,
                'destination_hash' => $destinationHash,
                'destination_encrypted' => $destination,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'sent_at' => now(),
                'expires_at' => now()->addMinutes((int) config('otp.expires_minutes', 5)),
            ]);
        });

        try {
            if ($channel === 'email') {
                $driver = strtolower((string) config('otp.email.driver', 'mail'));
                $useTestMailDelivery = app()->environment('testing')
                    && (bool) config('otp.testing_email_delivery', false);

                if ($driver === 'emailjs' && ! $useTestMailDelivery) {
                    $this->sendEmailJsOtp($destination, $code);
                } else {
                    Mail::to($destination)->send(new VerificationCodeMail($code, $purpose));
                }
            } else {
                $this->smsSender->send(
                    $destination,
                    "SpaceMatch: Your verification code is {$code}. Valid for 5 minutes. Do not share this code.",
                );
            }
        } catch (Throwable) {
            $challenge->forceFill(['consumed_at' => now()])->save();
            throw new OtpDeliveryFailed('We could not send a verification code. Please try again.');
        }

        return $challenge;
    }

    public function verify(User $user, string $code): string
    {
        return DB::transaction(function () use ($user, $code): string {
            $challenge = OtpVerification::query()
                ->where('user_id', $user->id)
                ->whereIn('purpose', ['registration', 'email_change', 'phone_change'])
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $challenge) {
                return 'missing';
            }

            $destination = $challenge->destination_encrypted;
            if (! hash_equals($challenge->destination_hash, $this->destinationHash($challenge->verification_type, $destination))) {
                return 'invalid';
            }

            if ($challenge->expires_at->isPast()) {
                $challenge->forceFill(['consumed_at' => now()])->save();

                return 'expired';
            }

            if ($challenge->attempts >= (int) config('otp.max_attempts', 5)) {
                return 'locked';
            }

            if (! Hash::check($code, $challenge->code_hash)) {
                $challenge->increment('attempts');

                return $challenge->attempts >= (int) config('otp.max_attempts', 5) ? 'locked' : 'invalid';
            }

            if ($challenge->purpose === 'registration' && ! $user->requiresContactVerification()) {
                $challenge->forceFill(['consumed_at' => now()])->save();

                return 'missing';
            }

            if ($challenge->purpose === 'registration') {
                $expectedDestination = $challenge->verification_type === 'email' ? $user->email : $user->phone_number;
                if ($user->verification_method !== $challenge->verification_type
                    || ! is_string($expectedDestination)
                    || ! hash_equals($this->destinationHash($challenge->verification_type, $expectedDestination), $challenge->destination_hash)) {
                    return 'invalid';
                }
            }

            if ($challenge->purpose === 'email_change' && User::query()->where('email', $destination)->whereKeyNot($user->id)->exists()) {
                $challenge->forceFill(['consumed_at' => now()])->save();

                return 'destination_taken';
            }

            if ($challenge->purpose === 'phone_change' && User::query()->where('phone_number', $destination)->whereKeyNot($user->id)->exists()) {
                $challenge->forceFill(['consumed_at' => now()])->save();

                return 'destination_taken';
            }

            $challenge->forceFill(['consumed_at' => now()])->save();

            if ($challenge->verification_type === 'email') {
                $user->forceFill([
                    'email' => $destination,
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->forceFill([
                    'phone_number' => $destination,
                    'phone_verified_at' => now(),
                ]);
            }

            if ($challenge->purpose === 'registration') {
                $user->forceFill([
                    'verification_required_at' => null,
                    'verification_method' => null,
                ]);
            }

            $user->save();

            return 'verified';
        });
    }

    public function activeChallenge(User $user, ?string $purpose = null): ?OtpVerification
    {
        return OtpVerification::query()
            ->where('user_id', $user->id)
            ->when($purpose, fn ($query) => $query->where('purpose', $purpose))
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();
    }

    public function maskDestination(string $channel, string $destination): string
    {
        if ($channel === 'sms') {
            return substr($destination, 0, 4).'******'.substr($destination, -3);
        }

        [$local, $domain] = array_pad(explode('@', $destination, 2), 2, '');
        $visible = mb_substr($local, 0, 1);

        return $visible.str_repeat('*', max(3, mb_strlen($local) - 1)).'@'.$domain;
    }

    private function destinationHash(string $channel, string $destination): string
    {
        $normalized = $channel === 'email' ? mb_strtolower(trim($destination)) : $destination;

        return hash_hmac('sha256', $channel.'|'.$normalized, (string) config('app.key'));
    }

    private function emailJsIsConfigured(): bool
    {
        return filled(config('services.emailjs.service_id'))
            && filled(config('services.emailjs.template_id'))
            && filled(config('services.emailjs.public_key'))
            && filled(config('services.emailjs.private_key'));
    }

    private function sendEmailJsOtp(string $destination, string $code): void
    {
        $requestedAt = now()->toIso8601String();

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->post('https://api.emailjs.com/api/v1.0/email/send', [
                    'service_id' => (string) config('services.emailjs.service_id'),
                    'template_id' => (string) config('services.emailjs.template_id'),
                    'user_id' => (string) config('services.emailjs.public_key'),
                    'accessToken' => (string) config('services.emailjs.private_key'),
                    'template_params' => [
                        'otp_code' => $code,
                        'to_email' => $destination,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $message = strtolower($exception->getMessage());
            $category = str_contains($message, 'timeout')
                || str_contains($message, 'timed out')
                || str_contains($message, 'error 28')
                    ? 'timeout'
                    : 'network_error';

            $this->logEmailJsFailure(null, $category, $requestedAt);

            throw new OtpDeliveryFailed('We could not send a verification code. Please try again.');
        } catch (Throwable) {
            $this->logEmailJsFailure(null, 'transport_error', $requestedAt);

            throw new OtpDeliveryFailed('We could not send a verification code. Please try again.');
        }

        if (! $response->successful()) {
            $status = $response->status();
            $category = match (true) {
                $status === 400 => 'bad_request',
                in_array($status, [401, 403], true) => 'authentication_failed',
                $status === 429 => 'rate_limited',
                $status >= 500 => 'provider_error',
                default => 'http_rejected',
            };

            $this->logEmailJsFailure($status, $category, $requestedAt, $response->header('x-request-id')
                ?? $response->header('x-correlation-id'));

            throw new OtpDeliveryFailed('We could not send a verification code. Please try again.');
        }
    }

    private function logEmailJsFailure(?int $status, string $category, string $requestedAt, ?string $correlationId = null): void
    {
        Log::warning('EmailJS OTP delivery failed', [
            'provider' => 'emailjs',
            'http_status' => $status,
            'category' => $category,
            'request_timestamp' => $requestedAt,
            'correlation_id' => is_string($correlationId)
                && preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $correlationId) === 1
                    ? $correlationId
                    : null,
        ]);
    }

    private function smtpIsConfigured(string $mailer): bool
    {
        $host = strtolower(trim((string) config("mail.mailers.{$mailer}.host")));
        $from = strtolower(trim((string) config('mail.from.address')));

        if ($host === 'smtp-relay.brevo.com') {
            return app(BrevoSmtpReadiness::class)->ready($mailer);
        }

        // Gmail must never advertise registration with incomplete credentials
        // or a transport that permits falling back to plaintext SMTP.
        if ($host === 'smtp.gmail.com') {
            $username = strtolower(trim((string) config("mail.mailers.{$mailer}.username")));
            $password = (string) config("mail.mailers.{$mailer}.password");

            return filter_var($from, FILTER_VALIDATE_EMAIL) !== false
                && $username === $from
                && preg_match('/^[A-Za-z0-9]{16}$/D', $password) === 1
                && (int) config("mail.mailers.{$mailer}.port") === 587
                && config("mail.mailers.{$mailer}.scheme") === 'smtp'
                && (bool) config("mail.mailers.{$mailer}.auto_tls")
                && (bool) config("mail.mailers.{$mailer}.require_tls")
                && blank(config("mail.mailers.{$mailer}.url"));
        }

        return $host !== ''
            && $from !== ''
            && $from !== 'hello@example.com';
    }
}
