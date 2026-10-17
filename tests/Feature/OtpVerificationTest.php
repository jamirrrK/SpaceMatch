<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\OtpVerification;
use App\Mail\VerificationCodeMail;
use App\Services\OtpVerificationService;
use App\Services\PhilippineMobileNumber;
use App\Services\SmsOtpSenderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;
use Tests\Support\FakeSmsOtpSender;

class OtpVerificationTest extends TestCase
{
    use RefreshDatabase;


    public function test_mail_delivery_failure_does_not_verify_or_unlock_new_account(): void
    {
        config(['otp.testing_email_delivery' => true]);
        Mail::shouldReceive('to')->once()->with('delivery-failure@example.test')->andThrow(new RuntimeException('SMTP unavailable'));

        $this->post('/register', [
            'name' => 'Delivery Failure Renter',
            'email' => 'delivery-failure@example.test',
            'password' => 'SpaceMatch-Password-2026',
            'password_confirmation' => 'SpaceMatch-Password-2026',
            'account_type' => User::ROLE_RENTER,
            'verification_method' => 'email',
            'terms' => '1',
        ])->assertRedirect('/verify-contact')->assertSessionHasErrors('code');

        $user = User::query()->where('email', 'delivery-failure@example.test')->sole();
        $this->assertNotNull($user->verification_required_at);
        $this->assertNull($user->email_verified_at);
        $this->actingAs($user)->get('/renter/dashboard')->assertRedirect('/verify-contact');
        $this->assertNotNull(OtpVerification::query()->where('user_id', $user->id)->value('consumed_at'));
    }

    public function test_email_delivery_requires_explicit_enable_and_a_non_log_transport(): void
    {
        config([
            'app.env' => 'production',
            'otp.email.enabled' => false,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'mailpit',
            'mail.from.address' => 'no-reply@spacematch.test',
        ]);
        $verification = app(OtpVerificationService::class);
        $this->assertFalse($verification->emailAvailable());

        config(['otp.email.enabled' => true, 'mail.default' => 'log']);
        $this->assertFalse($verification->emailAvailable());

        config(['mail.default' => 'smtp']);
        $this->assertTrue($verification->emailAvailable());
    }
    public function test_new_registration_stays_pending_until_email_otp_is_verified(): void
    {
        config(['otp.testing_email_delivery' => true]);
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'New Renter',
            'email' => 'new-renter@example.test',
            'password' => 'SpaceMatch-Password-2026',
            'password_confirmation' => 'SpaceMatch-Password-2026',
            'account_type' => User::ROLE_RENTER,
            'verification_method' => 'email',
            'terms' => '1',
        ]);

        $response->assertRedirect('/verify-contact');
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'new-renter@example.test')->sole();
        $this->assertNotNull($user->verification_required_at);
        $this->assertNull($user->email_verified_at);
        $storedChallenge = DB::table('otp_verifications')->where('user_id', $user->id)->first();
        $this->assertNotSame('new-renter@example.test', $storedChallenge->destination_encrypted);
        $this->assertNotSame($this->sentEmailCode(), $storedChallenge->code_hash);
        $this->actingAs($user)->get('/renter/dashboard')->assertRedirect('/verify-contact');
        $this->get('/verify-contact')->assertOk()
            ->assertSee('Verify account')
            ->assertDontSee('Account settings');
    }

    public function test_legacy_unmarked_accounts_are_not_forced_into_otp_verification(): void
    {
        $legacyOwner = User::factory()->unverified()->create(['role' => User::ROLE_OWNER]);
        $legacyAdmin = User::factory()->unverified()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($legacyOwner)->get('/owner/dashboard')->assertOk();
        $this->assertNull($legacyOwner->verification_required_at);
        $this->actingAs($legacyAdmin)->get('/admin/dashboard')->assertOk();
        $this->assertNull($legacyAdmin->verification_required_at);
    }

    public function test_owner_registration_uses_email_otp_and_marks_email_verified_only_after_success(): void
    {
        $owner = $this->registerPending('otp-owner@example.test', User::ROLE_OWNER);
        $code = $this->sentEmailCode();

        $this->post('/verify-contact', ['code' => $code])->assertRedirect('/owner/dashboard');

        $owner->refresh();
        $this->assertNull($owner->verification_required_at);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertNull($owner->phone_verified_at);
        $this->assertAuthenticatedAs($owner);
        $this->assertNotNull(OtpVerification::query()->where('user_id', $owner->id)->value('consumed_at'));
    }

    public function test_incorrect_code_increments_attempts_and_does_not_verify_account(): void
    {
        $user = $this->registerPending('wrong-code@example.test');
        $code = $this->sentEmailCode();
        $wrongCode = $code === '000000' ? '000001' : '000000';

        $this->post('/verify-contact', ['code' => $wrongCode])->assertSessionHasErrors('code');

        $challenge = OtpVerification::query()->where('user_id', $user->id)->sole();
        $this->assertSame(1, $challenge->attempts);
        $this->assertTrue(Hash::check($code, $challenge->code_hash));
        $this->assertNotSame($code, $challenge->code_hash);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_expired_code_is_rejected_and_consumed(): void
    {
        $user = $this->registerPending('expired-code@example.test');
        $code = $this->sentEmailCode();
        $challenge = OtpVerification::query()->where('user_id', $user->id)->sole();
        $challenge->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->post('/verify-contact', ['code' => $code])->assertSessionHasErrors('code');

        $this->assertNotNull($challenge->fresh()->consumed_at);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_code_is_single_use(): void
    {
        $user = $this->registerPending('single-use@example.test');
        $code = $this->sentEmailCode();

        $this->post('/verify-contact', ['code' => $code])->assertRedirect('/renter/dashboard');
        $this->post('/verify-contact', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_five_incorrect_attempts_lock_the_active_challenge(): void
    {
        $this->registerPending('attempt-limit@example.test');
        $code = $this->sentEmailCode();
        $wrongCode = $code === '999999' ? '000000' : '999999';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/verify-contact', ['code' => $wrongCode])->assertSessionHasErrors('code');
        }

        $this->post('/verify-contact', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_resend_cooldown_and_new_code_invalidate_previous_code(): void
    {
        $user = $this->registerPending('resend-code@example.test');
        $firstCode = $this->sentEmailCode();

        $this->post('/verify-contact/resend')->assertSessionHasErrors('code');

        OtpVerification::query()->where('user_id', $user->id)->update(['sent_at' => now()->subSeconds(61)]);
        $this->post('/verify-contact/resend')->assertSessionHas('status');
        $codes = Mail::sent(VerificationCodeMail::class)->pluck('code');
        $this->assertCount(2, $codes);
        $secondCode = $codes->last();
        $this->assertNotSame($firstCode, $secondCode);
        $this->assertNotNull(OtpVerification::query()->where('user_id', $user->id)->oldest('id')->value('consumed_at'));

        $this->post('/verify-contact', ['code' => $firstCode])->assertSessionHasErrors('code');
        $this->post('/verify-contact', ['code' => $secondCode])->assertRedirect('/renter/dashboard');
    }

    public function test_sms_is_not_available_without_a_configured_sender(): void
    {
        config(['otp.testing_email_delivery' => true]);
        Mail::fake();

        $this->post('/register', [
            'name' => 'SMS Renter',
            'email' => 'sms-renter@example.test',
            'phone_number' => '09171234567',
            'password' => 'SpaceMatch-Password-2026',
            'password_confirmation' => 'SpaceMatch-Password-2026',
            'account_type' => User::ROLE_RENTER,
            'verification_method' => 'sms',
            'terms' => '1',
        ])->assertSessionHasErrors('verification_method');

        $this->assertDatabaseMissing('users', ['email' => 'sms-renter@example.test']);
        $this->assertSame('+639171234567', PhilippineMobileNumber::normalize('09171234567'));
        $this->assertSame('+639171234567', PhilippineMobileNumber::normalize('639171234567'));
        $this->assertSame('+639171234567', PhilippineMobileNumber::normalize('+639171234567'));
        $this->assertNull(PhilippineMobileNumber::normalize('0917123456'));
        $this->assertNull(PhilippineMobileNumber::normalize('08171234567'));
    }

    public function test_fake_sms_sender_can_verify_a_phone_without_marking_email_verified(): void
    {
        config([
            'otp.testing_email_delivery' => true,
            'otp.sms.enabled' => true,
            'otp.sms.driver' => 'test',
        ]);
        Mail::fake();
        $sender = new FakeSmsOtpSender;
        $this->app->instance(SmsOtpSenderInterface::class, $sender);

        $this->post('/register', [
            'name' => 'SMS Owner',
            'email' => 'sms-owner@example.test',
            'phone_number' => '639171234567',
            'password' => 'SpaceMatch-Password-2026',
            'password_confirmation' => 'SpaceMatch-Password-2026',
            'account_type' => User::ROLE_OWNER,
            'verification_method' => 'sms',
            'terms' => '1',
        ])->assertRedirect('/verify-contact');

        $user = User::query()->where('email', 'sms-owner@example.test')->sole();
        $this->assertSame('+639171234567', $user->phone_number);
        $this->assertSame('+639171234567', $sender->messages[0]['destination']);
        preg_match('/verification code is (\d{6})/', $sender->messages[0]['message'], $matches);
        $this->assertCount(2, $matches);

        $this->post('/verify-contact', ['code' => $matches[1]])->assertRedirect('/owner/dashboard');
        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertNull($user->fresh()->email_verified_at);
        Mail::assertNothingSent();
    }

    public function test_email_change_is_not_applied_until_the_new_address_is_verified(): void
    {
        config(['otp.testing_email_delivery' => true]);
        Mail::fake();
        $user = User::factory()->create([
            'email' => 'before-change@example.test',
            'email_verified_at' => now(),
            'role' => User::ROLE_RENTER,
        ]);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'after-change@example.test',
            'phone_number' => '',
        ])->assertRedirect('/verify-contact');

        $this->assertSame('before-change@example.test', $user->fresh()->email);
        $code = $this->sentEmailCode();
        $this->post('/verify-contact', ['code' => $code])->assertRedirect('/profile');
        $this->assertSame('after-change@example.test', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_expired_email_change_code_can_be_resent(): void
    {
        config(['otp.testing_email_delivery' => true]);
        Mail::fake();
        $user = User::factory()->create([
            'email' => 'old-contact@example.test',
            'email_verified_at' => now(),
            'role' => User::ROLE_RENTER,
        ]);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => 'new-contact@example.test',
            'phone_number' => '',
        ])->assertRedirect('/verify-contact');

        $challenge = OtpVerification::query()->where('user_id', $user->id)->sole();
        $challenge->forceFill(['expires_at' => now()->subSecond(), 'sent_at' => now()->subSeconds(61)])->save();
        $this->post('/verify-contact', ['code' => Mail::sent(VerificationCodeMail::class)->last()->code])->assertSessionHasErrors('code');
        $this->post('/verify-contact/resend')->assertSessionHas('status');

        $newCode = Mail::sent(VerificationCodeMail::class)->last()->code;
        $this->post('/verify-contact', ['code' => $newCode])->assertRedirect('/profile');
        $this->assertSame('new-contact@example.test', $user->fresh()->email);
    }

    public function test_pending_account_login_returns_to_verification(): void
    {
        $user = $this->registerPending('pending-login@example.test');
        $this->post('/logout')->assertRedirect('/');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'SpaceMatch-Password-2026',
        ])->assertRedirect('/verify-contact');

        $this->assertAuthenticatedAs($user);
    }

    public function test_otp_cannot_be_used_for_another_account(): void
    {
        config(['otp.testing_email_delivery' => true]);
        Mail::fake();
        $firstUser = $this->registerPending('first-account@example.test');
        $firstCode = $this->sentEmailCode();
        $secondUser = User::factory()->unverified()->create([
            'email' => 'second-account@example.test',
            'role' => User::ROLE_RENTER,
            'verification_method' => 'email',
            'verification_required_at' => now(),
        ]);
        app(OtpVerificationService::class)->issue($secondUser, 'email');
        $secondCode = Mail::sent(VerificationCodeMail::class)->last()->code;

        $this->actingAs($secondUser)->post('/verify-contact', ['code' => $firstCode])->assertSessionHasErrors('code');
        $this->assertNotNull($secondUser->fresh()->verification_required_at);
        $this->post('/verify-contact', ['code' => $secondCode])->assertRedirect('/renter/dashboard');
        $this->assertNotNull($secondUser->fresh()->email_verified_at);
        $this->assertNotNull($firstUser->fresh()->verification_required_at);
    }

    public function test_registration_stays_disabled_when_only_log_mail_and_no_sms_are_configured(): void
    {
        config(['otp.testing_email_delivery' => false, 'otp.sms.enabled' => false]);
        Mail::fake();

        $this->get('/register')->assertOk()
            ->assertSee('Email delivery is not configured for this app.')
            ->assertSee('SMS verification is unavailable');

        $this->post('/register', [
            'name' => 'Undeliverable Renter',
            'email' => 'undeliverable@example.test',
            'password' => 'SpaceMatch-Password-2026',
            'password_confirmation' => 'SpaceMatch-Password-2026',
            'account_type' => User::ROLE_RENTER,
            'verification_method' => 'email',
            'terms' => '1',
        ])->assertSessionHasErrors('verification_method');

        $this->assertDatabaseMissing('users', ['email' => 'undeliverable@example.test']);
        Mail::assertNothingSent();
    }

    public function test_verification_email_has_branded_subject_and_expiry_notice(): void
    {
        $mail = new VerificationCodeMail('042693');
        $html = $mail->render();

        $this->assertSame('Your SpaceMatch Verification Code', $mail->envelope()->subject);
        $this->assertStringContainsString('042693', $html);
        $this->assertStringContainsString('This code expires in 5 minutes.', $html);
        $this->assertStringContainsString('If you did not request this code', $html);
    }

    public function test_invalid_phone_is_rejected_and_unverified_phone_is_normalized(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => '08171234567',
        ])->assertSessionHasErrors('phone_number');

        $this->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => '09171234567',
        ])->assertRedirect('/profile')->assertSessionHasNoErrors();

        $this->assertSame('+639171234567', $user->fresh()->phone_number);
        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_verified_phone_cannot_be_changed_without_an_configured_sms_sender(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'phone_number' => '+639171234567',
            'phone_verified_at' => now(),
        ]);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => '09181234567',
        ])->assertSessionHasErrors('phone_number');

        $this->assertSame('+639171234567', $user->fresh()->phone_number);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_resend_limit_is_enforced_per_account_and_ip(): void
    {
        $user = $this->registerPending('hourly-limit@example.test');
        $code = $this->sentEmailCode();
        $challenge = OtpVerification::query()->where('user_id', $user->id)->sole();
        $challenge->forceFill(['sent_at' => now()->subSeconds(61)])->save();

        for ($resend = 0; $resend < 5; $resend++) {
            OtpVerification::query()->where('user_id', $user->id)->update(['sent_at' => now()->subSeconds(61)]);
            $this->post('/verify-contact/resend')->assertSessionHas('status');
        }

        OtpVerification::query()->where('user_id', $user->id)->update(['sent_at' => now()->subSeconds(61)]);
        $this->post('/verify-contact/resend')->assertSessionHasErrors('code');
        $this->assertNotNull($code);
    }

    private function registerPending(string $email, string $role = User::ROLE_RENTER): User
    {
        config(['otp.testing_email_delivery' => true]);
        Mail::fake();

        $this->post('/register', [
            'name' => 'OTP Test User',
            'email' => $email,
            'password' => 'SpaceMatch-Password-2026',
            'password_confirmation' => 'SpaceMatch-Password-2026',
            'account_type' => $role,
            'verification_method' => 'email',
            'terms' => '1',
        ])->assertRedirect('/verify-contact');

        return User::query()->where('email', $email)->sole();
    }

    private function sentEmailCode(): string
    {
        $mail = Mail::sent(VerificationCodeMail::class)->last();
        $this->assertInstanceOf(VerificationCodeMail::class, $mail);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $mail->code);

        return $mail->code;
    }
}