# SpaceMatch OTP Verification Implementation

**Date:** 2026-10-16
**Status:** Secure OTP registration and verification flow implemented. **Real email delivery and SMS delivery were not verified in this environment.**

## Authentication Architecture

SpaceMatch uses Laravel's existing session guard and `App\Models\User`, with role values stored on `users.role`. Public registration allows renters and owners only. The existing login and password-reset flow remains in place.

New registrations are marked with nullable `verification_required_at` and `verification_method` fields. The new `contact.verified` middleware blocks protected application routes only when that marker is set. Unmarked legacy users, including administrators, retain existing access even if their old `email_verified_at` is null. Public listing routes and logout remain available to pending users.

## Database Changes

Two additive migrations were added:

- `2026_10_16_120000_add_contact_verification_to_users.php` adds nullable `phone_number`, `phone_verified_at`, `verification_required_at`, and `verification_method`. Phone numbers have a unique nullable index. Existing phone/contact values are not rewritten.
- `2026_10_16_120001_create_otp_verifications_table.php` creates per-user challenges with type, purpose, keyed destination hash, encrypted destination, password hash, attempt count, send/expiry/consume timestamps, and indexes.

No migration was run against the development database. The fixture migration was applied only to `storage/app/private/phase3-browser.sqlite`. The development `.env`, accounts, and records were not changed.

## OTP Generation and Verification

- Six-digit codes are created with PHP `random_int`, including leading zeroes.
- Codes are stored using Laravel's password hasher; plaintext is not stored in the application database.
- Destinations are encrypted at rest and also keyed-hashed for comparison.
- Challenges expire after five minutes, allow at most five incorrect attempts, and are consumed after success or expiry.
- Challenge issuance locks the user row and consumes any prior active challenge for the same purpose. Verification locks the active challenge and consumes it in the same database transaction as the account verification update.
- Email verification updates `email_verified_at`; SMS verification updates `phone_verified_at` only.
- Registration challenges are bound to the account's selected channel and current destination. Verification is user-scoped and cannot be replayed across accounts.
- Resend has a 60-second cooldown, five-per-hour account/destination limit, ten-per-hour IP limit, plus route-level request throttling. New challenges invalidate prior active challenges.
- OTPs are not logged, printed in application responses, or included in browser console output. The only fixed OTP is in the isolated browser fixture/test script and is not production application code.

## Philippine Mobile Numbers

Registration and profile updates normalize `09XXXXXXXXX`, `+639XXXXXXXXX`, and `639XXXXXXXXX` forms to `+639XXXXXXXXX`. Invalid structures are rejected. Existing phone records are not backfilled or rewritten.

## Email Delivery Configuration

The branded Laravel mailable is `App\Mail\VerificationCodeMail`; its subject is `Your SpaceMatch Verification Code`. It states the five-minute expiry and safely tells unintended recipients to ignore the message.

Email OTP requires `OTP_EMAIL_ENABLED=true` plus a configured non-log transport and sender address. For SMTP, configure `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, any required `MAIL_USERNAME`/`MAIL_PASSWORD`, and a real `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` in the deployment environment. Do not put credentials in source control. A local SMTP capture inbox may be explicitly enabled for local verification after it is actually running.

**Current environment:** effective `MAIL_MAILER=log`; `OTP_EMAIL_ENABLED=false`. No SMTP capture tool was installed, and no real email was sent or verified. Registration therefore remains disabled in the current live app rather than falsely claiming that a log entry is delivery. Automated tests use `Mail::fake()` and explicitly enable test delivery.

## SMS Provider Integration

`SmsOtpSenderInterface` defines the provider boundary. `UnavailableSmsOtpSender` is bound by default and always reports unavailable. SMS is disabled unless `SMS_OTP_ENABLED` and `SMS_OTP_DRIVER` are set and an authorized provider adapter is bound. Generic `SMS_OTP_API_KEY` and `SMS_OTP_SENDER_ID` environment names are documented in `.env.example`; no endpoint, credential, or provider behavior has been invented.

**Current environment:** SMS is disabled and no real SMS adapter is implemented or configured. The `Tests\Support\FakeSmsOtpSender` is test-only and records messages in memory; it does not deliver SMS.

## Registration, Access, and Profile Changes

- Registration requires an available selected channel and will not create an account if no delivery channel is available.
- New users are authenticated into a pending state so they can verify, resend, or log out. Protected owner/renter/admin features redirect pending new accounts to `/verify-contact`.
- Login for a pending account redirects to verification. Successful verification regenerates the session and redirects by role.
- Profile email changes leave the old address in place until the new address is verified. Replacing a verified phone requires an available SMS sender; an unverified phone can be edited but remains unverified.
- Only one contact method may be changed per profile submission.
- Registration does not offer admin role selection. Existing administrator accounts are not forced into verification.

## Automated Tests

The OTP feature tests cover renter and owner registration, correct/incorrect/expired/single-use codes, attempt limits, resend cooldown/hourly limits and invalidation, pending login, protected dashboard access, legacy and administrator access, cross-account isolation, delivery failure, phone normalization, phone/email verification independence, profile contact re-verification, disabled channels, fake SMS delivery, and email rendering.

Final Laravel suite: **75 tests, 562 assertions, 0 failures**.
OTP subset: **21 tests, 188 assertions, 0 failures**.

## Browser Tests

The isolated Playwright suite uses generated accounts, a dedicated SQLite file, and generated property-image fixtures. It verified disabled unconfigured registration channels, masked OTP page rendering at mobile width, pending login redirect, test-fixture OTP verification, logout, and a later verified login, in addition to existing SpaceMatch workflows and the eight-width responsive matrix.

Result: **18 passed, 0 failed**. Browser console/page errors, failed requests/responses, and responsive overflow: **0**. Screenshot: [OTP verification mobile](browser/otp-verification-mobile.png). Machine-readable results: [browser/results.json](browser/results.json).

The browser OTP uses an isolated seeded test challenge. It does not prove that a real email inbox or SMS handset received a message.

## Routes and Build

- Added authenticated `GET /verify-contact`, `POST /verify-contact`, and `POST /verify-contact/resend` routes.
- Protected existing dashboard and application route groups with the pending-contact middleware; role middleware remains in force.
- Route count is now 50.
- `php artisan optimize:clear`, `php artisan route:list --except-vendor`, `php artisan view:cache`, `php artisan test`, `npm run build`, and JavaScript syntax checks passed.
- No destructive database command was run and no migration was applied to the development database.

## Remaining Limitations

1. Configure and verify a real SMTP/API mail transport before enabling `OTP_EMAIL_ENABLED` outside isolated tests.
2. Select an authorized Philippine-compatible SMS provider, implement/bind its `SmsOtpSenderInterface` adapter, configure secrets outside source control, and verify delivery before enabling SMS.
3. Concurrency behavior uses transactional row locks, but the automated suite uses SQLite and does not simulate concurrent requests against production MySQL/MariaDB.
4. Automated tests use Laravel's test middleware behavior; deployment should retain the standard `web` CSRF middleware and rate limiter/cache configuration.
5. The user explicitly asked not to run migrations against the development database. Apply the two additive migrations only to the intended database after reviewing its target and backup policy.