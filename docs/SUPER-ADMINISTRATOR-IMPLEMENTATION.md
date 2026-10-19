# Super Administrator Implementation

## Implemented

- Uses the existing `admin` and `master_admin` roles. Public registration remains limited to renter and owner roles.
- Master-administrator promotion remains restricted to a verified existing administrator, requires interactive confirmation, and is recorded in `audit_logs`.
- The `spacematch:provision-master-admin {email?}` Artisan command can create a master administrator only from an interactive terminal. It collects the initial password through Laravel's hidden `secret()` prompt, confirms it, enforces a strong-password policy, hashes it, and never prints or logs it.
- A newly provisioned account remains email-unverified and is sent through the existing registration OTP flow. Its first successful login is forced to the password-change screen.
- All administrator routes honor the forced password-change flag. Password changes require the current password, strong confirmed replacement, and create an audit event.
- Master administrators now use the administrator dashboard as their post-login destination.
- OTP email delivery uses Laravel's configured mail transport only.

## Apply before provisioning

The additive migration `2026_10_19_020000_add_admin_password_change_requirement` is required by the new first-login control. Review and back up the target database, then apply migrations only in an authorized environment. Do not run migrations against a database without a verified backup and explicit authorization. The provisioning command refuses creation when the required security schema or a configured email OTP transport is unavailable.

Provision interactively on the intended local environment:

```text
php artisan spacematch:provision-master-admin admin@example.test
```

Enter the password only in the hidden prompt. Do not place it in command arguments, shell history, tickets, tests, or documentation. The provisioned account must verify its email code before administrator access is available.

## Not implemented / production blockers

- MFA enrollment, enforcement, recovery codes, and administrator step-up authentication are not implemented.
- Fine-grained permission scopes are not implemented; authorization continues to use the existing role middleware.
- User suspension, owner-verification review, complaints/disputes, and a complete system-settings workspace remain follow-up work.
- No administrator account was created or promoted as part of this change. The active local database was not migrated, and no production or GitHub actions were performed.
