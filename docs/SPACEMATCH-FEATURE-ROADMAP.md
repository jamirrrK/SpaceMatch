# SpaceMatch Feature Roadmap

## Current branch and delivery state
- Branch: `feature/student-experience-platform-upgrade`, based on `origin/develop`.
- This working tree contains uncommitted local changes. No commit, push, pull request, merge, local-MySQL migration, or production action was performed.
- The previous student-renter workflow is already present in the branch base; this upgrade extends it rather than recreating it.

## Completed and locally verified in this upgrade
- Participant-scoped inquiry conversations with searchable lists, accessible chat presentation, AJAX replies, polling, unread counts, and throttled messaging routes.
- Homepage carousel sourced only from published, available listings, with accessible/reduced-motion-aware controls.
- Renter payment-evidence submission, owner/admin review, rejection reasons, private evidence retrieval, transactional ledger updates, audit records, and PDF receipts.
- Master Administrator role access management with restricted promotion/demotion routes, self-demotion and unverified-account protections, audit logging, and an Artisan provisioning command.
- Renter-facing navigation links point to renter workflows rather than owner-only pages.
- Browser-verified workflow from registration/OTP through rental completion and review moderation, plus responsive and keyboard checks.

## Priority 1 — Safe integration and release preparation
1. Review the uncommitted feature diff and migration against the actual intended production schema.
2. Verify the production PHP version and DOM, mbstring, and zlib extensions required by the PDF dependency.
3. Build and verify a production-compatible Composer dependency bundle; the existing code-only deploy process does not install or update `vendor`.
4. Obtain an approved, fresh database/runtime backup and validate restoration in isolation.
5. Review and approve the additive `payment_submissions` migration before any database change.

## Priority 2 — Broader functional confidence
1. Continue tests against the supported production database engine using a separate disposable database; do not use the existing local or production database for destructive test setup.
2. Verify hosted OTP delivery and PDF generation only in an approved staging environment.
3. Add PDF receipt download and additional owner/admin payment-evidence edge cases to browser coverage if the staging environment permits.

## Priority 3 — Operational readiness
1. Confirm cPanel shell, PHP CLI/web parity, permissions, OPcache, maintenance behavior, storage paths, and quota.
2. Establish exact release artifacts, dependency provisioning, backup checks, migration steps, smoke tests, and recovery procedures.
3. Obtain explicit review/approval before committing, pushing, opening a pull request, merging, or deploying.

## Remaining limitations
- Browser coverage uses a fresh isolated SQLite database and synthetic OTP sink; it does not verify live email/SMS delivery or MySQL behavior.
- Production hosting capabilities and dependency compatibility remain unverified.
- The new additive migration was not run against the existing local MySQL database or production.
- The payment flow records manually transferred funds and owner/admin review; no gateway integration exists.
