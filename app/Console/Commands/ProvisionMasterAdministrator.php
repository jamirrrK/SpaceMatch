<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

class ProvisionMasterAdministrator extends Command
{
    protected $signature = 'spacematch:provision-master-admin {email? : Email address of an existing administrator}';

    protected $description = 'Grant master administrator access to an existing verified administrator';

    public function handle(): int
    {
        $email = trim((string) ($this->argument('email') ?: $this->ask('Email address of the existing administrator')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');

            return self::INVALID;
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $this->error('No existing account matches that email address. This command does not create user accounts.');

            return self::FAILURE;
        }

        if ($user->hasRole(User::ROLE_MASTER_ADMIN)) {
            $this->info('This account is already a master administrator.');

            return self::SUCCESS;
        }

        if (! $user->hasRole(User::ROLE_ADMIN)) {
            $this->error('Only an existing administrator account can be promoted.');

            return self::FAILURE;
        }

        if ($user->requiresContactVerification()) {
            $this->error('Complete contact verification for this administrator before granting master access.');

            return self::FAILURE;
        }

        if (! $this->confirm("Grant master administrator access to {$user->email}?", false)) {
            $this->warn('No account changes were made.');

            return self::SUCCESS;
        }

        $user->update(['role' => User::ROLE_MASTER_ADMIN]);
        AuditLog::query()->create([
            'user_id' => null,
            'action' => 'master_admin.provisioned',
            'description' => json_encode([
                'target_user_id' => $user->id,
                'source' => 'artisan',
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->info("Master administrator access granted to {$user->email}.");

        return self::SUCCESS;
    }
}
