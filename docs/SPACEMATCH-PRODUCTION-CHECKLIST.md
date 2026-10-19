# SpaceMatch production checklist — Phase 3.1

No production deployment is currently authorized. Every unchecked item below is a blocking prerequisite, not an assumption.

## Current release-specific blockers

- [ ] Review additive migration `2026_10_18_130000_create_payment_submissions_table.php` against the actual production schema and migration history; this change has not been applied to local MySQL or production.
- [ ] Establish and verify a Composer dependency bundle containing `barryvdh/laravel-dompdf` 3.1.2 and `dompdf/dompdf` 3.1.6 against the exact production PHP/extensions. Confirm DOM, mbstring, and zlib.
- [ ] Extend the approved deployment procedure to provision reviewed migrations and Composer dependencies safely; the existing code-only deployment flow explicitly does neither.
- [ ] Re-run the complete release validation after any deployment-procedure/dependency-bundle changes. Current local results are 152 Laravel tests/1,204 assertions, 54 deployment tests with 2 Windows skips, frontend build passed, 122 routes, and 16 isolated browser scenarios.

## Hosting and application

- [ ] Confirm Z.com enables shell/Terminal for tejzvrgp; independently verify any future SSH/SFTP permissions and host key without connecting credentials during this phase.
- [ ] Verify Bash, Python >=3.10 with fcntl/SSL/gzip/tar, correct absolute PHP CLI path, PHP >=8.2 and required extensions; verify web PHP matches the exact dependency platform fingerprint.
- [ ] Confirm fixed split app/public paths and separate private state, release and backup roots; state owned by tejzvrgp with mode 700, records/backups owner-only.
- [ ] Verify app private modes 700/600 and public file modes 755/644 are compatible with PHP/static delivery; check runtime and code destinations are writable and quota adequate.
- [ ] Verify customized index.php checks the app's standalone maintenance.php before autoloading; retain original index.php and .htaccess.
- [ ] Confirm file maintenance driver, OPcache revalidation with no stale serving during activation, no persistent workers, and paused cron/queue/request writers.
- [ ] Review managed code/static inventory; no uploads or runtime content may be approved inside those paths. Reject symlinks, hardlinks and special files in managed/release/vendor trees.

## Dependencies, database and backup

- [ ] Establish a verified production bundle/provisioning method outside production. Match composer.json/lock, exact PHP/extensions, successful Composer platform checks and every installed vendor file. Current method/evidence is unavailable, so deployment remains blocked.
- [ ] Create a reviewed private dependencies.json; do not generate an approval by merely hashing unknown installed dependencies. Verify non-authoritative autoload and classmap path/namespace compatibility.
- [ ] Compare every migration hash, live applied migration name and live schema. User-reported 27 applied migrations alone is insufficient. No migration or Composer change is supported by the code-only script.
- [ ] Pause all writers and obtain a fresh consistent SQL + full runtime backup using verified cPanel facilities/tool privileges.
- [ ] Verify checksums, gzip/tar readability, full contents, isolated restoration and record-count/upload/login checks. Create backup.json matching exact release SHA with creation/restore timestamps under one hour old. Retain private encrypted off-host copies.

## Release and rehearsal

- [ ] Review local diff; authorize any later commit/push/merge separately. Protect main and require Linux deployment tests plus application build checks.
- [ ] Pin third-party Actions to reviewed immutable commits. Configure required production reviewers, prevent self-review and restrict deployment branch/bypass.
- [ ] Rehearse real PHP, maintenance entrypoint, native lock, permissions, HTTP/static checks, rollback and failed recovery on an isolated Linux/cPanel-equivalent application/database. Simulated tests do not meet this requirement.
- [ ] Verify the exact approved main SHA, source diff, trusted workflow run, artifact SHA-256 and safe extraction; review whole-code/frontend completeness.
- [ ] Create reviewed, unexpired readiness.json only after evidence exists. The controller never approves capabilities or updates the baseline inventory automatically.
- [ ] Obtain explicit approval for this SHA/backup/window; run preflight under the lock, inspect results, then separately approve activation. Preserve snapshot/history and independent cPanel access for recovery.

## Activation and follow-up

- [ ] Review durable status. Any automatic recovery exits nonzero and must be investigated, even if old health is restored.
- [ ] Verify /up, homepage, exact built asset bytes and protected-file preservation; review logs privately.
- [ ] Test renter/owner login, registration, configured Laravel-mail OTP, authorization, images, dashboards, search, inquiry/viewing/rental flows.
- [ ] Resume writers only after functional checks; record reviewer/operator/results privately and refresh the reviewed baseline for the next release.
- [ ] If recovery cannot restore health, retain maintenance. If maintenance cannot be confirmed, restrict web access through authorized cPanel/provider controls immediately. Never automatically restore a database or remove incident history to bypass a block.
