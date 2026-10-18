<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use App\Exceptions\OtpDeliveryFailed;
use App\Exceptions\OtpDeliveryUnavailable;
use App\Services\OtpVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProvisionMasterAdministrator extends Command
{
    protected $signature = 'spacematch:provision-master-admin {email? : Email address of the existing or new administrator}';

    protected $description = 'Securely create a pending master administrator or promote a verified administrator';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) ($this->argument('email') ?: $this->ask('Email address of the administrator'))));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');

            return self::INVALID;
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            return $this->createPendingMasterAdministrator($email);
        }

        if ($user->hasRole(User::ROLE_MASTER_ADMIN)) {
            $this->info('This account is already a master administrator.');

            return self::SUCCESS;
        }

        if (! $user->hasRole(User::ROLE_ADMIN)) {
            $this->error('Only an existing administrator account can be promoted.');

            return self::FAILURE;
        }

        if ($user->requiresContactVerification() || $user->email_verified_at === null) {
            $this->error('Complete contact verification for this administrator before granting master access.');

            return self::FAILURE;
        }

        if (! $this->confirm("Grant master administrator access to {$user->email}?", false)) {
            $this->warn('No account changes were made.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user): void {
            $user->update(['role' => User::ROLE_MASTER_ADMIN]);
            AuditLog::query()->create([
                'user_id' => null,
                'action' => 'master_admin.provisioned',
                'description' => json_encode([
                    'target_user_id' => $user->id,
                    'source' => 'artisan',
                ], JSON_THROW_ON_ERROR),
            ]);
        });

        $this->info("Master administrator access granted to {$user->email}.");

        return self::SUCCESS;
    }

    private function createPendingMasterAdministrator(string $email): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Creating an administrator requires an interactive terminal for masked password entry.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('users', 'password_change_required')
            || ! Schema::hasTable('otp_verifications')
            || ! Schema::hasTable('audit_logs')) {
            $this->error('The required administrator security schema is not installed. Apply the reviewed migrations before provisioning.');

            return self::FAILURE;
        }

        $verification = app(OtpVerificationService::class);
        if (! $verification->emailAvailable()) {
            $this->error('Email OTP delivery is not configured. Configure and verify the Laravel mail transport before provisioning.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Administrator name'));
        $password = (string) $this->secret('Initial password (input is hidden)');
        $confirmation = (string) $this->secret('Confirm initial password (input is hidden)');

        $validator = Validator::make([
            'name' => $name,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::min(14)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::INVALID;
        }

        if (! $this->confirm("Create a new master administrator for {$email}?", false)) {
            $this->warn('No account changes were made.');

            return self::SUCCESS;
        }

        $user = DB::transaction(function () use ($email, $name, $password): User {
            $created = User::query()->create([
                'name' => $name,
                'email' => $email,
                'role' => User::ROLE_MASTER_ADMIN,
                'password' => Hash::make($password),
                'verification_required_at' => now(),
                'verification_method' => 'email',
                'password_change_required' => true,
            ]);

            AuditLog::query()->create([
                'user_id' => null,
                'action' => 'master_admin.provisioned',
                'description' => json_encode([
                    'target_user_id' => $created->id,
                    'source' => 'artisan',
                    'created' => true,
                    'contact_verification_required' => true,
                    'password_change_required' => true,
                ], JSON_THROW_ON_ERROR),
            ]);

            return $created;
        });

        try {
            $verification->issue($user, 'email');
        } catch (OtpDeliveryFailed|OtpDeliveryUnavailable) {
            $this->error("The account was created but its verification code could not be delivered. Configure mail delivery, then sign in as {$email} and request a new code.");

            return self::FAILURE;
        }

        $this->info("Pending master administrator account created for {$email}; a verification code was sent.");

        return self::SUCCESS;
    }
}
