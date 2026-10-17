# SpaceMatch Gmail OTP activation

**17 October 2026 · Status: prepared; activation blocked by private App Password**

## Official sender

The configured sender and SMTP username are now **spacematchhaha@gmail.com**, with display name **SpaceMatch**. No existing user email address or other existing record was changed. The former planned address is not used in configuration. The existing redesigned frontend is preserved.

## Configuration applied

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=spacematchhaha@gmail.com
MAIL_FROM_ADDRESS=spacematchhaha@gmail.com
MAIL_FROM_NAME="SpaceMatch"
MAIL_AUTO_TLS=true
MAIL_REQUIRE_TLS=true
MAIL_TIMEOUT=15
OTP_EMAIL_ENABLED=false
SMS_OTP_ENABLED=false
```

`MAIL_PASSWORD` is entered only in the local `.env`; its value is intentionally omitted here. Other `.env` values were preserved. `.env.example` documents the new sender without embedding a real password. Laravel's installed Symfony mailer uses the `smtp` scheme with automatic STARTTLS on port 587. `require_tls=true` prevents plaintext fallback. The legacy `MAIL_ENCRYPTION=tls` key is not the installed configuration's transport contract.

## Gmail readiness

| Check | Result |
| --- | --- |
| Gmail host / port / sender / username / name | Passed |
| STARTTLS required and automatic upgrade enabled | Passed |
| Certificate validation | Enabled throughout |
| Network STARTTLS probe | Passed: TLSv1.3 / TLS_AES_256_GCM_SHA384 |
| Installed PHP/Symfony SMTP STARTTLS probe | Passed with required TLS, without credentials or email |
| Private password present | Yes |
| Generated App Password format | **Invalid; blocked** |
| SMTP authentication | Not attempted with the invalid value |
| Minimal authorized test to sender inbox | Not sent yet |
| Actual inbox receipt | **Not confirmed** |
| Live email OTP availability | Disabled |
| SMS availability | Disabled |

The runtime OTP readiness check now rejects Gmail registration if credentials, sender matching, port, scheme, TLS requirements or URL override settings are invalid—even if someone manually toggles `OTP_EMAIL_ENABLED`. Configuration checks are distinct from authentication and inbox delivery. A password-format check cannot prove Gmail issued or accepts a credential.

Initial loading of a malformed local value caused a framework dotenv parse error. Subsequent tools validate syntax privately and suppress secret-bearing exception messages; the generated diagnostic file from that failed run was removed. Do not run commands that dump environment or mail configuration while entering secrets.

## Database: backup validated, migrations applied

Confirmed target: **MySQL connection, 127.0.0.1:3306, spacematch_db**, served by MariaDB 10.4.32. A private SQL backup was created **before** applying the migrations, restored into a newly named validation database, and compared table-by-table for schema, row count and SHA-256 row digests. The source also matched the snapshot at backup validation time.

- Backup: `C:\xampp\htdocs\spacematch\storage\app/private/backups/gmail-otp-20261016_204338_12d976/spacematch_db-before-otp.sql`
- Size: **19239 bytes**
- SHA-256: `2e69407c0177f679e88a45830301a53671cf049037b264831772ee90e0490f87`
- Restored verification database retained: `spacematch_otp_backupcheck_20261016_204338_12d976`
- Temporary database-client credential file removed after use.

Applied migrations:

1. `2026_10_16_120000_add_contact_verification_to_users`: nullable contact/verification columns and nullable phone uniqueness.
2. `2026_10_16_120001_create_otp_verifications_table`: OTP challenges, encrypted destination, code hash, attempt counter and expiry/consumption timestamps.

The original second migration required two compatibility corrections for this MariaDB server: an explicit short composite-index identifier and explicit current-time timestamp defaults to avoid invalid zero-date defaults. The first migration had already applied when the original timestamp definition failed. The remaining migration was resumed only after checking the original restored backup and all original values again. `issue()` continues to supply the five-minute expiry; an omitted expiry now defaults to immediate expiration, which fails closed.

After both migrations, original rows were projected onto their original columns and compared against the pre-migration digests. All original values and counts were preserved, including the original migration rows; only the two new migration entries and additive schema were added. The OTP table is initially empty. No uploads were modified. No database was dropped, wiped, reset, or restored over the original database. The validation database is retained for review rather than deleted.

Detailed artifact: [database and backup validation](gmail-otp-database.json).

## Preserved OTP behavior

Six-digit cryptographically generated codes, five-minute expiration, five-attempt maximum, 60-second resend cooldown, hourly limits, hashed codes, encrypted destinations, single-use verification, role-scoped dashboards and legacy-account behavior are retained. Renters and owners use the existing email-registration flow once activation is completed. SMS stays disabled without an authorized provider.

## Website validation

| Check | Result |
| --- | --- |
| Laravel suite | **78 tests passed, 581 assertions** |
| Gmail readiness regressions | Missing password, mismatched sender, wrong port/scheme, disabled TLS and URL overrides fail closed |
| Existing workflow browser suite | **18 passed, 0 failed** |
| Tutorial browser suite | **11 passed, 0 failed** |
| Redesign interaction browser suite | **6 passed, 0 failed** |
| Responsive matrix | **25 surfaces × nine widths = 225 checks**, including 320px |
| Browser console/runtime/network diagnostics | Clean in completed suites |
| Production asset build | Passed |
| Blade cache | Passed |

Browser regression uses the existing dedicated SQLite database and separate test-media directory, an array mailer, and email/SMS flags forced off; the fixture OTP is not evidence of Gmail delivery. Gmail-ready regression tests use dummy credentials and do not contact a provider.

Artifacts: [Laravel output](gmail-otp-tests.txt), [build output](gmail-otp-build.txt), [readiness](gmail-otp-readiness.json), [PHP STARTTLS](gmail-php-starttls.json), [TLS probe](gmail-starttls-probe.json), [workflow browser results](browser/results.json), [tutorial browser results](browser/tutorial-results.json), [redesign interactions](browser/redesign/results.json).

## What remains blocked

1. Generate an actual Gmail App Password for `spacematchhaha@gmail.com`, then save the generated 16-character code privately as `MAIL_PASSWORD` in `C:\xampp\htdocs\spacematch\.env`. Google first asks for an app name; that name is not the generated credential. Spaces used to display groups of characters are not part of the password. Do not paste the password or an OTP in chat.
2. Run `python scripts/gmail-env-check.py` and `php scripts/gmail-otp-readiness.php` without displaying secrets. A cached configuration must be cleared after a safely validated `.env` update.
3. Authenticate using the actual Laravel SMTP transport and send one minimal test to **spacematchhaha@gmail.com**, as authorized by the user: `php scripts/gmail-otp-readiness.php --send-test-to-sender`. Network permission may be required. The script catches transport exceptions without printing secret-bearing diagnostics.
4. The user confirms the unique subject appeared in the inbox (and checks spam if needed). SMTP acceptance alone is **not** delivery confirmation. Readiness artifacts must record this confirmation explicitly; the sender script never infers it.
5. After valid settings/authentication and inbox confirmation, activate `OTP_EMAIL_ENABLED=true`, clear configuration cache, and verify registration availability. Keep SMS off.
6. Run the real renter and owner registration → received OTP → verification → dashboard → logout → login journeys in a dedicated email-QA database. The user reads and enters OTPs directly in the local browser; OTPs are not requested in chat. Record actual inbox receipts separately from simulated/browser fixture results.

**Real inbox delivery and the actual Gmail-backed registration journey have not been confirmed. Activation is intentionally unfinished until the private App Password and inbox confirmation are available.**

## Sources

- [Google: Sign in with app passwords](https://support.google.com/accounts/answer/185833?hl=en)
- [Google: Gmail SMTP settings and STARTTLS port](https://support.google.com/mail/answer/7104828?hl=en)
- Installed Laravel `MailManager` and Symfony `EsmtpTransportFactory` / `EsmtpTransport` were inspected to configure and verify the actual installed transport.
