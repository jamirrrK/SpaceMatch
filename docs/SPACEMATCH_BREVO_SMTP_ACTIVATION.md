# SpaceMatch Brevo SMTP activation

**17 October 2026 · Configuration and regression validation complete; real sending/OTP activation awaits the private Brevo SMTP key.**

## Status by phase

| Phase | Result |
| --- | --- |
| Inspect configuration, OTP service/controllers and schema | Complete |
| Configure Brevo transport | Complete; key intentionally left empty pending private entry |
| Sender verification | User confirmed the proposed sender is verified and approved for transactional sending in Brevo; no independent provider API observation |
| Safe database backup/migrations | Fresh backup restored and validated; both OTP migrations already applied; no migrations executed |
| Real test email | Blocked: private SMTP key not configured |
| Activate email OTP | Deferred; `OTP_EMAIL_ENABLED=false` |
| Real renter/owner inbox-backed registration | Not run; depends on key and actual inbox confirmation |
| Regression/build/responsive checks | Passed |

## SMTP configuration

Configured `.env` and `.env.example` use:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_FROM_ADDRESS=spacematchhaha@gmail.com
MAIL_FROM_NAME="SpaceMatch"
MAIL_SCHEME=smtp
MAIL_AUTO_TLS=true
MAIL_REQUIRE_TLS=true
MAIL_TIMEOUT=15
OTP_EMAIL_ENABLED=false
SMS_OTP_ENABLED=false
```

The exact technical SMTP login supplied by the user is configured as `MAIL_USERNAME`; it is distinct from the From address. SMTP credentials are deliberately not reproduced in this report. `MAIL_PASSWORD` must be populated privately with a **generated Brevo SMTP key**. The old Gmail password slot was cleared when switching providers, preventing a Gmail credential from being transmitted to Brevo. Unrelated environment values were preserved.

The installed Laravel `MailManager` passes SMTP options to Symfony's `EsmtpTransportFactory`. Its supported `smtp` scheme plus automatic STARTTLS and required TLS are used for port 587. `MAIL_SCHEME` is supported by the actual installed configuration; an unsupported `MAIL_ENCRYPTION` setting was not added. `MAIL_URL` is empty to prevent a DSN overriding host, sender or credentials. Certificate checking is retained.

## Sender verification

The user explicitly confirmed **spacematchhaha@gmail.com is verified and approved for transactional sending** in Brevo. This confirmation is stored with its source and bound to the exact sender address:

```dotenv
BREVO_SENDER_VERIFIED=true
BREVO_VERIFIED_SENDER=spacematchhaha@gmail.com
```

The example environment keeps these approval flags false/empty. They are account-specific readiness information, not an assumption derived from an SMTP login. No Brevo plugin was found in plugin discovery, and there is no separate local Brevo API credential to independently inspect account sender status. The report therefore distinguishes **user confirmation** from **provider API observation**.

Brevo's account-side sender requirements remain authoritative. The application does not rewrite the From address or bypass provider rejection. No authenticated-domain blocker was reported by the user. If the account subsequently requires a domain-owned sender, use an approved address on a domain the user controls; a personal gmail.com address does not give the user control over Google's domain authentication.

Artifact: [sender-status evidence](brevo-sender-status.json).

## Database backup and migration results

Confirmed development target: **127.0.0.1:3306 / spacematch_db**, MariaDB through Laravel's MySQL connection. Read-only inspection found **no pending migrations**. Both previously discussed OTP migrations were already applied in the earlier Gmail setup:

- `2026_10_16_120000_add_contact_verification_to_users`
- `2026_10_16_120001_create_otp_verifications_table`

No `artisan migrate`, `artisan db`, rollback, wipe, fresh/reset, or drop command was executed in this Brevo activation. The ambiguous bare command names in the request were interpreted as inspection/validation where supported, while honoring the explicit prohibition on migration commands.

A fresh transactional SQL dump was made with destructive DROP statements disabled, restored into a newly named validation database, and checked against the original for table schemas, row counts and SHA-256 record digests. Original source snapshots also matched after validation.

- Private backup: `C:\xampp\htdocs\spacematch\storage\app/private/backups/brevo-20261016_213710_173c06/spacematch_db.sql`
- Size: **20974 bytes**
- SHA-256: `58e481717c5d5008dfcaf65ded107f14aa7d0b3100fdd6637be72ff51a8b9b35`
- Retained validation database: `spacematch_brevo_backupcheck_20261016_213710_173c06`
- All original values unchanged: **yes**
- Temporary database-client credential file removed after use.

Existing users, properties, images, amenities and records were preserved. Validation databases are retained for review; none was dropped. Unit tests use forced in-memory SQLite and browser tests use the existing dedicated SQLite database and test-media root. The browser fixture script now accepts `--no-migrate`, which requires a ready isolated schema and refreshes only its test fixtures.

Artifacts: [backup validation](brevo-database-backup.json), [21-table preservation check](brevo-data-preservation.json).

## SMTP connectivity and actual email delivery

The installed PHP/Symfony transport successfully negotiated **certificate-checked STARTTLS** with `smtp-relay.brevo.com:587`, with TLS explicitly required. That probe sent **no credentials and no email**.

The user chose to configure the private SMTP key later. Therefore:

- SMTP authentication: **not attempted**.
- Authorized test email to the previously authorized sender inbox: **not sent**.
- SMTP acceptance: **not obtained**.
- Actual inbox receipt: **not confirmed**.
- Real Gmail/Brevo-backed OTP registration: **not tested**.

Connection success, sender confirmation, SMTP acceptance and inbox receipt are separate states. None of the successful connection or simulated test results is presented as actual email delivery.

Artifacts: [STARTTLS probe](brevo-starttls.json), [transport readiness](brevo-smtp-readiness.json).

## OTP protections and readiness

The existing six-digit codes, five-minute expiry, five-attempt limit, single-use challenge consumption, resend cooldown, hourly/IP throttles, code hashing, encrypted destinations and pending-account dashboard middleware remain intact. Renter and owner role restrictions are unchanged. SMS remains disabled.

`BrevoSmtpReadiness` now checks the exact technical login independently of the sender, required TLS, non-overridden transport, private key plausibility and sender approval bound to the current From address. It rejects missing keys, Gmail App Passwords, obvious placeholders and REST API keys; it does not impose Gmail's 16-character rule on Brevo's standard, short or legacy key formats. A plausibility check is not provider authentication. The explicit activation flag is still required.

No Blade, styling or application interaction files were changed during this activation. The completed SpaceMatch redesign is preserved.

## Regression results

| Check | Result |
| --- | --- |
| Laravel suite | **81 tests passed, 597 assertions** |
| Brevo-specific readiness regressions | Technical login/sender separation, sender approval, wrong-provider keys, disabled TLS and override checks passed |
| Existing browser workflow suite | **18 passed, 0 failed** |
| Tutorial browser suite | **11 passed, 0 failed** |
| Redesign interaction suite | **6 passed, 0 failed** |
| Responsive matrix | **225 checks: 25 surfaces × nine widths**, including 320px |
| Browser console/runtime/network diagnostics | Clean in completed suites |
| Laravel optimize | Passed |
| Configuration cache cleared after optimize | Passed; keeps private edits effective and avoids cached development DB config in tests |
| Route listing / Blade cache | Passed; original 50 application routes unchanged |
| Production asset build | Passed |

The current baseline is higher than the historical 76-test report because Gmail and Brevo readiness regressions have been added without removing or weakening existing assertions. Configuration and route caches were cleared after the optimize check to restore the original uncached configuration/route behavior; Blade remains cached. Browser registration tests deliberately force the array mailer and OTP/SMS off; they use a seeded challenge rather than real inbox delivery. No actual OTP values are included in screenshots or reports.

Artifacts: [Laravel](brevo-tests.txt), [optimize](brevo-optimize.txt), [routes](brevo-routes.json), [Blade](brevo-views.txt), [build](brevo-build.txt), [workflow](browser/results.json), [tutorial](browser/tutorial-results.json), [redesign interactions](browser/redesign/results.json).

## Resume when the key is configured

Use XAMPP PHP (`C:\xampp\php\php.exe`) for the commands below if `php` is not on PATH.

1. In Brevo's **Settings → SMTP & API → SMTP**, generate/copy the full SMTP key. Enter it directly as `MAIL_PASSWORD` in `C:\xampp\htdocs\spacematch\.env` and save. Never send the key in chat. It must not be a Gmail password, Gmail App Password, Brevo login password or REST API key.
2. Run `php scripts/brevo-env-check.php`, then the secret-safe `php scripts/brevo-smtp-readiness.php --clear-config` and readiness check. Do not dump environment/configuration values.
3. Authenticate with `php scripts/brevo-smtp-readiness.php --authenticate`. External network permission may be required. Diagnostics suppress exception messages, AUTH transcripts and credentials; numeric SMTP codes are mapped to safe categories.
4. Send **one** minimal email to the already authorized inbox using `--send-test-to-authorized-inbox`. The helper requires recorded sender approval and prevents accidental repeat sends when a test-send artifact already exists. It preserves a unique subject and SMTP-acceptance metadata without credentials or OTPs.
5. Ask the user to confirm that unique subject arrived in the inbox (checking spam if necessary). Do not treat SMTP acceptance as receipt.
6. Only after provider readiness and actual delivery confirmation, enable `OTP_EMAIL_ENABLED=true`, clear configuration cache, and test renter and owner registration in a dedicated email-QA database. The user enters received OTPs directly in the local browser; never request those codes in chat or capture them in reports/screenshots.
7. Record each real registration → received email → verification → dashboard → logout → login result separately from the simulated regression suite.

## Remaining blockers

**Private Brevo SMTP key not configured**, per the user's decision to configure it later. Authentication, test-email acceptance, inbox confirmation and live renter/owner OTP registration remain unfinished. Email OTP is intentionally disabled. There are no pending development migrations and no current sender-approval blocker beyond the limitation that approval was user-reported rather than API-observed.

## Sources

- [Brevo SMTP setup](https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP)
- [Brevo SMTP keys](https://help.brevo.com/hc/en-us/articles/7959631848850-Create-and-manage-your-SMTP-keys)
- [Brevo sender verification](https://help.brevo.com/hc/en-us/articles/208836149-Create-a-new-sender-From-name-and-From-email)
- [Brevo SMTP diagnostics](https://help.brevo.com/hc/en-us/articles/115000188150-Troubleshooting-Issues-with-Brevo-SMTP)
- [Brevo sender-status API](https://developers.brevo.com/reference/get-senders)
