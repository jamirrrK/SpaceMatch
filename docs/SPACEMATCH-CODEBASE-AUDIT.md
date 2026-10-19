# SpaceMatch Codebase Audit

## Scope
This audit covers the current Laravel 12 app in `C:\xampp\htdocs\spacematch` and focuses on the actual implemented flows, route wiring, model contracts, migrations, and operational risks.

## Branch and repo state
- Branch: `feature/student-experience-platform-upgrade`, tracking `origin/develop`
- Working tree: contains the uncommitted local upgrade described in this report; no commit or push was made
- Production and the existing local MySQL database were not modified
- The new payment-submission migration was exercised only through the isolated SQLite test/browser databases

## Route structure
The app exposes a structured role-based route tree in `routes/web.php`:

- Public pages: home, property search, property details, space listings, public comparison routes, and property images
- Guest auth: login, register, forgot-password, reset-password
- Auth-only: logout, verify-contact, resend OTP
- Auth + contact verified: dashboard, profile, bookings, payments, maintenance, notifications
- Admin: `/admin/dashboard`, users, property moderation, audit logs, bookings
- Owner: property and space management, inquiry handling, viewing management, tenant management, reports, rental requests, bookings, payments, maintenance
- Renter: dashboard, inquiries, viewing requests, saved spaces, saved properties, renter preferences, rental requests, bookings, reviews, notifications

This confirms the core role model is implemented and the file is the single source of truth for route authorization.

## Authentication and verification
- `App\Http\Controllers\AuthController` handles login, registration, password reset, and redirecting by role.
- `App\Http\Controllers\VerificationController` handles OTP verification and resend flows.
- `App\Services\OtpVerificationService` implements email/SMS challenge issuance and verification, with lockout, expiry, and destination checks.
- `App\Http\Middleware\EnsureContactVerification` redirects unverified users to the verification screen.
- `App\Http\Middleware\EnsureUserHasRole` enforces role gating.

This is a strong foundation for account access control, though the production configuration still needs operational verification for email/SMS delivery settings.

## Core data model and permissions
- `App\Models\User` includes role, OTP relation, saved properties, saved spaces, and renter preference relations.
- `App\Policies\PropertyPolicy` and `App\Policies\SpacePolicy` enforce owner/admin ownership checks for property and space management.
- The database already contains the key entities used in renter/owner flows: users, properties, spaces, bookings, payments, maintenance requests, rental requests, reviews, questions/inquiries, viewing appointments, saved spaces, and notifications.

## Dashboard and workflow coverage
### Renter
The renter dashboard is connected to controller data in `App\Http\Controllers\Renter\DashboardController` and includes:
- active bookings
- pending requests
- completed rentals
- outstanding balances
- saved properties
- saved spaces
- open inquiries
- unread notifications

Relevant routes are present under `/renter/*` and require `auth`, `role:renter`, and `contact.verified`.

### Owner
The owner dashboard in `App\Http\Controllers\Owner\DashboardController` includes:
- property totals and publication status
- space totals and availability
- request counts and booking statistics
- collection totals and due amounts
- move-in/move-out schedule data
- inquiry counts
- revenue and collection chart data

Routes exist for inquiry, viewing, rental request, property, and payment management.

### Admin
Admin routes are present for moderation and report oversight, but the app should still be tested to confirm all admin screens and moderation operations are functional end-to-end.

## Notable integration findings
1. The merge-recovery work resolved the primary dashboard and property-detail conflicts without discarding either implementation.
2. A schema drift issue existed in the pending maintenance request history migration and was fixed by making the migration idempotent and compatible with an already-created table.
3. A duplicate notifications table creation issue was also detected and repaired to avoid `table already exists` errors.
4. The `saved_spaces` feature is implemented in both route and model layers.
5. The app retains both legacy local feature logic and newer coworker feature logic, which is expected in this integration branch but should be validated against the real production contract.
6. The upgrade adds participant-scoped inquiry polling and AJAX replies, a public-listing carousel, renter payment-proof submission and owner/admin review, PDF payment receipts, and restricted Master Administrator access.
7. Payment evidence is kept on the private local disk and exposed only by an authorized download route. The payment workflow records manual transfers; it is not a payment gateway.
8. The additional `payment_submissions` table is defined by a new additive migration. Dompdf adds Composer package/runtime requirements and is used for PDF receipts.
9. Browser testing found and fixed two harness/UI issues: the script now asserts AJAX replies without waiting for navigation, and the carousel pause control no longer overlays the slide navigation controls.

## Risks and follow-up
- Migration safety must remain strict in local MySQL and production; no destructive commands are allowed.
- Pending migration history and `migrations` table integrity should be monitored after any branch merge.
- Feature validation needs to continue against the actual database schema, not only sqlite test memory snapshots.
- Production deployment should not proceed until backup and compatibility checks are confirmed.

## Audit conclusion
The project has a substantial, implemented feature set with real route wiring and domain logic. The most important current risk is not a missing product feature; it is schema/migration drift and ensuring every feature is validated against the live database contract before production deployment.

## Current upgrade validation findings
- `php artisan test`: 152 tests passed, 1,204 assertions.
- `python -m unittest discover -s scripts/deploy -p 'test_*.py' -v`: 54 tests passed, with 2 expected Windows-specific skips (`flock` and OS symlink creation).
- `npm.cmd run build`: Vite production build and frontend sync passed.
- `composer validate --no-check-publish`: passed.
- `php artisan route:list`: passed; 122 routes registered.
- The isolated Playwright workflow passed all 16 scenarios, including OTP registration, search/preferences, inquiry messaging, viewing, rental approval, move-in, payments/proof review, maintenance, rental completion, review moderation, Master Administrator access, carousel controls, and responsive/keyboard checks.
- Browser diagnostics recorded no page errors, console errors, failed HTTP responses, or horizontal overflow at 375, 768, 1366, and 1920 px.
- No migration was applied to the existing local MySQL database. No production command, push, commit, or deployment was performed.

## Migration compatibility summary
Historical note: the following integration-table observations were recorded during the prior database audit. This feature upgrade did not repeat a schema inspection against the existing local MySQL database; its new migration was exercised only on isolated SQLite databases.

- `maintenance_request_status_history` is now safe to run as an additive migration when the table exists; it no longer throws during idempotent schema checks.
- `notifications` was confirmed to exist in the live database and the migration now protects against duplicate creation.
- `saved_spaces`, `renter_preferences`, `inquiries`, `inquiry_activities`, `viewing_appointments`, and `viewing_activities` were validated for the expected table existence, foreign key relationship pattern, and migration ordering.
- `rental_request_histories` was confirmed as a required pending migration and its table is not a duplicate of the rental request entity itself.

## Production safety status
The project is **not ready for production release**. This upgrade adds both a database migration and Composer dependencies; the hardened code-only deployment procedure deliberately excludes migration and Composer files/vendor provisioning. Production PHP/extensions, a compatible verified dependency bundle, backups, cPanel capabilities, and an approved additive migration plan remain unverified. Production `.env`, secrets, uploads, database records, and hosting were not accessed or changed.
